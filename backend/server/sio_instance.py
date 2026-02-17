from server.helpers.logs import addMessageToLogs
import traceback
import functools
import socketio
import os
from dotenv import load_dotenv
load_dotenv()

REDIS_URL = f'redis://{os.getenv("REDIS_HOST", "localhost")}:{os.getenv("REDIS_PORT", 6379)}'
sio = socketio.AsyncServer(
    async_mode='aiohttp',
    cors_allowed_origins='*',
    client_manager=socketio.AsyncRedisManager(REDIS_URL)
)

def sio_safe(event, *on_args, **on_kwargs):
    """
    Safe wrapper for @sio.on() which will send
    any errors to the betterstack_logger.
    Only supports async.
    """
    def decorator(fn):
        @functools.wraps(fn)
        async def wrapper(*handler_args, **handler_kwargs):
            try:
                return await fn(*handler_args, **handler_kwargs)
            except Exception as exc:
                data = handler_args[1] if len(handler_args) > 1 else None
                tb_str = "".join(traceback.format_exception(type(exc), exc, exc.__traceback__))
                msg = (f'Error in event "{event}" with data "{data}":\n{tb_str}')

                await addMessageToLogs(msg, "ERROR")
                raise

        return sio.on(event, *on_args, **on_kwargs)(wrapper)
    return decorator

sio.safe = sio_safe
