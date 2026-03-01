from datetime import datetime, timezone
import json
from server.config import message_timestamps, MAX_MESSAGES, TIME_WINDOW_SECONDS, get_cached_messages, cache_message, update_cached_messages, delete_cached_message, get_command_id
from server.helpers.user_helpers import get_user_premium_status, auth_required
from server.helpers.server_helpers import server_permissions, is_user_in_server, is_bot_in_server
from server.helpers.friend_helpers import inContact
import aiomysql
import uuid
import server.sio_instance as sio_instance
import server.config as config
from server.helpers.logs import addMessageToLogs
from server.helpers.other_helpers import addKudos
import asyncio
from server.helpers.bot_helpers import validate_embed
from dateutil import parser

@auth_required(server_required=True, allow_bots=True)
async def send_message(sid, metadata, data):
    message = data.get('message')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    contact_id = data.get('contact_id')
    parent_message_id = data.get('parent_message_id')
    file_names = data.get('file_names')

    embed = data.get('embed')
    req_id = data.get('req_id')
    command_id = data.get('command_id')

    is_bot = metadata.get('is_bot', False)
    account_id = metadata.get('account_id')

    is_contact = bool(contact_id)
    is_channel = bool(server_id and channel_id)

    if not (is_contact or is_channel) or (is_contact and is_channel):
        await addMessageToLogs(f"Invalid message target for send_message", "INFO")
        await sio_instance.sio.emit('send_message_response', {
            'success': False, 
            'error': 'Must specify either contact_id OR (server_id AND channel_id)', 
            'req_id': req_id
        }, to=sid)
        return

    if not message:
        await addMessageToLogs(f"Missing message content for send_message", "INFO")
        await sio_instance.sio.emit('send_message_response', {
            'success': False, 
            'error': 'Missing required field: message', 
            'req_id': req_id
        }, to=sid)
        return

    message_id = str(uuid.uuid4())

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            now = datetime.now(timezone.utc)
            timestamps = message_timestamps[account_id]
            
            if not is_bot:
                if is_contact:
                    if not await inContact(cur, account_id, contact_id):
                        await addMessageToLogs(f"User is not in contact for send_message, user id: {account_id}, contact id: {contact_id}", "INFO")
                        await sio_instance.sio.emit('send_message_response', {
                            'success': False, 
                            'error': 'User is not in contact', 
                            'req_id': req_id
                        }, to=sid)
                        return
                else:
                    if not await server_permissions(cur, account_id, server_id, 'send_messages'):
                        await addMessageToLogs(f"User does not have permission to send messages for send_message for user id: {account_id}", "INFO")
                        await sio_instance.sio.emit('send_message_response', {
                            'success': False, 
                            'error': 'User does not have permission to send messages', 
                            'req_id': req_id
                        }, to=sid)
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
                    await sio_instance.sio.emit('send_message_response', {
                        'success': False, 
                        'error': 'Bot not found', 
                        'req_id': req_id
                    }, to=sid)
                    return
            else:
                await cur.execute('SELECT username, nickname, profile_picture, is_staff FROM users WHERE id = %s', (account_id,))
                row = await cur.fetchone()
                if not row:
                    await addMessageToLogs(f"User not found for send_message for user id: {account_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {
                        'success': False, 
                        'error': 'User not found', 
                        'req_id': req_id
                    }, to=sid)
                    return

            username = is_bot and row.get('name') or row.get('username')
            display_name = is_bot and row.get('name') or row.get('nickname')
            is_staff = is_bot and False or row.get('is_staff')
            profile_picture = row['profile_picture']
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
            
            command_data = None
            if command_id is not None:
                command_data = await get_command_id(command_id)

            command_user_id = command_data.get('user_id') if command_data else None
            command = command_data.get('command') if command_data else None

            if command_user_id:
                await cur.execute('SELECT username FROM users WHERE id = %s', (command_user_id,))
                row = await cur.fetchone()
                if row:
                    command_username = row.get('username')
                else:
                    command_username = 'Unknown User'
            else:
                command_username = 'Unknown User'

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
                
            parent_username = None
            parent_user_id = None
            parent_msg = None
            
            if parent_message_id:
                await cur.execute('SELECT sent_by, sent_by_bot, message FROM messages WHERE id = %s', (parent_message_id,))
                parent_message = await cur.fetchone()
                if not parent_message:
                    await addMessageToLogs(f"Parent message not found for send_message for parent message id: {parent_message_id}", "INFO")
                    await sio_instance.sio.emit('send_message_response', {
                        'success': False, 
                        'error': 'Parent message not found', 
                        'req_id': req_id
                    }, to=sid)
                    return

                parent_msg = parent_message.get('message')

                if parent_message.get('sent_by'):
                    parent_user_id = parent_message.get('sent_by')
                    await cur.execute('SELECT username FROM users WHERE id = %s', (parent_user_id,))
                    parent_user = await cur.fetchone()
                    if not parent_user:
                        await addMessageToLogs(f"Parent user not found for send_message for parent user id: {parent_user_id}", "INFO")
                        await sio_instance.sio.emit('send_message_response', {
                            'success': False, 
                            'error': 'Parent user not found', 
                            'req_id': req_id
                        }, to=sid)
                        return
                    parent_username = parent_user.get('username')
                elif parent_message.get('sent_by_bot'):
                    parent_user_id = parent_message.get('sent_by_bot')
                    await cur.execute('SELECT name FROM bots WHERE id = %s', (parent_user_id,))
                    parent_bot = await cur.fetchone()
                    if not parent_bot:
                        await addMessageToLogs(f"Parent bot not found for send_message for parent bot id: {parent_user_id}", "INFO")
                        await sio_instance.sio.emit('send_message_response', {
                            'success': False, 
                            'error': 'Parent bot not found', 
                            'req_id': req_id
                        }, to=sid)
                        return
                    parent_username = parent_bot.get('name')

            if is_bot:
                sent_by = None
                sent_by_bot = account_id
            else:
                sent_by = account_id
                sent_by_bot = None

            await cur.execute(
                '''
                INSERT INTO messages 
                (id, message, sent_by, sent_by_bot, created_at, updated_at, edited, server_id, channel_id, contact_id, command, command_user_id, embed, assets, parent_message_id)
                VALUES (%s, %s, %s, %s, %s, NULL, FALSE, %s, %s, %s, %s, %s, %s, %s, %s)
                ''',
                (
                    message_id, 
                    message, 
                    sent_by, 
                    sent_by_bot, 
                    timestamp, 
                    server_id if is_channel else None, 
                    channel_id if is_channel else None, 
                    contact_id if is_contact else None,
                    command if is_bot else None,
                    command_user_id if is_bot else None, 
                    embed_str if is_bot else None, 
                    assets_json, 
                    parent_message_id
                )
            )
            
            if is_contact:
                await cur.execute(
                    'UPDATE contacts SET last_message_sent = %s WHERE contact_id = %s',
                    (timestamp, contact_id)
                )
                await addMessageToLogs(f"Updated last_message_sent for contact_id: {contact_id}", "INFO")

            if is_bot:
                await addMessageToLogs(f"Inserted bot message for bot id: {account_id}", "INFO")
            else:
                await addMessageToLogs(f"Inserted message for user id: {account_id}", "INFO")
                if is_channel:
                    await addKudos(cur, account_id, 1, message, server_id, channel_id)

            await conn.commit()
                
    if isinstance(timestamp, str):
        timestamp = datetime.fromisoformat(timestamp)
 
    message_response = {
        'id': message_id,
        'bot_message': 1 if is_bot else 0,
        'message': message,
        'created_at': timestamp.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'server_id': server_id if is_channel else None,
        'channel_id': channel_id if is_channel else None,
        'contact_id': contact_id if is_contact else None,
        'sent_by': account_id if not is_bot else None,
        'sent_by_bot': account_id if is_bot else None,
        'assets': json.loads(assets_json) if assets_json else [],
        'embed': embed,
        'req_id': req_id,
        'sender_info': {
            'username': username,
            'display_name': display_name,
            'profile_picture': profile_picture,
            'staff': is_staff,
            'premium': is_premium
        },
        'parent_message_info': None,
        'command_info': None
    }

    if parent_message_id and parent_username:
        message_response['parent_message_info'] = {
            'user_id': parent_user_id,
            'message_id': parent_message_id,
            'message_preview': (parent_msg[:100] + '...') if len(parent_msg) > 100 else parent_msg,
            'username': parent_username
        }
    
    if command_data and command:
        message_response['command_info'] = {
            'command': command,
            'username': command_username
        }

    if is_contact:
        await cache_message(contact_id=contact_id, message=message_response)
    else:
        await cache_message(server_id=server_id, channel_id=channel_id, message=message_response)

    if is_contact:
        await sio_instance.sio.emit('new_message', message_response, room=f"contact:{contact_id}")
        await addMessageToLogs(f"new_message emitted for contact_id: {contact_id}", "INFO")
        
        await sio_instance.sio.emit('new_message_notification', {
            'contact_id': contact_id,
            'message_id': message_id,
            'message': message,
            'sender_info': message_response['sender_info']
        }, room=f"contact_notif:{contact_id}", skip_sid=sid)
    else:
        await sio_instance.sio.emit('new_message', message_response, room=f"server:{server_id}:channel:{channel_id}")
        await addMessageToLogs(f"new_message emitted for server_id: {server_id} and channel_id: {channel_id}", "INFO")
        
        await sio_instance.sio.emit('new_message_notification', {
            'server_id': server_id,
            'channel_id': channel_id,
            'message_id': message_id,
            'message': message,
            'sender_info': message_response['sender_info']
        }, room=f"server_notif:{server_id}", skip_sid=sid)
    
    await sio_instance.sio.emit('send_message_response', {
        'success': True, 
        'message_id': message_id, 
        'req_id': req_id
    }, to=sid)
    await addMessageToLogs(f"send_message_response emitted for sid: {sid}", "INFO")

@auth_required(server_required=True, allow_bots=False)
async def get_messages(sid, metadata, data):
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    contact_id = data.get('contact_id')
    offset = data.get('offset', 0)
    user_id = metadata.get('account_id')

    is_contact = bool(contact_id)
    is_channel = bool(server_id and channel_id)

    if not (is_contact or is_channel):
        await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return

    try:
        offset = int(offset)
        if offset < 0:
            offset = 0
    except Exception:
        offset = 0

    limit = 25

    if is_contact:
        cached_messages = await get_cached_messages(contact_id=contact_id, offset=offset, limit=limit)
    else:
        cached_messages = await get_cached_messages(server_id=server_id, channel_id=channel_id, offset=offset, limit=limit)

    if cached_messages:
        await sio_instance.sio.emit('all_messages', cached_messages, to=sid)

    async def send_db():
        try:
            async with config.pool.acquire() as conn:
                async with conn.cursor(aiomysql.DictCursor) as cur:

                    joined_at = None
                    can_read_history = True

                    if is_contact:
                        if not await inContact(cur, user_id, contact_id):
                            await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'User is not in contact'}, to=sid)
                            return

                    else:
                        await cur.execute(
                            "SELECT created_by FROM servers WHERE id = %s AND created_by = %s AND server_type = 'normal'",
                            (server_id, user_id)
                        )
                        is_owner = bool(await cur.fetchone())

                        if not is_owner:
                            await cur.execute("""
                                SELECT rp.*
                                FROM user_server_roles usr
                                JOIN role_permissions rp ON usr.role_id = rp.role_id
                                WHERE usr.server_id = %s AND usr.user_id = %s
                            """, (server_id, user_id))
                            role_rows = await cur.fetchall()

                            def _has_perm(perm):
                                for row in role_rows:
                                    if row.get(perm):
                                        return True
                                return False

                            if not _has_perm('view_channels'):
                                await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'User does not have permission to view channels'}, to=sid)
                                return

                            can_read_history = _has_perm('read_message_history')

                        await cur.execute(
                            "SELECT joined_at FROM server_members WHERE server_id = %s AND user_id = %s",
                            (server_id, user_id)
                        )
                        member_row = await cur.fetchone()
                        if not member_row:
                            await sio_instance.sio.emit('get_messages_response', {'success': False, 'error': 'Server not found'}, to=sid)
                            return

                        if not can_read_history:
                            joined_at_value = member_row.get('joined_at')
                            if joined_at_value:
                                if isinstance(joined_at_value, str):
                                    try:
                                        joined_at = datetime.strptime(joined_at_value, '%Y-%m-%d %H:%M:%S')
                                    except Exception:
                                        joined_at = None
                                else:
                                    joined_at = joined_at_value

                    if is_contact:
                        where_clause = "m.contact_id = %s"
                        where_params = [contact_id]
                    else:
                        where_clause = "m.server_id = %s AND m.channel_id = %s"
                        where_params = [server_id, channel_id]

                    if joined_at and not can_read_history:
                        where_clause += " AND m.created_at >= %s"
                        where_params.append(joined_at)

                    async def fetch_count():
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(
                                    f"SELECT COUNT(*) AS total FROM messages m WHERE {where_clause}",
                                    where_params
                                )
                                return (await cur.fetchone())['total']

                    async def fetch_messages():
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(f"""
                                    SELECT
                                        m.id, m.message, m.sent_by, m.sent_by_bot, m.created_at, m.updated_at, m.edited,
                                        m.server_id, m.channel_id, m.contact_id,
                                        m.parent_message_id, m.assets, m.command, m.command_user_id, m.embed,
                                        u.username, u.nickname AS display_name, u.profile_picture,
                                        u.is_staff AS staff, u.premium, u.premium_expires_at,
                                        (m.sent_by_bot IS NOT NULL) AS bot_message
                                    FROM messages m
                                    LEFT JOIN users u ON m.sent_by = u.id
                                    WHERE {where_clause}
                                    ORDER BY m.created_at DESC
                                    LIMIT %s OFFSET %s
                                """, where_params + [limit, offset])
                                rows = list(await cur.fetchall())
                                rows.reverse()
                                return rows

                    total_count, messages = await asyncio.gather(fetch_count(), fetch_messages())

                    if not messages:
                        await sio_instance.sio.emit('all_messages_nocache', [], to=sid)
                        await sio_instance.sio.emit('get_messages_response', {'success': True, 'count': 0, 'total': total_count, 'offset': offset}, to=sid)
                        return

                    message_ids = [str(msg['id']) for msg in messages]
                    placeholders = ','.join(['%s'] * len(message_ids))

                    async def fetch_reactions():
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(
                                    f"SELECT message_id, reaction, user_id, bot_id, super_reaction FROM message_reactions WHERE message_id IN ({placeholders})",
                                    message_ids
                                )
                                return await cur.fetchall()

                    parent_ids = list({str(msg['parent_message_id']) for msg in messages if msg.get('parent_message_id')})

                    async def fetch_parents():
                        if not parent_ids:
                            return []
                        ph = ','.join(['%s'] * len(parent_ids))
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(f"""
                                    SELECT m.id, m.message, m.sent_by, m.sent_by_bot,
                                           u.username AS user_name, b.name AS bot_name
                                    FROM messages m
                                    LEFT JOIN users u ON m.sent_by = u.id
                                    LEFT JOIN bots b ON m.sent_by_bot = b.id
                                    WHERE m.id IN ({ph})
                                """, parent_ids)
                                return await cur.fetchall()

                    bot_ids = list({str(msg['sent_by_bot']) for msg in messages if msg.get('sent_by_bot')})

                    async def fetch_bots():
                        if not bot_ids:
                            return []
                        ph = ','.join(['%s'] * len(bot_ids))
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(f"SELECT id, name, profile_picture FROM bots WHERE id IN ({ph})", bot_ids)
                                return await cur.fetchall()

                    command_user_ids = list({str(msg['command_user_id']) for msg in messages if msg.get('command_user_id')})

                    async def fetch_command_users():
                        if not command_user_ids:
                            return []
                        ph = ','.join(['%s'] * len(command_user_ids))
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(f"SELECT id, username FROM users WHERE id IN ({ph})", command_user_ids)
                                return await cur.fetchall()

                    reaction_rows, parent_rows, bot_rows, command_user_rows = await asyncio.gather(
                        fetch_reactions(),
                        fetch_parents(),
                        fetch_bots(),
                        fetch_command_users(),
                    )

                    reaction_user_ids = list({str(r['user_id']) for r in reaction_rows if r.get('user_id')})
                    reaction_bot_ids = list({str(r['bot_id']) for r in reaction_rows if r.get('bot_id')})

                    async def fetch_reaction_users():
                        if not reaction_user_ids:
                            return []
                        ph = ','.join(['%s'] * len(reaction_user_ids))
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(
                                    f"SELECT id, username, nickname AS display_name, profile_picture FROM users WHERE id IN ({ph})",
                                    reaction_user_ids
                                )
                                return await cur.fetchall()

                    async def fetch_reaction_bots():
                        if not reaction_bot_ids:
                            return []
                        ph = ','.join(['%s'] * len(reaction_bot_ids))
                        async with config.pool.acquire() as c:
                            async with c.cursor(aiomysql.DictCursor) as cur:
                                await cur.execute(
                                    f"SELECT id, name AS username, profile_picture FROM bots WHERE id IN ({ph})",
                                    reaction_bot_ids
                                )
                                return await cur.fetchall()

                    r_users, r_bots = await asyncio.gather(fetch_reaction_users(), fetch_reaction_bots())

                    now = datetime.now(timezone.utc)

                    def _is_premium(row):
                        if not row.get('premium'):
                            return False
                        exp = row.get('premium_expires_at')
                        if exp is None:
                            return True
                        if isinstance(exp, str):
                            exp = parser.parse(exp)
                        if exp.tzinfo is None:
                            exp = exp.replace(tzinfo=timezone.utc)
                        return exp > now

                    bots_by_id = {str(r['id']): r for r in bot_rows}
                    parents_by_id = {str(r['id']): r for r in parent_rows}
                    cmd_users_by_id = {str(r['id']): r for r in command_user_rows}
                    r_users_by_id = {str(r['id']): r for r in r_users}
                    r_bots_by_id = {str(r['id']): r for r in r_bots}

                    reactions_by_msg = {}
                    for r in reaction_rows:
                        reactions_by_msg.setdefault(str(r['message_id']), []).append(r)

                    for msg in messages:
                        for field in ('created_at', 'updated_at'):
                            v = msg.get(field)
                            if isinstance(v, datetime):
                                msg[field] = v.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z')
                            else:
                                msg[field] = None

                        if msg.get('assets'):
                            try:
                                msg['assets'] = json.loads(msg['assets'])
                            except Exception:
                                msg['assets'] = []
                        else:
                            msg['assets'] = []

                        if msg['bot_message']:
                            bot = bots_by_id.get(str(msg['sent_by_bot']), {})
                            msg['sender_info'] = {
                                'username': bot.get('name'),
                                'display_name': None,
                                'profile_picture': bot.get('profile_picture'),
                                'staff': False,
                                'premium': False,
                            }
                        else:
                            msg['sender_info'] = {
                                'username': msg.pop('username', None),
                                'display_name': msg.pop('display_name', None),
                                'profile_picture': msg.pop('profile_picture', None),
                                'staff': msg.pop('staff', None),
                                'premium': _is_premium(msg),
                            }

                        msg.pop('premium', None)
                        msg.pop('premium_expires_at', None)

                        parent_id = msg.pop('parent_message_id', None)
                        parent_info = None
                        if parent_id:
                            p = parents_by_id.get(str(parent_id))
                            if p:
                                preview = p['message'] or ''
                                parent_info = {
                                    'user_id': p['sent_by'] if p['sent_by'] else p['sent_by_bot'],
                                    'message_id': p['id'],
                                    'message_preview': (preview[:100] + '...') if len(preview) > 100 else preview,
                                    'username': p['user_name'] if p['user_name'] else p['bot_name'],
                                }
                        msg['parent_message_info'] = parent_info

                        command_user_id = msg.pop('command_user_id', None)
                        command_info = None
                        if command_user_id:
                            cu = cmd_users_by_id.get(str(command_user_id))
                            if cu:
                                command_info = {
                                    'command': msg.pop('command', None),
                                    'username': cu['username'],
                                }
                        msg.pop('command', None)
                        msg['command_info'] = command_info

                        msg['reactions'] = [
                            {
                                'reaction': r['reaction'],
                                'user_id': r['user_id'],
                                'bot_id': r['bot_id'],
                                'super_reaction': r['super_reaction'] == 1,
                                'reaction_user_info': (
                                    r_users_by_id.get(str(r['user_id'])) if r.get('user_id')
                                    else r_bots_by_id.get(str(r['bot_id']))
                                ),
                            }
                            for r in reactions_by_msg.get(str(msg['id']), [])
                        ]

                    await sio_instance.sio.emit('all_messages_nocache', messages, to=sid)
                    await sio_instance.sio.emit(
                        'get_messages_response',
                        {'success': True, 'count': len(messages), 'total': total_count, 'offset': offset},
                        to=sid
                    )

                    async def _update_cache():
                        try:
                            if is_contact:
                                await update_cached_messages(contact_id=contact_id, messages=messages, offset=offset, limit=limit)
                            else:
                                await update_cached_messages(server_id=server_id, channel_id=channel_id, messages=messages, offset=offset, limit=limit)
                        except Exception:
                            pass

                    asyncio.create_task(_update_cache())

        except Exception as e:
            await addMessageToLogs(f"get_messages send_db error: {e}", "ERROR")

    asyncio.create_task(send_db())
    await sio_instance.sio.emit('get_messages_response', {'success': True, 'offset': offset, 'count': len(cached_messages)}, to=sid)

@auth_required(server_required=True, allow_bots=False)
async def get_message_by_id(sid, metadata, data):
    message_id = data.get('message_id')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    contact_id = data.get('contact_id')
    user_id = metadata.get('account_id')

    if (server_id is None and channel_id is None) and contact_id is None or message_id is None:
        await addMessageToLogs(f"Missing required fields for get_message_by_id", "INFO")
        await sio_instance.sio.emit('message_by_id', None, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if contact_id is not None:
                if not await inContact(cur, user_id, contact_id):
                    await addMessageToLogs(f"User {user_id} not in contact {contact_id}", "INFO")
                    await sio_instance.sio.emit('message_by_id', None, to=sid)
                    return
                
                await cur.execute("""
                    SELECT m.id, m.message, m.sent_by, m.sent_by_bot, u.username AS user_name, b.name AS bot_name, m.created_at
                    FROM messages m
                    LEFT JOIN users u ON m.sent_by = u.id
                    LEFT JOIN bots b ON m.sent_by_bot = b.id
                    WHERE m.id = %s AND m.contact_id = %s
                    LIMIT 1
                """, (message_id, contact_id))
            else:
                await cur.execute("""
                    SELECT m.id, m.message, m.sent_by, m.sent_by_bot, u.username AS user_name, b.name AS bot_name, m.created_at
                    FROM messages m
                    LEFT JOIN users u ON m.sent_by = u.id
                    LEFT JOIN bots b ON m.sent_by_bot = b.id
                    WHERE m.id = %s AND m.server_id = %s AND m.channel_id = %s
                    LIMIT 1
                """, (message_id, server_id, channel_id))
            
            message = await cur.fetchone()

            if not message:
                await sio_instance.sio.emit('message_by_id', None, to=sid)
                return

            if isinstance(message.get('created_at'), datetime):
                message['created_at'] = message['created_at'].astimezone(timezone.utc).isoformat().replace('+00:00', 'Z')

            if message.get('sent_by_bot'):
                message['sender'] = message.pop('bot_name')
                message['sender_id'] = message.pop('sent_by_bot')
            else:
                message['sender'] = message.pop('user_name')
                message['sender_id'] = message.pop('sent_by')

            message.pop('sent_by', None)
            message.pop('sent_by_bot', None)

            await sio_instance.sio.emit('message_by_id', message, to=sid)
            await addMessageToLogs(f"Emitted message_by_id to {user_id}", "INFO")

@auth_required(server_required=True, allow_bots=True)
async def delete_message(sid, metadata, data):
    message_id = data.get('message_id')
    contact_id = data.get('contact_id')
    req_id = data.get('req_id')
    is_bot = metadata.get('is_bot')
    account_id = metadata.get('account_id')

    if message_id is None:
        await sio_instance.sio.emit(
            'message_deleted',
            {'success': False, 'error': 'Missing required fields', 'req_id': req_id},
            to=sid
        )
        await addMessageToLogs("Missing required fields for delete_message", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            server_id = None
            channel_id = None

            if contact_id:
                if not await inContact(cur, account_id, contact_id):
                    await sio_instance.sio.emit(
                        'message_deleted',
                        {'success': False, 'error': 'User not in contact', 'req_id': req_id},
                        to=sid
                    )
                    await addMessageToLogs(f"User {account_id} not in contact {contact_id}", "INFO")
                    return
            else:
                if is_bot:
                    await cur.execute(
                        "SELECT server_id, channel_id FROM messages WHERE id = %s AND sent_by_bot = %s",
                        (message_id, account_id)
                    )
                else:
                    await cur.execute(
                        "SELECT server_id, channel_id FROM messages WHERE id = %s AND sent_by = %s",
                        (message_id, account_id)
                    )
                message = await cur.fetchone()
                if not message:
                    await sio_instance.sio.emit(
                        'message_deleted',
                        {'success': False, 'error': 'Message not found', 'req_id': req_id},
                        to=sid
                    )
                    await addMessageToLogs(f"Message not found for delete_message, message id: {message_id}, user id: {account_id}", "INFO")
                    return
                server_id = message['server_id']
                channel_id = message['channel_id']

            if is_bot:
                result = await cur.execute(
                    "DELETE FROM messages WHERE id = %s AND sent_by_bot = %s",
                    (message_id, account_id)
                )
            else:
                result = await cur.execute(
                    "DELETE FROM messages WHERE id = %s AND sent_by = %s",
                    (message_id, account_id)
                )

            if result == 0:
                await sio_instance.sio.emit(
                    'message_deleted',
                    {'success': False, 'error': 'Message couldn\'t be deleted', 'req_id': req_id},
                    to=sid
                )
                await addMessageToLogs(f"Message couldn't be deleted for delete_message, message id: {message_id}, user id: {account_id}", "INFO")
                return

            await delete_cached_message(
                contact_id=contact_id,
                server_id=server_id,
                channel_id=channel_id,
                message_id=message_id
            )

            room = f'contact:{contact_id}' if contact_id else f'server:{server_id}:channel:{channel_id}'
            await sio_instance.sio.emit(
                'message_deleted',
                {'success': True, 'message_id': message_id, 'req_id': req_id},
                room=room
            )
            await addMessageToLogs(f"Emitted message_deleted to {room}", "INFO")

@auth_required(server_required=True, allow_bots=True)
async def add_reaction(sid, metadata, data):
    message_id = data.get('message_id')
    req_id = data.get('req_id')
    reaction = data.get('reaction')
    account_id = metadata.get('account_id')
    is_bot = metadata.get('is_bot')
    callback = data.get('_callback')

    async def respond(resp_data):
        if callback:
            await callback(resp_data)
        else:
            await sio_instance.sio.emit('add_reaction', resp_data, to=sid)

    if message_id is None or reaction is None:
        await respond({'success': False, 'error': 'Missing required fields', 'req_id': req_id})
        await addMessageToLogs(f"Missing required fields for add_reaction", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(
                "SELECT channel_id, server_id, contact_id FROM messages WHERE id = %s",
                (message_id,)
            )
            message_info = await cur.fetchone()
            if not message_info:
                await respond({'success': False, 'error': 'Message not found', 'req_id': req_id})
                await addMessageToLogs(f"Message not found for add_reaction, message id: {message_id}, user id: {account_id}", "INFO")
                return

            channel_id = message_info['channel_id']
            server_id = message_info['server_id']
            contact_id = message_info['contact_id']

            if contact_id:
                if not await inContact(cur, account_id, contact_id):
                    await respond({'success': False, 'error': f'User not in contact, contact id: {contact_id}', 'req_id': req_id})
                    await addMessageToLogs(f"User not in contact for add_reaction, message id: {message_id}, user id: {account_id}", "INFO")
                    return
            else:
                if is_bot:
                    if not await is_bot_in_server(cur, account_id, server_id):
                        await respond({'success': False, 'error': f'Bot not in server, server id: {server_id}', 'req_id': req_id})
                        await addMessageToLogs(f"Bot not in server for add_reaction, message id: {message_id}, user id: {account_id}", "INFO")
                        return
                else:
                    if not await is_user_in_server(cur, account_id, server_id):
                        await respond({'success': False, 'error': f'User not in server, server id: {server_id}', 'req_id': req_id})
                        await addMessageToLogs(f"User not in server for add_reaction, message id: {message_id}, user id: {account_id}", "INFO")
                        return

            if not reaction.startswith(':') or not reaction.endswith(':'):
                await respond({'success': False, 'error': 'Invalid reaction', 'req_id': req_id})
                await addMessageToLogs(f"Invalid reaction for add_reaction, message id: {message_id}, user id: {account_id}, reaction: {reaction}", "INFO")
                return

            if is_bot:
                await cur.execute(
                    "SELECT * FROM message_reactions WHERE message_id=%s AND reaction=%s AND bot_id=%s",
                    (message_id, reaction, account_id)
                )
            else:
                await cur.execute(
                    "SELECT * FROM message_reactions WHERE message_id=%s AND reaction=%s AND user_id=%s",
                    (message_id, reaction, account_id)
                )
            existing = await cur.fetchone()

            room = f'contact:{contact_id}' if contact_id else f'server:{server_id}:channel:{channel_id}'

            if existing:
                if is_bot:
                    await cur.execute(
                        "DELETE FROM message_reactions WHERE message_id=%s AND reaction=%s AND bot_id=%s",
                        (message_id, reaction, account_id)
                    )
                else:
                    await cur.execute(
                        "DELETE FROM message_reactions WHERE message_id=%s AND reaction=%s AND user_id=%s",
                        (message_id, reaction, account_id)
                    )
                await conn.commit()
                await sio_instance.sio.emit(
                    'remove_reaction',
                    {'success': True, 'message_id': message_id, 'reaction': reaction, 'user_id': account_id, 'req_id': req_id},
                    room=room
                )
                await addMessageToLogs(f"Removed reaction {reaction} from message {message_id} by user {account_id}", "INFO")
            else:
                if is_bot:
                    await cur.execute(
                        "INSERT INTO message_reactions (message_id, reaction, bot_id) VALUES (%s, %s, %s)",
                        (message_id, reaction, account_id)
                    )
                else:
                    await cur.execute(
                        "INSERT INTO message_reactions (message_id, reaction, user_id) VALUES (%s, %s, %s)",
                        (message_id, reaction, account_id)
                    )
                await conn.commit()
                await sio_instance.sio.emit(
                    'add_reaction',
                    {'success': True, 'message_id': message_id, 'reaction': reaction, 'user_id': account_id, 'req_id': req_id},
                    room=room
                )
                await addMessageToLogs(f"Added reaction {reaction} to message {message_id} by user {account_id}", "INFO")

@auth_required(server_required=True, allow_bots=True)
async def edit_message(sid, metadata, data):
    message_id = data.get('message_id')
    message = data.get('message')
    embed = data.get('embed')
    req_id = data.get('req_id')
    account_id = metadata.get('account_id')
    is_bot = metadata.get('is_bot')
    server_id = metadata.get('server_id')
    
    if is_bot:
        if not all([(message or embed), message_id]):
            await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
            return
    else:
        if not all([message, message_id]):
            await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
            return
    
    if embed is not None and is_bot:
        if not validate_embed(embed):
            await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Invalid embed data', 'req_id': req_id}, to=sid)
            return
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            message_row = None
            if is_bot:
                await cur.execute(
                    "SELECT server_id, channel_id, message, embed FROM messages WHERE id=%s AND sent_by_bot=%s",
                    (message_id, account_id)
                )
                message_row = await cur.fetchone()
            else:
                await cur.execute(
                    "SELECT server_id, channel_id, message FROM messages WHERE id=%s AND sent_by=%s",
                    (message_id, account_id)
                )
                message_row = await cur.fetchone()
            
            if message_row == None:
                await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=sid)
                return
            else:
                if message_row['server_id'] != server_id:
                    await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=sid)
                    return
                if is_bot:
                    db_embed = json.loads(message_row['embed']) if message_row.get('embed') else None
                    if message_row['message'] == message and db_embed == embed:
                        await sio_instance.sio.emit('edit_message_response', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=sid)
                        return
                else:
                    if message_row['message'] == message:
                        await sio_instance.sio.emit('edit_message_response', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=sid)
                        return

            is_premium = False
            if not is_bot:
                is_premium = await get_user_premium_status(cur, account_id)
            
            limit = 10000 if is_premium else 3000
            
            if embed is not None and is_bot:
                embed_list = embed if isinstance(embed, list) else [embed]
                for e in embed_list:
                    e['bot_id'] = account_id
            
            if message is not None and len(message) > limit:
                await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Message too long', 'req_id': req_id}, to=sid)
                return

            if message is None and (embed is None or not is_bot):
                await sio_instance.sio.emit('edit_message_response', {'success': False, 'error': 'Invalid update', 'req_id': req_id}, to=sid)
                return
            
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
            
            if is_bot:
                embed_str = json.dumps(embed) if embed is not None else None
                
                fields = []
                values = []

                if message is not None:
                    fields.append("message = %s")
                    values.append(message)

                if embed is not None:
                    embed_str = json.dumps(embed)
                    fields.append("embed = %s")
                    values.append(embed_str)

                if fields:
                    fields.append("updated_at = %s")
                    fields.append("edited = %s")
                    values.append(timestamp)
                    values.append(True)

                    values.append(message_id)
                    values.append(account_id)

                    sql = f'''
                        UPDATE messages
                        SET {', '.join(fields)}
                        WHERE id = %s AND sent_by_bot = %s
                    '''

                    await cur.execute(sql, values)
                    await conn.commit()
                    await sio_instance.sio.emit('edit_message_response', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=sid)
            else:
                await cur.execute(
                    "UPDATE messages SET message=%s, updated_at=%s, edited=1 WHERE id=%s AND sent_by=%s",
                    (message, timestamp, message_id, account_id)
                )
                await conn.commit()
                await sio_instance.sio.emit('edit_message_response', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=sid)

            server_id = message_row['server_id']
            channel_id = message_row['channel_id']
                
    await sio_instance.sio.emit('update_message', {
        'id': message_id,
        'bot_message': is_bot,
        'message': message,
        'updated_at': timestamp,
        'embed': embed if is_bot and embed is not None else None,
        'sent_by': account_id
    }, room=f'server:{server_id}:channel:{channel_id}', to=sid)