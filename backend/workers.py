import os
import sys
import signal
import subprocess
import time

MAIN_SCRIPT = os.path.join(os.path.dirname(__file__), "main.py")

def worker_task(name):
    os.environ["WORKER_NAME"] = name
    proc = subprocess.Popen([sys.executable, MAIN_SCRIPT], env=os.environ)

    def handle_sigterm(signum, frame):
        print(f"{name} ({proc.pid}) shutting down...")
        proc.terminate()
        proc.wait()
        sys.exit(0)

    signal.signal(signal.SIGTERM, handle_sigterm)

    print(f"{name} ({proc.pid}) started main.py")

    while True:
        if proc.poll() is not None:
            print(f"{name} main.py exited unexpectedly, restarting...")
            proc = subprocess.Popen([sys.executable, MAIN_SCRIPT], env=os.environ)
            print(f"{name} ({proc.pid}) restarted main.py")
        time.sleep(2)

if __name__ == "__main__":
    name = sys.argv[1] if len(sys.argv) > 1 else f"Worker{os.getpid()}"
    worker_task(name)
