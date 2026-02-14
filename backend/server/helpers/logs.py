import os
import inspect
import logging
from datetime import datetime
from server.config import server_name
from server.config import BETTERSTACK_TOKEN, BETTERSTACK_HOST

betterstack_logger = logging.getLogger("betterstack")
if BETTERSTACK_TOKEN:
    from logtail import LogtailHandler
    handler = LogtailHandler(
        source_token=BETTERSTACK_TOKEN,
        host=BETTERSTACK_HOST
    )
    betterstack_logger.addHandler(handler)
    betterstack_logger.setLevel(logging.DEBUG)

async def addMessageToLogs(message, level="INFO"):
    caller_frame = inspect.stack()[1]
    caller_file = os.path.basename(caller_frame.filename)
    caller_line = caller_frame.lineno
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_line = f"[Worker Name: {server_name}] [{timestamp}] [{caller_file}:{caller_line}] [{level}] -> {message}"
    
    if BETTERSTACK_TOKEN:
        log_data = {
            'worker': server_name,
            'file': caller_file,
            'line': caller_line,
            'log_type': level
        }
        
        try:
            if level == "ERROR":
                betterstack_logger.error(message, extra=log_data)
            elif level in ("WARNING", "WARN"):
                betterstack_logger.warning(message, extra=log_data)
            elif level == "DEBUG":
                betterstack_logger.debug(message, extra=log_data)
            else:
                betterstack_logger.info(message, extra=log_data)
        except Exception as e:
            print(f"[WARNING] Could not log to BetterStack: {e}")
            print(log_line)
    else:
        print(log_line)