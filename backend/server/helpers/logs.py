import os
import inspect
import logging
import aiofiles
from datetime import datetime
from server.config import server_name
from server.config import BETTERSTACK_TOKEN, BETTERSTACK_HOST

logs_file = "/home/lvwij/wokki20_chat/webpage/_private/logs/logs.txt"
MAX_LINES = 500

betterstack_logger = logging.getLogger("betterstack")
if BETTERSTACK_TOKEN:
    from logtail import LogtailHandler
    handler = LogtailHandler(
        source_token=BETTERSTACK_TOKEN,
        host=BETTERSTACK_HOST
    )
    betterstack_logger.addHandler(handler)
    betterstack_logger.setLevel(logging.DEBUG)

async def addMessageToLogs(message, type):
    caller_frame = inspect.stack()[1]
    caller_file = os.path.basename(caller_frame.filename)
    caller_line = caller_frame.lineno
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    log_line = f"[Worker Name: {server_name}] [{timestamp}] [{caller_file}:{caller_line}] [{type}] -> {message}\n"
    
    lines = []
    if os.path.exists(logs_file):
        async with aiofiles.open(logs_file, "r") as f:
            lines = await f.readlines()
    
    if len(lines) >= MAX_LINES:
        lines = lines[-(MAX_LINES-1):]
    
    lines.append(log_line)
    
    async with aiofiles.open(logs_file, "w") as f:
        await f.writelines(lines)
    
    if BETTERSTACK_TOKEN:
        log_data = {
            'worker': server_name,
            'file': caller_file,
            'line': caller_line,
            'log_type': type
        }
        
        if type == "ERROR":
            betterstack_logger.error(message, extra=log_data)
        elif type == "WARNING" or type == "WARN":
            betterstack_logger.warning(message, extra=log_data)
        elif type == "INFO":
            betterstack_logger.info(message, extra=log_data)
        elif type == "DEBUG":
            betterstack_logger.debug(message, extra=log_data)
        else:
            betterstack_logger.info(message, extra=log_data)
