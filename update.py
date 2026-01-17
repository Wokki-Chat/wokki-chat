import subprocess
import time

SERVICE_NAME = "wokki20_chat.service"

def restart_service():
    print(f"Stopping {SERVICE_NAME}...")
    subprocess.run(["sudo", "systemctl", "stop", SERVICE_NAME], check=True)
    time.sleep(1)
    print(f"Starting {SERVICE_NAME}...")
    subprocess.run(["sudo", "systemctl", "start", SERVICE_NAME], check=True)
    print(f"{SERVICE_NAME} restarted successfully.")

if __name__ == "__main__":
    restart_service()
