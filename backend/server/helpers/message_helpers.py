from datetime import datetime, timezone
import json
from server.config import user_message_timestamps, MAX_MESSAGES, TIME_WINDOW_SECONDS
from server.helpers.user_helpers import verify_access_token, get_user_premium_status
from server.helpers.server_helpers import is_user_in_server, does_user_have_server_permission, send_server_notifications, get_server_channel_sids, get_server_users_info
import aiomysql
import uuid
import server.sio_instance as sio_instance
import server.config as config

async def send_message(sid, data):
    print(f"[send_message] Received from {sid}: {data}")
    access_token = data.get('access_token')
    message = data.get('message')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    parent_message_id = data.get('parent_message_id')
    file_names = data.get('file_names')

    if not all([access_token, message, server_id, channel_id]):
        print("[send_message] Missing one of access_token, message, server_id, or channel_id")
        await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return

    message_id = str(uuid.uuid4())

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            now = datetime.now(timezone.utc)
            timestamps = user_message_timestamps[user_id]
            
            if not await is_user_in_server(cur, user_id, server_id):
                await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'User is not in server'}, to=sid)
                return
            
            if not await does_user_have_server_permission(cur, user_id, server_id, 'send_messages'):
                await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'User does not have permission to send messages'}, to=sid)
                return
            
            while timestamps and (now - timestamps[0]).total_seconds() > TIME_WINDOW_SECONDS:
                timestamps.popleft()

            if len(timestamps) >= MAX_MESSAGES:
                await sio_instance.sio.emit('send_message_response', {
                    'success': False,
                    'error': f'Rate limit exceeded. Max {MAX_MESSAGES} messages every {TIME_WINDOW_SECONDS} seconds.'
                }, to=sid)
                return

            timestamps.append(now)

            if not user_id:
                await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'Invalid or expired token'}, to=sid)
                return

            is_premium = await get_user_premium_status(cur, user_id)
            print(is_premium)
            limit = 10000 if is_premium else 3000
            if len(message) > limit:
                await sio_instance.sio.emit('send_message_response', {
                    'success': False,
                    'error': f'Message too long. Limit is {limit} characters.'
                }, to=sid)
                return

            await cur.execute('SELECT username, profile_picture FROM users WHERE id = %s', (user_id,))
            user_row = await cur.fetchone()
            if not user_row:
                await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'User not found'}, to=sid)
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
                INSERT INTO messages 
                (id, message, sent_by, created_at, updated_at, edited, server_id, channel_id, parent_message_id, assets)
                VALUES (%s, %s, %s, %s, NULL, FALSE, %s, %s, %s, %s)
                ''',
                (message_id, message, user_id, timestamp, server_id, channel_id, parent_message_id, assets_json)
            )
            print(f"[send_message] Message inserted: id={message_id} user={username} message={message} server={server_id} channel={channel_id} parent_message_id={parent_message_id} assets={assets_json} at {timestamp}")

            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)
    
    if isinstance(timestamp, str):
        timestamp = datetime.fromisoformat(timestamp)

    await sio_instance.sio.emit('new_message', {
        'id': message_id,
        'bot_message': 0,
        'username': username,
        'message': message,
        'created_at': timestamp.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'server_id': server_id,
        'channel_id': channel_id,
        'sent_by': user_id,
        'parent_message_id': parent_message_id,
        'profile_picture': profile_picture,
        'assets': json.loads(assets_json) if assets_json else []
    }, to=server_channel_sids)
    
    send_server_notifications(cur, server_id, channel_id)
    
    await sio_instance.sio.emit('send_message_response', {'success': True}, to=sid)
    print("[send_message] send_message_response sent")
    
async def get_messages(sid, data):
    print(f"[get_messages] Received from {sid}: {data}")
    access_token = data.get('access_token')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    offset = data.get('offset', 0)

    if not all([access_token, server_id, channel_id]):
        print("[get_messages] Missing access_token, server_id or channel_id")
        await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
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
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'Invalid or expired token'}, to=sid)
                return

            if not await is_user_in_server(cur, user_id, server_id):
                await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'User is not in server'}, to=sid)
                return

            if not await does_user_have_server_permission(cur, user_id, server_id, 'view_channels'):
                await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'User does not have permission to view channels'}, to=sid)
                return

            can_read_history = await does_user_have_server_permission(cur, user_id, server_id, 'read_message_history')

            await cur.execute(
                "SELECT joined_at FROM server_members WHERE server_id = %s AND user_id = %s",
                (server_id, user_id)
            )
            result = await cur.fetchone()

            if not result:
                await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'Server not found'}, to=sid)
                return

            joined_at = None
            if not can_read_history:
                joined_at_value = result.get('joined_at')
                if joined_at_value:
                    if isinstance(joined_at_value, str):
                        try:
                            joined_at = datetime.strptime(joined_at_value, '%Y-%m-%d %H:%M:%S')
                        except Exception:
                            joined_at = None
                    else:
                        joined_at = joined_at_value


            if joined_at and not can_read_history:
                joined_at_filter_msg = " AND m.created_at >= %s"
                joined_at_filter_bot = " AND bm.created_at >= %s"
                joined_at_filter_count_msg = " AND created_at >= %s"
                joined_at_filter_count_bot = " AND created_at >= %s"
                params = [server_id, channel_id, joined_at, server_id, channel_id, joined_at]
            else:
                joined_at_filter_msg = ""
                joined_at_filter_bot = ""
                joined_at_filter_count_msg = ""
                joined_at_filter_count_bot = ""
                params = [server_id, channel_id, server_id, channel_id]

            count_query = f"""
                SELECT (
                    (SELECT COUNT(*) FROM messages WHERE server_id = %s AND channel_id = %s {joined_at_filter_count_msg})
                    +
                    (SELECT COUNT(*) FROM bot_messages WHERE server_id = %s AND channel_id = %s {joined_at_filter_count_bot})
                ) AS total
            """
            await cur.execute(count_query, params)
            total_count = (await cur.fetchone())['total']

            messages_query = f"""
                SELECT * FROM (
                    SELECT m.id, m.message, m.sent_by, u.username, m.created_at, m.updated_at,
                        m.edited, m.server_id, m.channel_id, m.parent_message_id, m.assets,
                        u.profile_picture, NULL AS command, NULL AS command_user_id, NULL as embed,
                        FALSE AS bot_message
                    FROM messages m
                    JOIN users u ON m.sent_by = u.id
                    WHERE m.server_id = %s AND m.channel_id = %s {joined_at_filter_msg}

                    UNION ALL

                    SELECT bm.id, bm.message, NULL AS sent_by, b.name AS username, bm.created_at, bm.updated_at,
                        bm.edited, bm.server_id, bm.channel_id, NULL AS parent_message_id, NULL AS assets,
                        b.profile_picture, bm.command, bm.command_user_id, bm.embed,
                        TRUE AS bot_message
                    FROM bot_messages bm
                    LEFT JOIN bots b ON bm.bot_id = b.id
                    WHERE bm.server_id = %s AND bm.channel_id = %s {joined_at_filter_bot}
                ) AS combined_messages
                ORDER BY created_at DESC
                LIMIT %s OFFSET %s
            """

            params.extend([limit, offset])
            await cur.execute(messages_query, params)
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
                
            users = await get_server_users_info(cur, server_id)
                
    await sio_instance.sio.emit('all_messages', messages, to=sid)
    await sio_instance.sio.emit("server_users", users, to=sid)
    await sio_instance.sio.emit('get_messages_response', {'success': True, 'count': len(messages), 'total': total_count, 'offset': offset}, to=sid)
    print("[get_messages] all_messages and get_messages_response sent")
    

async def get_message_by_id(sid, data):
    access_token = data.get('access_token')
    message_id = data.get('message_id')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')

    if access_token is None or message_id is None or server_id is None or channel_id is None:
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                return

            if not await is_user_in_server(cur, user_id, server_id):
                await sio_instance.sio.emit('message_by_id', None, to=sid)
                return

            await cur.execute(
                "SELECT id, sent_by, message, created_at FROM messages WHERE id = %s AND server_id = %s AND channel_id = %s",
                (message_id, server_id, channel_id)
            )
            message = await cur.fetchone()

            if not message:
                await cur.execute(
                    "SELECT id, bot_id, message, created_at FROM bot_messages WHERE id = %s AND server_id = %s AND channel_id = %s",
                    (message_id, server_id, channel_id)
                )
                bot_message = await cur.fetchone()
                if not bot_message:
                    await sio_instance.sio.emit('message_by_id', None, to=sid)
                    return
                
                message = {
                    'id': bot_message['id'],
                    'sent_by': bot_message['bot_id'],
                    'message': bot_message['message'],
                    'created_at': bot_message['created_at'],
                }

            if isinstance(message.get('created_at'), datetime):
                message['created_at'] = message['created_at'].isoformat()

            await sio_instance.sio.emit('message_by_id', message, to=sid)
            

async def delete_message(sid, data):
    access_token = data.get('access_token')
    message_id = data.get('message_id')

    if access_token is None or message_id is None:
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                return
            
            await cur.execute("SELECT server_id, channel_id FROM messages WHERE id = %s AND sent_by = %s", (message_id, user_id))
            message = await cur.fetchone()
            if not message:
                return

            server_id = message['server_id']
            channel_id = message['channel_id']

            result = await cur.execute("DELETE FROM messages WHERE id = %s AND sent_by = %s", (message_id, user_id))
            if result == 0:
                return

            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)

            await conn.commit()
            await sio_instance.sio.emit('message_deleted', message_id, to=server_channel_sids)