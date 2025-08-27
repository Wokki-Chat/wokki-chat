import asyncio
from aiohttp import web
import server.sio_events
from server.sio_instance import sio
from server.db import get_db_pool
import server.config as config
import server.sio_config

app = web.Application()

sio.attach(app)

@app.on_startup.append
async def startup(app):
    config.pool = await get_db_pool()
    config.typing_lock = asyncio.Lock()

@app.on_cleanup.append
async def cleanup(app):
    config.pool.close()
    await config.pool.wait_closed()

if __name__ == "__main__":
    web.run_app(app, host="0.0.0.0", port=5000)
