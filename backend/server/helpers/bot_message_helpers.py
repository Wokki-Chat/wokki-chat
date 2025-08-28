from datetime import datetime, timezone
import json
from server.config import MAX_MESSAGES, TIME_WINDOW_SECONDS, bot_message_timestamps, sid_to_bot_id
import server.config as config
from server.helpers.server_helpers import get_server_channel_sids, is_user_in_server
from server.helpers.bot_helpers import validate_embed, verify_bot_token, is_bot_in_server
from server.helpers.user_helpers import verify_access_token
import aiomysql
import uuid
import server.sio_instance as sio_instance

async def send_bot_message(sid, data):
    bot_token = data.get('bot_token')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    message = data.get('message')
    command = data.get('command') or None
    user_id = data.get('user_id') or None
    embed = data.get('embed')
    req_id = data.get('req_id')
    parent_message_id = data.get('parent_message_id')
    file_names = data.get('file_names')

    if not all([bot_token, server_id, channel_id, (message or embed)]):
        print("[send_bot_message] Missing one of bot_token, server_id, channel_id, or (message or embed)")
        await sio_instance.sio.emit('send_bot_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
        return
    
    if embed is not None:
        if not validate_embed(embed):
            await sio_instance.sio.emit('send_bot_message_response', {'success': False, 'error': 'Invalid embed data', 'req_id': req_id}, to=sid)
            return
    
    message_id = str(uuid.uuid4())
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            bot_id = await verify_bot_token(cur, bot_token)
            if not bot_id:
                await sio_instance.sio.emit('send_bot_message_response', {'success': False, 'error': 'Invalid token', 'req_id': req_id}, to=sid)
                return
            
            if not await is_bot_in_server(cur, bot_id, server_id):
                await sio_instance.sio.emit('send_bot_message_response', {'success': False, 'error': 'Bot is not in server', 'req_id': req_id}, to=sid)
                return
        
            if embed is not None:
                for e in embed:
                    e['bot_id'] = bot_id
       
            
            now = datetime.now(timezone.utc)
            timestamps = bot_message_timestamps[bot_id]
            
            while timestamps and (now - timestamps[0]).total_seconds() > TIME_WINDOW_SECONDS:
                timestamps.popleft()

            if len(timestamps) >= MAX_MESSAGES:
                await sio_instance.sio.emit('send_bot_message_response', {
                    'success': False,
                    'error': f'Rate limit exceeded. Max {MAX_MESSAGES} messages every {TIME_WINDOW_SECONDS} seconds.',
                    'req_id': req_id
                }, to=sid)
                return

            timestamps.append(now)
            
            if len(message) > 3000:
                await sio_instance.sio.emit('send_bot_message_response', {'success': False, 'error': 'Message too long', 'req_id': req_id}, to=sid)
                return
            
            await cur.execute('SELECT name, profile_picture FROM bots WHERE id = %s', (bot_id,))
            bot_row = await cur.fetchone()
            if not bot_row:
                await sio_instance.sio.emit('send_bot_message_response', {'success': False, 'error': 'Bot not found', 'req_id': req_id}, to=sid)
                return

            username = bot_row['name']
            profile_picture = bot_row['profile_picture']
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

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
            
            await cur.execute(
                '''
                INSERT INTO bot_messages 
                (id, message, bot_id, created_at, updated_at, edited, server_id, channel_id, command, command_user_id, embed, parent_message_id, assets)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ''',
                (message_id, message, bot_id, timestamp, None, False, server_id, channel_id, command, user_id, embed_str, parent_message_id, assets_json)
            )
            await conn.commit()
            
            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)

    if isinstance(timestamp, str):
        timestamp = datetime.fromisoformat(timestamp)

    await sio_instance.sio.emit('new_message', {
        'id': message_id,
        'bot_message': 1,
        'username': username,
        'message': message,
        'created_at': timestamp.astimezone(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'server_id': server_id,
        'channel_id': channel_id,
        'sent_by': None,
        'parent_message_id': parent_message_id,
        'profile_picture': profile_picture,
        'assets': json.loads(assets_json) if assets_json else [],
        'command': command,
        'command_user_id': user_id,
        'embed': embed
    }, to=server_channel_sids)
    await sio_instance.sio.emit('send_bot_message_response', {'success': True, 'message_id': message_id, 'req_id': req_id}, to=sid)
    
async def edit_bot_message(sid, data):
    bot_token = data.get('bot_token')
    message_id = data.get('message_id')
    message = data.get('message')
    embed = data.get('embed')
    req_id = data.get('req_id')
    
    if not all([bot_token, (message or embed), message_id]):
        print("[send_bot_message] Missing one of bot_token, server_id, channel_id, or (message or embed)")
        await sio_instance.sio.emit('edit_bot_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
        return
    
    if embed is not None:
        if not validate_embed(embed):
            await sio_instance.sio.emit('edit_bot_message_response', {'success': False, 'error': 'Invalid embed data', 'req_id': req_id}, to=sid)
            return
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            bot_id = await verify_bot_token(cur, bot_token)
            if not bot_id:
                await sio_instance.sio.emit('edit_bot_message_response', {'success': False, 'error': 'Invalid token', 'req_id': req_id}, to=sid)
                return
            
            await cur.execute(
                '''
                SELECT server_id, channel_id FROM bot_messages 
                WHERE id = %s AND bot_id = %s
                ''',
                (message_id, bot_id)
            )
            message_row = await cur.fetchone()
            if not message_row:
                await sio_instance.sio.emit('edit_bot_message_response', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=sid)
                return
        
            if embed is not None:
                for e in embed:
                    e['bot_id'] = bot_id
       
            
            if message is not None:
                if len(message) > 3000:
                    await sio_instance.sio.emit('edit_bot_message_response', {'success': False, 'error': 'Message too long', 'req_id': req_id}, to=sid)
                    return
            
            await cur.execute('SELECT name, profile_picture FROM bots WHERE id = %s', (bot_id,))
            bot_row = await cur.fetchone()
            if not bot_row:
                await sio_instance.sio.emit('edit_bot_message_response', {'success': False, 'error': 'Bot not found', 'req_id': req_id}, to=sid)
                return
            
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
            
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
                values.append(bot_id)

                sql = f'''
                    UPDATE bot_messages
                    SET {', '.join(fields)}
                    WHERE id = %s AND bot_id = %s
                '''

                await cur.execute(sql, values)
                await conn.commit()
                
            server_id = message_row['server_id']
            channel_id = message_row['channel_id']
            
            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)
            
                
    await sio_instance.sio.emit('update_message', {
        'id': message_id,
        'bot_message': 1,
        'message': message,
        'updated_at': timestamp,
        'embed': embed
    }, to=server_channel_sids)
    await sio_instance.sio.emit('edit_bot_message_response', {'success': True, 'req_id': req_id}, to=sid)


async def delete_bot_message(sid, data):
    bot_token = data.get('bot_token')
    message_id = data.get('message_id')
    message = data.get('message')
    embed = data.get('embed')
    req_id = data.get('req_id')
    
    if not all([bot_token, (message or embed), message_id]):
        print("[delete_bot_message] Missing one of bot_token, server_id, channel_id, or (message or embed)")
        await sio_instance.sio.emit('delete_bot_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
        return
    
    if embed is not None:
        if not validate_embed(embed):
            await sio_instance.sio.emit('delete_bot_message_response', {'success': False, 'error': 'Invalid embed data', 'req_id': req_id}, to=sid)
            return
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            bot_id = await verify_bot_token(cur, bot_token)
            if not bot_id:
                await sio_instance.sio.emit('delete_bot_message_response', {'success': False, 'error': 'Invalid token', 'req_id': req_id}, to=sid)
                return
            
            await cur.execute(
                '''
                SELECT server_id, channel_id FROM bot_messages 
                WHERE id = %s AND bot_id = %s
                ''',
                (message_id, bot_id)
            )
            message_row = await cur.fetchone()
            if not message_row:
                await sio_instance.sio.emit('delete_bot_message_response', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=sid)
                return
        
            if embed is not None:
                for e in embed:
                    e['bot_id'] = bot_id
       
            
            if message is not None:
                if len(message) > 3000:
                    await sio_instance.sio.emit('delete_bot_message_response', {'success': False, 'error': 'Message too long', 'req_id': req_id}, to=sid)
                    return
            
            await cur.execute('SELECT name, profile_picture FROM bots WHERE id = %s', (bot_id,))
            bot_row = await cur.fetchone()
            if not bot_row:
                await sio_instance.sio.emit('delete_bot_message_response', {'success': False, 'error': 'Bot not found', 'req_id': req_id}, to=sid)
                return
            
            timestamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
            
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
                values.append(bot_id)

                sql = f'''
                    UPDATE bot_messages
                    SET {', '.join(fields)}
                    WHERE id = %s AND bot_id = %s
                '''

                await cur.execute(sql, values)
                await conn.commit()
                
            server_id = message_row['server_id']
            channel_id = message_row['channel_id']
            
            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)
            
                
    await sio_instance.sio.emit('message_deleted', message_id, to=server_channel_sids)
    await sio_instance.sio.emit('delete_bot_message_response', {'success': True, 'req_id': req_id}, to=sid)
    

async def embed_button(sid, data):
    access_token = data.get('access_token')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    bot_id = data.get('bot_id')
    button_id = data.get('button_id')
    
    if not all([access_token, bot_id, button_id, server_id, channel_id]):
        await sio_instance.sio.emit('embed_button_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                await sio_instance.sio.emit('embed_button_response', {'success': False, 'error': 'Invalid token'}, to=sid)
                return
            
            if not await is_user_in_server(cur, user_id, server_id):
                await sio_instance.sio.emit('embed_button_response', {'success': False, 'error': 'User is not in server'}, to=sid)
                return
            
            if not await is_bot_in_server(cur, bot_id, server_id):
                await sio_instance.sio.emit('embed_button_response', {'success': False, 'error': 'Bot is not in server'}, to=sid)
                return
                        
            bot_sid = None
            for s, b_id in sid_to_bot_id.items():
                if b_id == bot_id:
                    bot_sid = s
                    break

            if bot_sid:
                await sio_instance.sio.emit('embed_button_pressed', {
                    'button_id': button_id,
                    'sent_by_user_id': user_id,
                    'server_id': server_id,
                    'channel_id': channel_id
                }, to=bot_sid)

            await sio_instance.sio.emit('embed_button_response', {'success': True}, to=sid)