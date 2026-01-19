import traceback, os
from server.helpers.connection_helpers import handle_connect, handle_disconnect
from server.helpers.logs import betterstack_logger, BETTERSTACK_TOKEN, addMessageToLogs
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
    
    tb = exc.__traceback__
    while tb.tb_next:
        tb = tb.tb_next
    caller_file = os.path.basename(tb.tb_frame.f_code.co_filename)
    caller_line = tb.tb_lineno

    msg = f"Error in event '{event}' with data '{data}':\n{tb_str}"

    if BETTERSTACK_TOKEN:
        # send directly with modified file and line
        log_data = {
            'worker': server_name,
            'file': caller_file,
            'line': caller_line,
            'log_type': "ERROR"
        }
        betterstack_logger.error(msg, extra=log_data)
    else:
        # send through wrapper
        addMessageToLogs(msg, 'ERROR')
