from server.helpers.connection_helpers import handle_connect, handle_disconnect
from server.helpers.user_helpers import verify_access_token, broadcast_user_update

def register_sio_handlers(sio):
    @sio.event
    async def connect(sid, environ):
        await handle_connect(sid, environ)

    @sio.event
    async def disconnect(sid):
        await handle_disconnect(sid)