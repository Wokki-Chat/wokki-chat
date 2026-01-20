from datetime import datetime, timezone
import json
import uuid
from server.config import message_timestamps, MAX_MESSAGES, TIME_WINDOW_SECONDS
from server.helpers.user_helpers import auth_required, is_user_friends_with, get_user_premium_status
from server.helpers.dm_helpers import get_sid_from_dm_id
import aiomysql
import server.sio_instance as sio_instance
import server.config as config
from server.helpers.logs import addMessageToLogs

@auth_required(server_required=False, allow_bots=False)
async def get_direct_messages(sid, metadata, data):
    dm_id = data.get('dm_id')
    offset = data.get('offset', 0)

    user_id = metadata.get('account_id')

    if not dm_id:
        await addMessageToLogs(f"Missing required field dm_id for get_direct_messages", "INFO")
        await sio_instance.sio.emit('get_direct_messages_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return

    try:
        offset = int(offset)
        if offset < 0:
            offset = 0
    except Exception:
        offset = 0

    limit = 25

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if not await is_user_friends_with(cur, user_id, dm_id):
                await addMessageToLogs(f"User is not friends with dm_id for get_direct_messages, user id: {user_id}, dm_id: {dm_id}", "INFO")
                await sio_instance.sio.emit('get_direct_messages_response', {'success': False, 'error': 'User is not friends'}, to=sid)
                return
            
            await cur.execute(
                '''
                SELECT COUNT(*) FROM direct_messages WHERE (to_id = %s AND from_id = %s) OR (to_id = %s AND from_id = %s)
                ''',
                (dm_id, user_id, user_id, dm_id)
            )
            total_count = (await cur.fetchone())['COUNT(*)']
            await addMessageToLogs(f"Total count for get_direct_messages: {total_count}, user id: {user_id}, dm_id: {dm_id}", "INFO")

            await cur.execute(
                '''
                SELECT dm.id, dm.message, dm.from_id AS sent_by, u.username, dm.created_at, dm.updated_at,
                    dm.edited, dm.to_id, dm.parent_message_id, dm.assets,
                    u.profile_picture
                FROM direct_messages dm
                JOIN users u ON dm.from_id = u.id
                WHERE (dm.to_id = %s AND dm.from_id = %s) OR (dm.to_id = %s AND dm.from_id = %s)
                ORDER BY created_at DESC
                LIMIT %s OFFSET %s
                ''',
                (dm_id, user_id, user_id, dm_id, limit, offset)
            )
            messages = list(await cur.fetchall())
            messages.reverse()

            for msg in messages:
                if isinstance(msg.get('created_at'), datetime):
                    msg['created_at'] = msg['created_at'].astimezone(timezone.utc).isoformat().replace('+00:00', 'Z')
                else:
                    msg['created_at'] = None

                if isinstance(msg.get('updated_at'), datetime):
                    msg['updated_at'] = msg['updated_at'].astimezone(timezone.utc).isoformat().replace('+00:00', 'Z')
                else:
                    msg['updated_at'] = None

                if msg.get('assets'):
                    try:
                        msg['assets'] = json.loads(msg['assets'])
                    except Exception:
                        msg['assets'] = []
                else:
                    msg['assets'] = []

    await sio_instance.sio.emit('all_direct_messages', messages, to=sid)
    await addMessageToLogs(f"Emitted all_direct_messages for get_direct_messages, user id: {user_id}, dm_id: {dm_id}", "INFO")
    await sio_instance.sio.emit('get_direct_messages_response', {'success': True, 'count': len(messages), 'total': total_count, 'offset': offset}, to=sid)
    await addMessageToLogs(f"Emitted get_direct_messages_response for get_direct_messages, user id: {user_id}, dm_id: {dm_id}", "INFO")

@auth_required(server_required=False, allow_bots=False)
async def send_direct_message(sid, metadata, data):
    dm_id = data.get('dm_id')
    message = data.get('message')
    parent_message_id = data.get('parent_message_id')
    file_names = data.get('file_names')

    user_id = metadata.get('account_id')

    if not all([dm_id, message]):
        await addMessageToLogs(f"Missing required fields for send_direct_message", "INFO")
        await sio_instance.sio.emit('send_direct_message_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return

    message_id = str(uuid.uuid4())
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            now = datetime.now(timezone.utc)
            timestamps = message_timestamps[user_id]
            
            if not await is_user_friends_with(cur, user_id, dm_id):
                await addMessageToLogs(f"User is not friends with dm_id for send_direct_message, user id: {user_id}, dm_id: {dm_id}", "INFO")
                await sio_instance.sio.emit('send_direct_message_response', {'success': False, 'error': 'User is not friends'}, to=sid)
                return
            
            while timestamps and (now - timestamps[0]).total_seconds() > TIME_WINDOW_SECONDS:
                timestamps.popleft()

            if len(timestamps) >= MAX_MESSAGES:
                await addMessageToLogs(f"Rate limit exceeded for send_direct_message, user id: {user_id}", "INFO")
                await sio_instance.sio.emit('send_direct_message_response', {
                    'success': False,
                    'error': f'Rate limit exceeded. Max {MAX_MESSAGES} messages every {TIME_WINDOW_SECONDS} seconds.'
                }, to=sid)
                return

            timestamps.append(now)
            is_premium = await get_user_premium_status(cur, user_id)
            print(is_premium)
            limit = 10000 if is_premium else 3000
            if len(message) > limit:
                await addMessageToLogs(f"Message too long for send_direct_message, user id: {user_id}, premium: {is_premium}, limit: {limit}", "INFO")
                await sio_instance.sio.emit('send_direct_message_response', {
                    'success': False,
                    'error': f'Message too long. Limit is {limit} characters.'
                }, to=sid)
                return

            await cur.execute('SELECT username, profile_picture FROM users WHERE id = %s', (user_id,))
            user_row = await cur.fetchone()
            if not user_row:
                await addMessageToLogs(f"User not found for send_direct_message, user id: {user_id}", "INFO")
                await sio_instance.sio.emit('send_direct_message_response', {'success': False, 'error': 'User not found'}, to=sid)
                return
            
            username = user_row['username']
            profile_picture = user_row['profile_picture']
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

            assets_json = None
            if file_names and isinstance(file_names, list) and len(file_names) > 0:
                assets_list = []
                for f in file_names:
                    saved_name = f.get('savedName') or f.get('saved_name') or f.get('file_name')
                    original_name = f.get('originalName') or f.get('original_name') or saved_name
                    if saved_name:
                        assets_list.append({
                            'savedName': saved_name,
                            'originalName': original_name
                        })
                assets_json = json.dumps(assets_list) if assets_list else None

            await cur.execute(
                '''
                INSERT INTO direct_messages 
                (id, message, from_id, to_id, created_at, updated_at, edited, parent_message_id, assets)
                VALUES (%s, %s, %s, %s, %s, NULL, FALSE, %s, %s)
                ''',
                (message_id, message, user_id, dm_id, timestamp, parent_message_id, assets_json)
            )
            
            await addMessageToLogs(f"Inserted message for send_direct_message, user id: {user_id}, dm_id: {dm_id}", "INFO")

    dm_sid = await get_sid_from_dm_id(dm_id)

    if isinstance(timestamp, str):
        timestamp = datetime.fromisoformat(timestamp)

    await sio_instance.sio.emit('new_direct_message', {
        'id': message_id,
        'username': username,
        'message': message,
        'created_at': timestamp.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'sent_by': user_id,
        'parent_message_id': parent_message_id,
        'profile_picture': profile_picture,
        'assets': json.loads(assets_json) if assets_json else []
    }, to=[dm_sid, sid])
    await addMessageToLogs(f"Emitted new_direct_message for send_direct_message, user id: {user_id}, dm_id: {dm_id}", "INFO")
    await sio_instance.sio.emit('send_direct_message_response', {'success': True}, to=sid)
    await addMessageToLogs(f"Emitted send_direct_message_response for send_direct_message, user id: {user_id}, dm_id: {dm_id}", "INFO")
    
@auth_required(server_required=False, allow_bots=False)
async def get_direct_message_by_id(sid, metadata, data):
    message_id = data.get('message_id')
    dm_id = data.get('dm_id')

    user_id = metadata.get('account_id')

    if message_id is None or dm_id is None:
        await addMessageToLogs(f"Missing required fields for get_direct_message_by_id", "INFO")
        await sio_instance.sio.emit('direct_message_by_id', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if not await is_user_friends_with(cur, user_id, dm_id):
                await addMessageToLogs(f"User is not friends with dm_id for get_direct_message_by_id, user id: {user_id}, dm_id: {dm_id}", "INFO")
                await sio_instance.sio.emit('direct_message_by_id', {'success': False, 'error': 'User is not friends'}, to=sid)
                return

            await cur.execute(
                "SELECT id, from_id AS sent_by, message, created_at FROM direct_messages WHERE id = %s AND ((from_id = %s AND to_id = %s) OR (from_id = %s AND to_id = %s))",
                (message_id, user_id, dm_id, dm_id, user_id)
            )
            message = await cur.fetchone()

            if not message:
                await sio_instance.sio.emit('direct_message_by_id', None, to=sid)
                return

            if isinstance(message.get('created_at'), datetime):
                message['created_at'] = message['created_at'].isoformat()

            await sio_instance.sio.emit('direct_message_by_id', message, to=sid)
            
@auth_required(server_required=False, allow_bots=False) # no logging added, why
async def delete_direct_message(sid, metadata, data):
    access_token = data.get('access_token')
    message_id = data.get('message_id')
    dm_id = data.get('dm_id')

    user_id = metadata.get('account_id')

    if access_token is None or message_id is None:
        await sio_instance.sio.emit('direct_message_deleted', {'success': False, 'error': 'Missing required fields'}, to=[dm_sid, sid])
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if not await is_user_friends_with(cur, user_id, dm_id):
                await sio_instance.sio.emit('direct_message_deleted', {'success': False, 'error': 'User is not friends'}, to=[dm_sid, sid])
                return

            result = await cur.execute("DELETE FROM direct_messages WHERE id = %s AND from_id = %s AND to_id = %s", (message_id, user_id, dm_id))
            if result == 0:
                await sio_instance.sio.emit('direct_message_deleted', {'success': False, 'error': 'Failed to delete message'}, to=[dm_sid, sid])
                return

            dm_sid = await get_sid_from_dm_id(dm_id)

            await conn.commit()
            await sio_instance.sio.emit('direct_message_deleted', {'success': True, 'message_id': message_id}, to=[dm_sid, sid])