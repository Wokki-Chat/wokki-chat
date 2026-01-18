import subprocess
import sys
import time
import socket
import re

WORKERS = [
    ("wokki_chat_ignis.service", 5001),
    ("wokki_chat_aqua.service", 5002),
    ("wokki_chat_terra.service", 5003),
    ("wokki_chat_ventus.service", 5004)
]

DELAY = 2
MONITOR_TIME = 30

def is_port_free(port):
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        try:
            s.bind(("0.0.0.0", port))
            return True
        except OSError:
            return False

def get_tracebacks(service):
    result = subprocess.run(
        ["journalctl", "-u", service, "--since", "1 minute ago", "--no-pager", "-o", "cat"],
        capture_output=True, text=True
    )
    logs = result.stdout.splitlines()

    tracebacks = []
    in_traceback = False
    tb_block = []

    for line in logs:
        if "traceback" in line.lower() or in_traceback:
            in_traceback = True
            tb_block.append(line)
            if re.match(r'^\w*Error:.*', line):
                tracebacks.append("\n".join(tb_block))
                tb_block = []
                in_traceback = False

    return tracebacks

def restart_and_monitor(service, port):
    print(f"Stopping {service}...")
    stop = subprocess.run(["sudo", "systemctl", "stop", service])
    if stop.returncode != 0:
        print(f"Failed to stop {service}, aborting rolling restart.")
        return False

    for _ in range(10):
        if is_port_free(port):
            break
        time.sleep(1)
    else:
        print(f"Port {port} still in use, aborting.")
        return False

    time.sleep(DELAY)

    print(f"Starting {service}...")
    start = subprocess.run(["sudo", "systemctl", "start", service])
    if start.returncode != 0:
        print(f"Failed to start {service}, aborting rolling restart.")
        return False

    print(f"{service} started. Monitoring for {MONITOR_TIME} seconds...")
    for _ in range(MONITOR_TIME):
        tracebacks = get_tracebacks(service)
        if tracebacks:
            print(f"Errors detected in {service} logs, aborting rolling restart.")
            for tb in tracebacks:
                print("\n" + tb + "\n")
            return False
        time.sleep(1)

    print(f"{service} is healthy.\n")
    return True

if __name__ == "__main__":
    for s, p in WORKERS:
        if not restart_and_monitor(s, p):
            sys.exit(1)
    print("All workers restarted successfully and are healthy!")
