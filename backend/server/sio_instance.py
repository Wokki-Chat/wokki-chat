from server.helpers.logs import BETTERSTACK_TOKEN, server_name, betterstack_logger, addMessageToLogs
import traceback
import functools
import socketio
import os

REDIS_URL = 'redis://localhost:6379'
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
                # sid = handler_args[0] if len(handler_args) > 0 else None
                data = handler_args[1] if len(handler_args) > 1 else None

                tb_str = "".join(
                    traceback.format_exception(type(exc), exc, exc.__traceback__)
                )

                tb = exc.__traceback__
                while tb.tb_next:
                    tb = tb.tb_next
                caller_file = os.path.basename(tb.tb_frame.f_code.co_filename)
                caller_line = tb.tb_lineno

                msg = (
                    f'Error in event "{event}" with data "{data}":\n{tb_str}'
                )

                if BETTERSTACK_TOKEN:
                    log_data = {
                        "worker": server_name,
                        "file": caller_file,
                        "line": caller_line,
                        "log_type": "ERROR",
                    }
                    betterstack_logger.error(msg, extra=log_data)
                else:
                    await addMessageToLogs(msg, "ERROR")

                raise

        return sio.on(event, *on_args, **on_kwargs)(wrapper)

    return decorator

sio.safe = sio_safe
