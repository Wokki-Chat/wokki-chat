import subprocess
import sys
import time
import socket
import re
import argparse
import shutil

WORKERS = {
    "Ignis": ("wokki_chat_ignis.service", 5001),
    "Aqua": ("wokki_chat_aqua.service", 5002),
    "Terra": ("wokki_chat_terra.service", 5003),
    "Ventus": ("wokki_chat_ventus.service", 5004)
}

DELAY = 2
MONITOR_TIME = 30
PORT_CHECK_RETRIES = 10

RED = "\033[91m"
GREEN = "\033[92m"
YELLOW = "\033[93m"
RESET = "\033[0m"

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

def print_progress_bar(seconds_left, total_seconds, width=30, prefix=""):
    term_width = shutil.get_terminal_size((80, 20)).columns
    bar_width = min(width, term_width - 25)
    filled = int(((total_seconds - seconds_left) / total_seconds) * bar_width)
    empty = bar_width - filled
    bar = f"[{'=' * filled}{' ' * empty}] {int(seconds_left)}s"
    print(f"\r{prefix}{bar}", end="", flush=True)

def format_time(seconds):
    minutes = int(seconds) // 60
    sec = int(seconds) % 60
    return f"{minutes}m {sec}s"

def print_remaining_time(seconds, color=YELLOW):
    print(f"{color}Estimated total remaining time: {format_time(seconds)}{RESET}", flush=True)

def restart_and_monitor(service, port, total_remaining_time):
    print(f"{YELLOW}Stopping {service}...{RESET}")
    stop = subprocess.run(["sudo", "systemctl", "stop", service])
    if stop.returncode != 0:
        print(f"{RED}Failed to stop {service}, aborting rolling restart.{RESET}")
        return False

    for _ in range(PORT_CHECK_RETRIES):
        if is_port_free(port):
            break
        time.sleep(1)
        total_remaining_time[0] -= 1
        print(f"{YELLOW}Estimated total remaining time: {total_remaining_time[0]:.0f}s{RESET}")

    else:
        print(f"{RED}Port {port} still in use, aborting.{RESET}")
        return False

    time.sleep(DELAY)
    total_remaining_time[0] -= DELAY
    print(f"{YELLOW}Estimated total remaining time: {total_remaining_time[0]:.0f}s{RESET}")

    print(f"\n{YELLOW}Starting {service}...{RESET}")
    start = subprocess.run(["sudo", "systemctl", "start", service])
    if start.returncode != 0:
        print(f"{RED}Failed to start {service}, aborting rolling restart.{RESET}")
        return False

    print(f"{YELLOW}{service} started. Monitoring for {MONITOR_TIME} seconds...{RESET}")

    total_updates = MONITOR_TIME * 10
    for i in range(total_updates):
        tracebacks = get_tracebacks(service)
        if tracebacks:
            print(f"\n{RED}Errors detected in {service} logs, aborting rolling restart.{RESET}")
            for tb in tracebacks:
                print("\n" + tb + "\n")
            return False

        seconds_left = MONITOR_TIME - (i / 10)
        print_progress_bar(seconds_left, MONITOR_TIME, prefix=f"{YELLOW}Monitoring {service}: {RESET}")
        total_remaining_time[0] -= 0.1
        print_remaining_time(total_remaining_time[0])
        time.sleep(0.1)

    print(f"\n{GREEN}{service} is healthy.{RESET}\n")
    return True

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("-w", "--workers", help="Comma-separated list of workers to restart (e.g. Terra,Ventus)")
    args = parser.parse_args()

    if args.workers:
        selected_workers = []
        for name in args.workers.split(","):
            name = name.strip().capitalize()
            if name in WORKERS:
                selected_workers.append(WORKERS[name])
            else:
                print(f"{RED}Unknown worker: {name}{RESET}")
        if not selected_workers:
            print(f"{RED}No valid workers specified. Exiting.{RESET}")
            sys.exit(1)
    else:
        selected_workers = WORKERS.values()

    total_est_time = 0
    for s, p in selected_workers:
        total_est_time += PORT_CHECK_RETRIES + DELAY + MONITOR_TIME
    total_remaining_time = [total_est_time]

    print(f"{YELLOW}Estimated total time for rolling restart: ~{format_time(total_remaining_time[0])}{RESET}\n")

    for s, p in selected_workers:
        if not restart_and_monitor(s, p, total_remaining_time):
            sys.exit(1)

    print(f"\n{GREEN}All workers restarted successfully and are healthy!{RESET}")