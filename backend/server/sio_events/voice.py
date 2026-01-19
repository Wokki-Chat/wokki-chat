from server.sio_instance import sio
from server.helpers.voice_helpers import connect_to_voice_channel

@sio.safe('connect_to_voice_channel')
async def handle_connect_to_voice_channel(sid, data):
    await connect_to_voice_channel(sid, data)