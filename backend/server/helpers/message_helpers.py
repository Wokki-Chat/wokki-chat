from datetime import datetime, timezone
import json
from server.config import user_message_timestamps, bot_message_timestamps, MAX_MESSAGES, TIME_WINDOW_SECONDS
from server.helpers.user_helpers import get_user_premium_status, auth_required
from server.helpers.server_helpers import does_user_have_server_permission, send_server_notifications, get_server_channel_sids, get_server_users_info
import aiomysql
import uuid
import server.sio_instance as sio_instance
import server.config as config
from server.helpers.logs import addMessageToLogs
from server.helpers.other_helpers import addKudos

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
            if is_bot:
                timestamps = bot_message_timestamps[account_id]
            else:
                timestamps = user_message_timestamps[account_id]
            
            if not is_bot:
                if not await does_user_have_server_permission(cur, account_id, server_id, 'send_messages'):
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
                await cur.execute('SELECT username, profile_picture FROM users WHERE id = %s', (account_id,))
                row = await cur.fetchone()
                if not row:
                    await addMessageToLogs(f"User not found for send_message for user id: {account_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {'success': False, 'error': 'User not found'}, to=sid)
                    return

            username = is_bot and row.get('name') or row.get('username')
            profile_picture = row['profile_picture']
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

            if is_bot: # perhaps add feature for users to have embeds too? would be a funny idea for selfbots (wokkicord 🤑)
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

            if is_bot:
                await cur.execute(
                    '''
                    INSERT INTO bot_messages 
                    (id, message, bot_id, created_at, updated_at, edited, server_id, channel_id, command, command_user_id, embed, parent_message_id, assets)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ''',
                    (message_id, message, account_id, timestamp, None, False, server_id, channel_id, command, user_id, embed_str, parent_message_id, assets_json)
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

            await conn.commit() # idk if this is necessary, it exists in bot_message_helpers
            
            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)
    
    if isinstance(timestamp, str):
        timestamp = datetime.fromisoformat(timestamp)

    await sio_instance.sio.emit('new_message', {
        'id': message_id,
        'bot_message': is_bot and 1 or 0,
        'username': username,
        'message': message,
        'created_at': timestamp.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'server_id': server_id,
        'channel_id': channel_id,
        'sent_by': (not is_bot) and account_id or None,
        'parent_message_id': parent_message_id,
        'profile_picture': profile_picture,
        'assets': json.loads(assets_json) if assets_json else [],
        'command': command,
        'command_user_id': user_id,
        'embed': embed
    }, to=server_channel_sids)
    await addMessageToLogs(f"new_message emitted for server_id: {server_id} and channel_id: {channel_id}", "INFO")
    
    send_server_notifications(cur, server_id, channel_id)
    
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

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if not await does_user_have_server_permission(cur, user_id, server_id, 'view_channels'):
                await addMessageToLogs(f"User does not have permission to view channels for get_messages, user id: {user_id}, server id: {server_id}", "INFO")
                await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'User does not have permission to view channels'}, to=sid)
                return

            can_read_history = await does_user_have_server_permission(cur, user_id, server_id, 'read_message_history')

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
    await addMessageToLogs(f"Emitted all_messages to {user_id}, Sent {len(messages)} messages to {user_id}", "INFO")
    
    await sio_instance.sio.emit("server_users", users, to=sid)
    await addMessageToLogs(f"Emitted server_users to {user_id}, Sent {len(users)} users to {user_id}", "INFO")
    
    await sio_instance.sio.emit('get_messages_response', {'success': True, 'count': len(messages), 'total': total_count, 'offset': offset}, to=sid)
    await addMessageToLogs(f"get_messages_response emitted to {user_id}", "INFO")
    
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
        await sio_instance.sio.emit('message_deleted', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=server_channel_sids)
        await addMessageToLogs(f"Missing required fields for delete_message", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if is_bot:
                await cur.execute("SELECT server_id, channel_id FROM messages WHERE id = %s AND sent_by = %s", (message_id, account_id))
                message = await cur.fetchone()
            else:
                await cur.execute("SELECT server_id, channel_id FROM bot_messages WHERE id = %s AND bot_id = %s", (message_id, account_id))
                message = await cur.fetchone()

            if not message:
                await sio_instance.sio.emit('message_deleted', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=server_channel_sids)
                await addMessageToLogs(f"Message not found for delete_message, message id: {message_id}, user id: {account_id}", "INFO")
                return

            server_id = message['server_id']
            channel_id = message['channel_id']

            if is_bot:
                result = await cur.execute('DELETE FROM bot_messages WHERE id = %s AND bot_id = %s', (message_id, account_id))
            else:
                result = await cur.execute("DELETE FROM messages WHERE id = %s AND sent_by = %s", (message_id, account_id))
            
            if result == 0:
                await sio_instance.sio.emit('message_deleted', {'success': False, 'error': 'Message couldn\'t be deleted', 'req_id': req_id}, to=server_channel_sids)
                await addMessageToLogs(f"Message couldn't be deleted for delete_message, message id: {message_id}, user id: {account_id}", "INFO")
                return

            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)

            await conn.commit()
            await sio_instance.sio.emit('message_deleted', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=server_channel_sids)
            await addMessageToLogs(f"Emitted message_deleted to {server_id}", "INFO")