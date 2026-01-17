import subprocess
import time
import os
import signal

def get_worker_pids():
    """Return all Gunicorn worker PIDs (excluding master)."""
    result = subprocess.run(
        ["pgrep", "-f", "gunicorn main:app"], capture_output=True, text=True
    )
    pids = [int(pid) for pid in result.stdout.split() if pid.strip()]

    master_pid = None
    for pid in pids:
        try:
            with open(f"/proc/{pid}/status") as f:
                for line in f:
                    if line.startswith("PPid:"):
                        ppid = int(line.split()[1])
                        if ppid == 1:
                            master_pid = pid
                            break
        except FileNotFoundError:
            continue

    workers = [p for p in pids if p != master_pid]
    return sorted(workers), master_pid

def rolling_restart():
    workers, master = get_worker_pids()
    print(f"Master PID: {master}")
    print(f"Workers before restart: {workers}")

    for old_worker in workers:
        print(f"Killing worker {old_worker}...")
        try:
            os.kill(old_worker, signal.SIGTERM)
        except ProcessLookupError:
            print(f"Worker {old_worker} already gone.")
            continue

        while True:
            new_workers, _ = get_worker_pids()
            if any(w not in workers for w in new_workers):
                print(f"New worker spawned: {new_workers}")
                workers = new_workers
                break
            time.sleep(0.5)

        time.sleep(1)

    print("Rolling restart complete")

if __name__ == "__main__":
    rolling_restart()
