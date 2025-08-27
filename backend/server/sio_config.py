from server.helpers.connection_helpers import handle_connect, handle_disconnect
from server.sio_instance import sio

@sio.event
async def connect(sid, environ):
    await handle_connect(sid, environ)

@sio.event
async def disconnect(sid):
    await handle_disconnect(sid)