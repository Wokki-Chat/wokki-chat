from server.config import typing_lock, add_typing_user, remove_typing_user, get_typing_users
import server.sio_instance as sio_instance
from server.helpers.user_helpers import auth_required
import aiomysql
import server.config as config
from server.helpers.logs import addMessageToLogs

@auth_required(server_or_contact_required=True, allow_bots=False)
async def typing(sid, metadata, data):
    typing_state = data.get('typing')
    channel_id = data.get('channel_id')
    server_id = data.get('server_id')
    user_id = metadata.get('account_id')

    if typing_state is None or channel_id is None or server_id is None:
        await addMessageToLogs("Missing required fields for typing", "INFO")
        return

    if typing_state:
        await add_typing_user(user_id, channel_id, server_id)
        await addMessageToLogs(f"User is typing, user id: {user_id}, channel id: {channel_id}, server id: {server_id}", "INFO")
    else:
        await remove_typing_user(user_id, channel_id, server_id)
        await addMessageToLogs(f"User is not typing, user id: {user_id}, channel id: {channel_id}, server id: {server_id}", "INFO")

    typing_users = await get_typing_users(channel_id, server_id)
    await sio_instance.sio.emit(
        'users_typing',
        {'user_ids': list(typing_users), 'channel_id': channel_id, 'server_id': server_id},
        room=f'server:{server_id}:channel:{channel_id}'
    )
    await addMessageToLogs(f"Emitted users_typing, user ids: {list(typing_users)}, channel id: {channel_id}, server id: {server_id}", "INFO")