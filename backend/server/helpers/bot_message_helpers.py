from datetime import datetime, timezone
import aiomysql
import json

import server.config as config
from server.config import get_bot_sid_from_id
from server.sio_instance import sio
from server.helpers.server_helpers import is_user_in_server
from server.helpers.bot_helpers import validate_embed, verify_bot_token, is_bot_in_server
from server.helpers.user_helpers import verify_access_token
from server.helpers.logs import addMessageToLogs

async def edit_bot_message(sid, data):
    bot_token = data.get('bot_token')
    message_id = data.get('message_id')
    message = data.get('message')
    embed = data.get('embed')
    req_id = data.get('req_id')
    
    if not all([bot_token, (message or embed), message_id]):
        await addMessageToLogs(f"Missing required fields for edit_bot_message", "INFO")
        await sio.emit('edit_bot_message_response', {'success': False, 'error': 'Missing required fields', 'req_id': req_id}, to=sid)
        return
    
    if embed is not None:
        if not validate_embed(embed):
            await addMessageToLogs(f"Invalid embed data for edit_bot_message, bot token: {bot_token}, message id: {message_id}", "INFO")
            await sio.emit('edit_bot_message_response', {'success': False, 'error': 'Invalid embed data', 'req_id': req_id}, to=sid)
            return
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            bot_id = await verify_bot_token(cur, bot_token)
            if not bot_id:
                await addMessageToLogs(f"Invalid token for edit_bot_message, bot token: {bot_token}", "INFO")
                await sio.emit('edit_bot_message_response', {'success': False, 'error': 'Invalid token', 'req_id': req_id}, to=sid)
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
                await addMessageToLogs(f"Message not found for edit_bot_message, bot token: {bot_token}, message id: {message_id}", "INFO")
                await sio.emit('edit_bot_message_response', {'success': False, 'error': 'Message not found', 'req_id': req_id}, to=sid)
                return
        
            if embed is not None:
                for e in embed:
                    e['bot_id'] = bot_id
       
            
            if message is not None:
                if len(message) > 3000:
                    await addMessageToLogs(f"Message too long for edit_bot_message, bot token: {bot_token}, message id: {message_id}", "INFO")
                    await sio.emit('edit_bot_message_response', {'success': False, 'error': 'Message too long', 'req_id': req_id}, to=sid)
                    return
            
            await cur.execute('SELECT name, profile_picture FROM bots WHERE id = %s', (bot_id,))
            bot_row = await cur.fetchone()
            if not bot_row:
                await addMessageToLogs(f"Bot not found for edit_bot_message, bot token: {bot_token}", "INFO")
                await sio.emit('edit_bot_message_response', {'success': False, 'error': 'Bot not found', 'req_id': req_id}, to=sid)
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
                
                await addMessageToLogs(f"updated bot message for server id: bot id: {bot_id}, message id: {message_id}", "INFO")
                
            server_id = message_row['server_id']
            channel_id = message_row['channel_id']            
                
    await sio.emit('update_message', {
        'id': message_id,
        'bot_message': 1,
        'message': message,
        'updated_at': timestamp,
        'embed': embed
    }, room=f'server:{server_id}:channel:{channel_id}', to=sid)
    await addMessageToLogs(f"Emited update_message for server id: {server_id}, channel id: {channel_id}, message id: {message_id}", "INFO")
    await sio.emit('edit_bot_message_response', {'success': True, 'req_id': req_id}, to=sid)
    await addMessageToLogs(f"Emited edit_bot_message_response for bot token: {bot_token}, message id: {message_id}", "INFO")
    

async def embed_button(sid, data):
    access_token = data.get('access_token')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    bot_id = data.get('bot_id')
    button_id = data.get('button_id')
    
    if not all([access_token, bot_id, button_id, server_id, channel_id]):
        await addMessageToLogs(f"Missing required fields for embed_button", "INFO")
        await sio.emit('embed_button_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                await addMessageToLogs(f"Invalid token for embed_button, access token: {access_token}", "INFO")
                await sio.emit('embed_button_response', {'success': False, 'error': 'Invalid token'}, to=sid)
                return
            
            if not await is_user_in_server(cur, user_id, server_id):
                await addMessageToLogs(f"User is not in server for embed_button, user id: {user_id}, server id: {server_id}", "INFO")
                await sio.emit('embed_button_response', {'success': False, 'error': 'User is not in server'}, to=sid)
                return
            
            if not await is_bot_in_server(cur, bot_id, server_id):
                await addMessageToLogs(f"Bot is not in server for embed_button, bot id: {bot_id}, server id: {server_id}", "INFO")
                await sio.emit('embed_button_response', {'success': False, 'error': 'Bot is not in server'}, to=sid)
                return
                        
            bot_sid = await get_bot_sid_from_id(bot_id)
                
            if not bot_sid:
                await addMessageToLogs(f"Bot not found for embed_button, bot id: {bot_id}", "INFO")
                await sio.emit('embed_button_response', {'success': False, 'error': 'Bot not found'}, to=sid)
                return

            if bot_sid:
                await sio.emit('embed_button_pressed', {
                    'button_id': button_id,
                    'sent_by_user_id': user_id,
                    'server_id': server_id,
                    'channel_id': channel_id
                }, to=bot_sid)
                await addMessageToLogs(f"Emitted embed_button_pressed for bot id: {bot_id}, button id: {button_id}, user id: {user_id}, server id: {server_id}, channel id: {channel_id}", "INFO")
            else:
                await addMessageToLogs(f"Bot not found for embed_button, bot id: {bot_id}", "INFO")
                await sio.emit('embed_button_response', {'success': False, 'error': 'Bot not found'}, to=sid)
                return            

            await sio.emit('embed_button_response', {'success': True}, to=sid)
            await addMessageToLogs(f"Emited embed_button_response for bot id: {bot_id}, button id: {button_id}", "INFO")
