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
import time
import requests
from logtail import LogtailHandler
import logging
from dotenv import load_dotenv
load_dotenv()
import os
import signal

WORKER_NAME = os.getenv("WORKER_NAME", "Unknown")
PORT = int(os.getenv("PORT", 5000))
HEARTBEAT_FILE = f"/home/lvwij/wokki20_chat/webpage/_private/heartbeats/worker_{WORKER_NAME}.heartbeat"
BETTERSTACK_TOKEN = os.getenv("BETTERSTACK_TOKEN", "")
BETTERSTACK_HOST = os.getenv("BETTERSTACK_HOST", "")

logger = logging.getLogger(__name__)
if BETTERSTACK_TOKEN:
    handler = LogtailHandler(
        source_token=BETTERSTACK_TOKEN,
        host=BETTERSTACK_HOST
    )
    logger.addHandler(handler)
    logger.setLevel(logging.INFO)

app = web.Application()
sio.attach(app)
shutdown_event = asyncio.Event()
accepting_connections = True

def log_exception_sync(exc_type, exc_value, exc_tb):
    tb_str = ''.join(traceback.format_exception(exc_type, exc_value, exc_tb))
    try:
        loop = asyncio.get_event_loop()
        if not loop.is_closed():
            loop.create_task(addMessageToLogs(tb_str, "ERROR"))
    except RuntimeError:
        pass
    if BETTERSTACK_TOKEN:
        logger.error(tb_str, extra={'worker': WORKER_NAME, 'port': PORT})

sys.excepthook = log_exception_sync

def handle_async_exception(loop, context):
    msg = context.get("exception")
    tb_str = ""
    if msg:
        tb_str = ''.join(traceback.format_exception(type(msg), msg, msg.__traceback__))
    else:
        tb_str = str(context)
    try:
        if not loop.is_closed():
            loop.create_task(addMessageToLogs(tb_str, "ERROR"))
    except RuntimeError:
        pass
    if BETTERSTACK_TOKEN:
        logger.error(tb_str, extra={'worker': WORKER_NAME, 'context': str(context)})

loop = asyncio.get_event_loop()
loop.set_exception_handler(handle_async_exception)

async def heartbeat():
    while not shutdown_event.is_set():
        try:
            with open(HEARTBEAT_FILE, "w") as f:
                f.write(str(int(time.time())))
        except Exception as e:
            await addMessageToLogs(f"Failed to write heartbeat: {e}", "ERROR")
            if BETTERSTACK_TOKEN:
                logger.error(f"Heartbeat failed: {e}", extra={'worker': WORKER_NAME})
        
        await asyncio.sleep(5)

@app.on_startup.append
async def startup(app):
    try:
        config.pool = await get_db_pool()
        config.typing_lock = asyncio.Lock()
        await addMessageToLogs(f"Worker {WORKER_NAME} started on port {PORT}", "INFO")
        if BETTERSTACK_TOKEN:
            logger.info(f"Worker started", extra={
                'worker': WORKER_NAME,
                'port': PORT,
                'event': 'startup'
            })
        
        app['heartbeat_task'] = asyncio.create_task(heartbeat())
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        await addMessageToLogs(''.join(traceback.format_exception(exc_type, exc_value, exc_tb)), "ERROR")
        raise

@app.on_cleanup.append
async def cleanup(app):
    try:
        global accepting_connections
        accepting_connections = False
        
        if BETTERSTACK_TOKEN:
            logger.warning(f"Worker shutting down", extra={
                'worker': WORKER_NAME,
                'port': PORT,
                'event': 'shutdown'
            })

        await asyncio.sleep(2)

        if 'heartbeat_task' in app:
            app['heartbeat_task'].cancel()
            try:
                await app['heartbeat_task']
            except asyncio.CancelledError:
                pass

        if config.pool:
            config.pool.close()
            await config.pool.wait_closed()

        await addMessageToLogs(f"Worker {WORKER_NAME} stopped", "INFO")
        shutdown_event.set()
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        try:
            loop = asyncio.get_event_loop()
            if not loop.is_closed():
                await addMessageToLogs(''.join(traceback.format_exception(exc_type, exc_value, exc_tb)), "ERROR")
        except RuntimeError:
            pass
        raise

async def health(request):
    if accepting_connections:
        return web.Response(
            text="OK",
            headers={'Access-Control-Allow-Origin': '*'}
        )
    else:
        return web.Response(
            status=503,
            text="Shutting down",
            headers={'Access-Control-Allow-Origin': '*'}
        )

app.router.add_get("/health", health)

async def main():
    runner = web.AppRunner(app)
    await runner.setup()
    site = web.TCPSite(runner, "0.0.0.0", PORT)
    await site.start()

    loop = asyncio.get_event_loop()

    def handle_sigterm():
        shutdown_event.set()

    for sig in (signal.SIGTERM, signal.SIGINT):
        loop.add_signal_handler(sig, handle_sigterm)

    try:
        await shutdown_event.wait()
    finally:
        await runner.cleanup()


if __name__ == "__main__" and "gunicorn" not in sys.modules:
    try:
        asyncio.run(main())
    except Exception:
        exc_type, exc_value, exc_tb = sys.exc_info()
        try:
            asyncio.run(addMessageToLogs(''.join(traceback.format_exception(exc_type, exc_value, exc_tb)), "ERROR"))
        except RuntimeError:
            pass
        raise
