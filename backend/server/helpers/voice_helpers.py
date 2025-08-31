import json
import aiohttp
import server.config as config
from server.helpers.user_helpers import verify_access_token
from server.helpers.server_helpers import is_user_in_server
import aiomysql
import server.sio_instance as sio_instance
from server.helpers.livekit_helpers import generate_livekit_token, livekit_room_exists, livekit_create_room
from server.helpers.logs import addMessageToLogs

async def connect_to_voice_channel(sid, data):
    access_token = data.get('access_token')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')

    if not access_token or not server_id or not channel_id:
        await addMessageToLogs("Missing data for connect_to_voice_channel", "INFO")
        await sio_instance.sio.emit('error', {'msg': 'Missing data'}, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            user_id = await verify_access_token(cur, access_token)
            if not user_id:
                await addMessageToLogs("Invalid token for connect_to_voice_channel", "INFO")
                await sio_instance.sio.emit('error', {'msg': 'Invalid token'}, to=sid)
                return
            
            if not await is_user_in_server(cur, user_id, server_id):
                await addMessageToLogs("User is not in server for connect_to_voice_channel", "INFO")
                await sio_instance.sio.emit('error', {'msg': 'User is not in server'}, to=sid)
                return

            await cur.execute("SELECT channels FROM servers WHERE id = %s", (server_id,))
            server_row = await cur.fetchone()
            if not server_row:
                await addMessageToLogs("Server not found for connect_to_voice_channel", "INFO")
                await sio_instance.sio.emit('error', {'msg': 'Server not found'}, to=sid)
                return

            channels = server_row['channels']
            if isinstance(channels, str):
                channels = json.loads(channels)

            channel_exists = any(
                ch['channel_id'] == channel_id and ch['channel_type'] == 'voice' for ch in channels
            )
            if not channel_exists:
                await addMessageToLogs("Voice channel not found for connect_to_voice_channel", "INFO")
                await sio_instance.sio.emit('error', {'msg': 'Voice channel not found'}, to=sid)
                return

            room_name = f"{server_id}:{channel_id}"

            async with aiohttp.ClientSession() as session:
                try:
                    room_exists = await livekit_room_exists(room_name)
                    if not room_exists:
                        await livekit_create_room(room_name)
                except Exception as e:
                    await addMessageToLogs(f"LiveKit room error: {str(e)}", "ERROR")
                    await sio_instance.sio.emit('error', {'msg': f'LiveKit room error: {str(e)}'}, to=sid)
                    return

            livekit_token = generate_livekit_token(str(user_id), room_name)

            await sio_instance.sio.emit('livekit_token', {
                'token': livekit_token,
                'url': 'https://chat.wokki20.nl/livekit',
                'room': room_name
            }, to=sid)

            await addMessageToLogs(f"User connected to voice channel, user id: {user_id}, server id: {server_id}, channel id: {channel_id}", "INFO")
            return
