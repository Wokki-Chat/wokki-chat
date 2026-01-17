from server.helpers.connection_helpers import handle_connect, handle_disconnect
from server.config import server_name
from server.sio_instance import sio

@sio.event
async def connect(sid, environ, auth=None):
    requested_server = auth.get("requested_server") if auth else None
    if requested_server and requested_server != server_name:
        await sio.disconnect(sid)
        return
    await handle_connect(sid, environ)

@sio.event
async def disconnect(sid):
    await handle_disconnect(sid)