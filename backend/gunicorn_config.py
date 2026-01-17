import os

bind = "0.0.0.0:5000"
workers = 4
worker_class = "aiohttp.GunicornWebWorker"

worker_names = ["Ignis", "Aqua", "Terra", "Ventus"]

def post_fork(server, worker):
    idx = worker.pid % len(worker_names)
    worker_name = worker_names[idx]
    os.environ["WORKER_NAME"] = worker_name
    server.log.info(f"Worker {worker.pid} assigned name: {worker_name}")
