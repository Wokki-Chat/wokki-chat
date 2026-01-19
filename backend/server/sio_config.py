import traceback
from server.helpers.connection_helpers import handle_connect, handle_disconnect
from server.helpers.logs import addMessageToLogs
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

@sio.on_error_default
async def catch_all_sio_errors(sid, exc, event=None, data=None):
    tb_str = "".join(traceback.format_exception(type(exc), exc, exc.__traceback__))
    msg = f"Error in event '{event}' with data {data}:\n{tb_str}"
    addMessageToLogs(msg, 'ERROR')
