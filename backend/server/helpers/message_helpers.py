from datetime import datetime, timezone
import json
from server.config import message_timestamps, MAX_MESSAGES, TIME_WINDOW_SECONDS, get_cached_messages, cache_message, get_cached_users, cache_users, delete_cached_message
from server.helpers.user_helpers import get_user_premium_status, auth_required
from server.helpers.server_helpers import server_permissions, send_server_notifications, get_server_users_info
import aiomysql
import uuid
import server.sio_instance as sio_instance
import server.config as config
from server.helpers.logs import addMessageToLogs
from server.helpers.other_helpers import addKudos
import asyncio

@auth_required(server_required=True, allow_bots=True)
async def send_message(sid, metadata, data):
    message = data.get('message')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    parent_message_id = data.get('parent_message_id')
    file_names = data.get('file_names')

    embed = data.get('embed')
    req_id = data.get('req_id')
    command = data.get('command')
    user_id = data.get('user_id')

    is_bot = metadata.get('is_bot', False)
    account_id = metadata.get('account_id')

    if not all([message, server_id, channel_id]):
        await addMessageToLogs(f"Missing required fields for send_message", "INFO")
        await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
        return

    message_id = str(uuid.uuid4())

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            now = datetime.now(timezone.utc)
            timestamps = message_timestamps[account_id]
            
            if not is_bot:
                if not await server_permissions(cur, account_id, server_id, 'send_messages'):
                    await addMessageToLogs(f"User does not have permission to send messages for send_message for user id: {account_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'User does not have permission to send messages'}, to=sid)
                    return
            
            while timestamps and (now - timestamps[0]).total_seconds() > TIME_WINDOW_SECONDS:
                timestamps.popleft()

            if len(timestamps) >= MAX_MESSAGES:
                await addMessageToLogs(f"Rate limit exceeded for send_message for user id: {account_id}", "INFO")
                await sio_instance.sio.emit('send_message_response', {
                    'success': False,
                    'error': f'Rate limit exceeded. Max {MAX_MESSAGES} messages every {TIME_WINDOW_SECONDS} seconds.',
                    'req_id': req_id
                }, to=sid)
                return

            timestamps.append(now)

            is_premium = False
            if not is_bot:
                is_premium = await get_user_premium_status(cur, account_id)
            
            limit = 10000 if is_premium else 3000
            if len(message) > limit:
                await addMessageToLogs(f"Message too long for send_message for user id: {account_id}", "INFO")
                await sio_instance.sio.emit('send_message_response', {
                    'success': False,
                    'error': f'Message too long. Limit is {limit} characters.',
                    'req_id': req_id
                }, to=sid)
                return

            if is_bot:
                await cur.execute('SELECT name, profile_picture FROM bots WHERE id = %s', (account_id,))
                row = await cur.fetchone()
                if not row:
                    await addMessageToLogs(f"Bot not found for send_message for bot id: {account_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'Bot not found', 'req_id': req_id}, to=sid)
                    return
            else:
                await cur.execute('SELECT username, nickname, profile_picture, staff FROM users WHERE id = %s', (account_id,))
                row = await cur.fetchone()
                if not row:
                    await addMessageToLogs(f"User not found for send_message for user id: {account_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'User not found'}, to=sid)
                    return

            username = is_bot and row.get('name') or row.get('username')
            display_name = is_bot and row.get('name') or row.get('nickname')
            is_staff = is_bot and False or row.get('staff')
            profile_picture = row['profile_picture']
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

            if is_bot:
                embed_str = json.dumps(embed) if embed is not None else None

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
                
            if parent_message_id:
                await cur.execute('SELECT sent_by, message FROM messages WHERE id = %s', (parent_message_id,))
                parent_message = await cur.fetchone()
                if not parent_message:
                    await addMessageToLogs(f"Parent message not found for send_message for parent message id: {parent_message_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'Parent message not found', 'req_id': req_id}, to=sid)
                    return

                parent_user_id = parent_message.get('sent_by')
                parent_msg = parent_message.get('message')
                                
                if parent_user_id:
                    await cur.execute('SELECT username FROM users WHERE id = %s', (parent_user_id,))
                    parent_user = await cur.fetchone()
                    if not parent_user:
                        await addMessageToLogs(f"Parent user not found for send_message for parent user id: {parent_user_id}", "INFO")
                        await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'Parent user not found', 'req_id': req_id}, to=sid)
                        return

                    parent_username = parent_user.get('username')

            if is_bot:
                await cur.execute(
                    '''
                    INSERT INTO bot_messages 
                    (id, message, bot_id, created_at, updated_at, edited, server_id, channel_id, command, command_user_id, embed, assets, parent_message_id)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ''',
                    (message_id, message, account_id, timestamp, None, False, server_id, channel_id, command, user_id, embed_str, assets_json, parent_message_id)
                )
                await addMessageToLogs(f"Inserted bot message for bot id: {account_id}", "INFO")
            else:
                await cur.execute(
                    '''
                    INSERT INTO messages 
                    (id, message, sent_by, created_at, updated_at, edited, server_id, channel_id, parent_message_id, assets)
                    VALUES (%s, %s, %s, %s, NULL, FALSE, %s, %s, %s, %s)
                    ''',
                    (message_id, message, account_id, timestamp, server_id, channel_id, parent_message_id, assets_json)
                )
                await addMessageToLogs(f"Inserted message for user id: {account_id}", "INFO")
                
                await addKudos(cur, account_id, 1, message, server_id, channel_id)

            await conn.commit()
                
    if isinstance(timestamp, str):
        timestamp = datetime.fromisoformat(timestamp)
        
    message_response = {
        'id': message_id,
        'bot_message': is_bot and 1 or 0,
        'message': message,
        'created_at': timestamp.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'server_id': server_id,
        'channel_id': channel_id,
        'sent_by': (not is_bot) and account_id or None,
        'assets': json.loads(assets_json) if assets_json else [],
        'command': command,
        'command_user_id': user_id,
        'embed': embed,
        'req_id': req_id,
        'sender_info': {
            'username': username,
            'display_name': display_name,
            'profile_picture': profile_picture,
            'staff': is_staff,
            'premium': is_premium
        },
        'parent_message_info': {
            'user_id': parent_user_id,
            'message_id': parent_message_id,
            'message_preview': (parent_msg[:100] + '...') if len(parent_msg) > 100 else parent_msg,
            'username': parent_username
        }
    }
    await cache_message(server_id, channel_id, message_response)

    await sio_instance.sio.emit('new_message', message_response, room=f"server:{server_id}:channel:{channel_id}")
    await addMessageToLogs(f"new_message emitted for server_id: {server_id} and channel_id: {channel_id}", "INFO")
    
    # await send_server_notifications(cur, server_id, channel_id)
    
    await sio_instance.sio.emit('send_message_response', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=sid)
    await addMessageToLogs(f"send_message_response emitted for sid: {sid}", "INFO")
    
@auth_required(server_required=True, allow_bots=False)
async def get_messages(sid, metadata, data):
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    offset = data.get('offset', 0)
    user_id = metadata.get('account_id')

    if not all([server_id, channel_id]):
        await addMessageToLogs(f"Missing required fields for get_messages", "INFO")
        await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return

    try:
        offset = int(offset)
        if offset < 0:
            offset = 0
    except Exception:
        offset = 0

    limit = 25
    
    cached_messages = await get_cached_messages(server_id, channel_id, offset, limit)
    if cached_messages:
        await sio_instance.sio.emit('all_messages', cached_messages, to=sid)
    
    async def send_db():
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                if not await server_permissions(cur, user_id, server_id, 'view_channels'):
                    await addMessageToLogs(f"User does not have permission to view channels for get_messages, user id: {user_id}, server id: {server_id}", "INFO")
                    await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'User does not have permission to view channels'}, to=sid)
                    return

                can_read_history = await server_permissions(cur, user_id, server_id, 'read_message_history')

                await cur.execute(
                    "SELECT joined_at FROM server_members WHERE server_id = %s AND user_id = %s",
                    (server_id, user_id)
                )
                result = await cur.fetchone()

                if not result:
                    await addMessageToLogs(f"Server not found for get_messages, server id: {server_id}", "INFO")
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
                        SELECT m.id, m.message, m.sent_by, u.username, m.created_at, m.updated_at, u.nickname AS display_name,
                            m.edited, m.server_id, m.channel_id, m.parent_message_id, m.assets,
                            u.profile_picture, NULL AS command, NULL AS command_user_id, NULL as embed, u.is_staff AS staff,
                            FALSE AS bot_message
                        FROM messages m
                        JOIN users u ON m.sent_by = u.id
                        WHERE m.server_id = %s AND m.channel_id = %s {joined_at_filter_msg}

                        UNION ALL

                        SELECT bm.id, bm.message, bm.bot_id AS sent_by, b.name AS username, bm.created_at, bm.updated_at, null AS display_name,
                            bm.edited, bm.server_id, bm.channel_id, NULL AS parent_message_id, NULL AS assets,
                            b.profile_picture, bm.command, bm.command_user_id, bm.embed, FALSE AS staff,
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

                    premium = False
                    if not msg['bot_message']:
                        premium = await get_user_premium_status(cur, msg['sent_by'])

                    msg['sender_info'] = {
                        'username': msg.pop('username', None),
                        'display_name': msg.pop('display_name', None),
                        'profile_picture': msg.pop('profile_picture', None),
                        'staff': msg.pop('staff', None),
                        'premium': premium
                    }

                    parent_id = msg.pop('parent_message_id', None)
                    parent_info = None
                    if parent_id:
                        await cur.execute("""
                            SELECT m.id, m.message, m.sent_by, u.username
                            FROM messages m
                            JOIN users u ON m.sent_by = u.id
                            WHERE m.id = %s
                            LIMIT 1
                        """, (parent_id,))
                        parent_msg = await cur.fetchone()
                        if parent_msg:
                            parent_info = {
                                'user_id': parent_msg['sent_by'],
                                'message_id': parent_msg['id'],
                                'message_preview': (parent_msg['message'][:100] + '...') if len(parent_msg['message']) > 100 else parent_msg['message'],
                                'username': parent_msg['username']
                            }

                    msg['parent_message_info'] = parent_info

        await sio_instance.sio.emit('all_messages_nocache', messages, to=sid)
        await addMessageToLogs(f"Emitted all_messages to {user_id}, Sent {len(messages)} messages to {user_id}", "INFO")
        
        await sio_instance.sio.emit('get_messages_response', {'success': True, 'count': len(messages), 'total': total_count, 'offset': offset}, to=sid)
        await addMessageToLogs(f"get_messages_response emitted to {user_id}", "INFO")
        
    asyncio.create_task(send_db())
    await sio_instance.sio.emit('get_messages_response', {'success': True, 'offset': offset, 'count': len(cached_messages)}, to=sid)
    
@auth_required(server_required=True, allow_bots=False)
async def get_message_by_id(sid, metadata, data):
    message_id = data.get('message_id')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')

    user_id = metadata.get('account_id')

    if message_id is None or server_id is None or channel_id is None:
        await addMessageToLogs(f"Missing required fields for get_message_by_id", "INFO")
        await sio_instance.sio.emit('message_by_id', None, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
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
            await addMessageToLogs(f"Emitted message_by_id to {user_id}", "INFO")
            
@auth_required(server_required=True, allow_bots=True)
async def delete_message(sid, metadata, data):
    message_id = data.get('message_id')
    req_id = data.get('req_id')

    is_bot = metadata.get('is_bot')
    account_id = metadata.get('account_id')

    if message_id is None:
        await sio_instance.sio.emit('message_deleted', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
        await addMessageToLogs(f"Missing required fields for delete_message", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if is_bot:
                await cur.execute("SELECT server_id, channel_id FROM bot_messages WHERE id = %s AND bot_id = %s", (message_id, account_id))
                message = await cur.fetchone()
            else:
                await cur.execute("SELECT server_id, channel_id FROM messages WHERE id = %s AND sent_by = %s", (message_id, account_id))
                message = await cur.fetchone()

            if not message:
                await sio_instance.sio.emit('message_deleted', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=sid)
                await addMessageToLogs(f"Message not found for delete_message, message id: {message_id}, user id: {account_id}", "INFO")
                return

            server_id = message['server_id']
            channel_id = message['channel_id']

            if is_bot:
                result = await cur.execute('DELETE FROM bot_messages WHERE id = %s AND bot_id = %s', (message_id, account_id))
            else:
                result = await cur.execute("DELETE FROM messages WHERE id = %s AND sent_by = %s", (message_id, account_id))
            
            if result == 0:
                await sio_instance.sio.emit('message_deleted', {'success': False, 'error': 'Message couldn\'t be deleted', 'req_id': req_id}, to=sid)
                await addMessageToLogs(f"Message couldn't be deleted for delete_message, message id: {message_id}, user id: {account_id}", "INFO")
                return

            await delete_cached_message(server_id, channel_id, message_id)
            await conn.commit()
            await sio_instance.sio.emit('message_deleted', {'success': True, 'message_id': message_id, 'req_id': req_id}, room=f'server:{server_id}:channel:{channel_id}')
            await addMessageToLogs(f"Emitted message_deleted to {server_id}", "INFO")