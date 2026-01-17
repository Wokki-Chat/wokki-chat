import os
import inspect
from datetime import datetime
import asyncio
import aiofiles
from server.config import server_name

logs_file = "/home/lvwij/wokki20_chat/webpage/_private/logs/logs.txt"
MAX_LINES = 500

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
