import os
import inspect
from datetime import datetime

logs_file = "/home/lvwij/wokki20_chat/webpage/_private/logs/logs.txt"

def addMessageToLogs(message, type):
    caller_frame = inspect.stack()[1]
    caller_file = os.path.basename(caller_frame.filename)
    caller_line = caller_frame.lineno
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    
    worker_pid = os.getpid()
    
    log_line = f"[Worker PID: {worker_pid}] [{timestamp}] [{caller_file}:{caller_line}] [{type}] -> {message}\n"
    
    with open(logs_file, "a") as f:
        f.write(log_line)
