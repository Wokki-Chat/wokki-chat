from server.sio_instance import sio
from server.helpers.typing_helpers import typing

@sio.on('typing')
async def handle_typing(sid, data):
    await typing(sid, data)