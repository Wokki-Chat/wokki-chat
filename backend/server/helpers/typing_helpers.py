from server.config import typing_lock, add_typing_user, remove_typing_user, get_typing_users
import server.sio_instance as sio_instance
from server.helpers.user_helpers import auth_required
from server.helpers.server_helpers import is_user_in_server, get_server_channel_sids
import aiomysql
import server.config as config
from server.helpers.logs import addMessageToLogs

@auth_required(server_required=True, allow_bots=False)
async def typing(sid, metadata, data):
    typing_state = data.get('typing')
    channel_id = data.get('channel_id')
    server_id = data.get('server_id')

    user_id = metadata.get('account_id')

    if typing_state is None or channel_id is None or server_id is None:
        await addMessageToLogs(f"Missing required fields for typing", "INFO")
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            server_channel_sids = await get_server_channel_sids(cur, server_id, channel_id)

    async with typing_lock:
        if typing_state:
            await add_typing_user(user_id, channel_id, server_id)
            await addMessageToLogs(f"User is typing, user id: {user_id}, channel id: {channel_id}, server id: {server_id}", "INFO")
        else:
            await remove_typing_user(user_id, channel_id, server_id)
            await addMessageToLogs(f"User is not typing, user id: {user_id}, channel id: {channel_id}, server id: {server_id}", "INFO")

        typing_users = await get_typing_users()
        await sio_instance.sio.emit('users_typing', {'user_ids': list(typing_users), 'channel_id': channel_id, 'server_id': server_id}, to=server_channel_sids)
        await addMessageToLogs(f"Emitted users_typing, user ids: {list(typing_users)}, channel id: {channel_id}, server id: {server_id}", "INFO")