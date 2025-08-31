from server.config import typing_lock, typing_users
import server.sio_instance as sio_instance
from server.helpers.user_helpers import verify_access_token
from server.helpers.server_helpers import is_user_in_server, get_server_channel_sids
import aiomysql
import server.config as config
from server.helpers.logs import addMessageToLogs

async def typing(sid, data):
    access_token = data.get('access_token')
    typing_state = data.get('typing')
    channel_id = data.get('channel_id')
    server_id = data.get('server_id')

    if access_token is None or typing_state is None or channel_id is None or server_id is None:
        addMessageToLogs(f"Missing required fields for typing", "INFO")
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                addMessageToLogs(f"Invalid access token for typing, access token: {access_token}", "INFO")
                await sio_instance.sio.emit('error', {'msg': 'Invalid access token'}, to=sid)
                return
            
            if not await is_user_in_server(cur, user_id, server_id):
                addMessageToLogs(f"User is not in server for typing, user id: {user_id}, server id: {server_id}", "INFO")
                await sio_instance.sio.emit('error', {'msg': 'User is not in server'}, to=sid)
                return
            
            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)

    async with typing_lock:
        if typing_state:
            typing_users.add(user_id)
            addMessageToLogs(f"User is typing, user id: {user_id}, channel id: {channel_id}, server id: {server_id}", "INFO")
        else:
            typing_users.discard(user_id)
            addMessageToLogs(f"User is not typing, user id: {user_id}, channel id: {channel_id}, server id: {server_id}", "INFO")

        await sio_instance.sio.emit('users_typing', {'user_ids': list(typing_users), 'channel_id': channel_id, 'server_id': server_id}, to=server_channel_sids)
        addMessageToLogs(f"Emitted users_typing, user ids: {list(typing_users)}, channel id: {channel_id}, server id: {server_id}", "INFO")