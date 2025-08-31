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

def log_exception_sync(exc_type, exc_value, exc_tb):
    """Fallback sync logger for excepthook (runs in thread)."""
    tb_str = ''.join(traceback.format_exception(exc_type, exc_value, exc_tb))
    asyncio.get_event_loop().create_task(addMessageToLogs(tb_str, "ERROR"))

sys.excepthook = log_exception_sync

def handle_async_exception(loop, context):
    """Asyncio exception handler."""
    msg = context.get("exception")
    if msg:
        tb_str = ''.join(traceback.format_exception(type(msg), msg, msg.__traceback__))
        loop.create_task(addMessageToLogs(tb_str, "ERROR"))
    else:
        loop.create_task(addMessageToLogs(str(context), "ERROR"))

loop = asyncio.get_event_loop()
loop.set_exception_handler(handle_async_exception)

@app.on_startup.append
async def startup(app):
    try:
        config.pool = await get_db_pool()
        config.typing_lock = asyncio.Lock()
        await addMessageToLogs("Server started", "INFO")
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        await addMessageToLogs(''.join(traceback.format_exception(exc_type, exc_value, exc_tb)), "ERROR")
        raise

@app.on_cleanup.append
async def cleanup(app):
    try:
        if config.pool:
            config.pool.close()
            await config.pool.wait_closed()
        await addMessageToLogs("Server stopped", "INFO")
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        await addMessageToLogs(''.join(traceback.format_exception(exc_type, exc_value, exc_tb)), "ERROR")
        raise

if __name__ == "__main__":
    try:
        web.run_app(app, host="0.0.0.0", port=5000)
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        asyncio.run(addMessageToLogs(''.join(traceback.format_exception(exc_type, exc_value, exc_tb)), "ERROR"))
        raise
