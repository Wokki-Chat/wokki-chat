import asyncio
import sys
import traceback
from aiohttp import web
import server.sio_events
from server.sio_instance import sio
from server.db import get_db_pool
import server.config as config
import server.sio_config
from server.helpers.logs import addMessageToLogs

app = web.Application()
sio.attach(app)

def log_exception(exc_type, exc_value, exc_tb):
    """Logs exceptions with full traceback."""
    tb_str = ''.join(traceback.format_exception(exc_type, exc_value, exc_tb))
    addMessageToLogs(tb_str, "ERROR")

sys.excepthook = log_exception

def handle_async_exception(loop, context):
    msg = context.get("exception")
    if msg:
        tb_str = ''.join(traceback.format_exception(type(msg), msg, msg.__traceback__))
        addMessageToLogs(tb_str, "ERROR")
    else:
        addMessageToLogs(str(context), "ERROR")

loop = asyncio.get_event_loop()
loop.set_exception_handler(handle_async_exception)

@app.on_startup.append
async def startup(app):
    try:
        config.pool = await get_db_pool()
        config.typing_lock = asyncio.Lock()
        addMessageToLogs("Server started", "INFO")
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        log_exception(exc_type, exc_value, exc_tb)
        raise

@app.on_cleanup.append
async def cleanup(app):
    try:
        config.pool.close()
        addMessageToLogs("Server stopped", "INFO")
        await config.pool.wait_closed()
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        log_exception(exc_type, exc_value, exc_tb)
        raise

if __name__ == "__main__":
    try:
        web.run_app(app, host="0.0.0.0", port=5000)
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        log_exception(exc_type, exc_value, exc_tb)
        raise
