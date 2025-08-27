import subprocess
import time
import os
import signal

APP_NAME = "wokki20_chat"

def get_gunicorn_pids():
    result = subprocess.run(["pgrep", "-f", f"gunicorn main:app"], capture_output=True, text=True)
    pids = [int(pid) for pid in result.stdout.split() if pid.strip()]
    return sorted(pids)

def rolling_restart():
    original_pids = get_gunicorn_pids()
    print(f"Found PIDs: {original_pids}")
    killed_pids = []

    for pid in original_pids:
        if pid in killed_pids:
            continue
        print(f"Killing PID {pid}...")
        try:
            os.kill(pid, signal.SIGTERM)
        except ProcessLookupError:
            print(f"PID {pid} already gone.")
            continue
        killed_pids.append(pid)

        while True:
            current_pids = get_gunicorn_pids()
            new_workers = [p for p in current_pids if p not in original_pids]
            if new_workers:
                print(f"New worker detected: {new_workers}")
                break
            time.sleep(1)
        time.sleep(1)

if __name__ == "__main__":
    rolling_restart()
