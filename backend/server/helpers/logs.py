import os
import inspect
from datetime import datetime

logs_file = "/home/lvwij/wokki20_chat/webpage/_private/logs/logs.txt"
MAX_LINES = 500

def addMessageToLogs(message, type):
    caller_frame = inspect.stack()[1]
    caller_file = os.path.basename(caller_frame.filename)
    caller_line = caller_frame.lineno
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    
    worker_pid = os.getpid()
    
    log_line = f"[Worker PID: {worker_pid}] [{timestamp}] [{caller_file}:{caller_line}] [{type}] -> {message}\n"
    
    if os.path.exists(logs_file):
        with open(logs_file, "r") as f:
            lines = f.readlines()
    else:
        lines = []
    
    if len(lines) >= MAX_LINES:
        lines = lines[-(MAX_LINES-1):]
    
    lines.append(log_line)
    
    with open(logs_file, "w") as f:
        f.writelines(lines)
