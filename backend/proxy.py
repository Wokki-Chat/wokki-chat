import asyncio
import json
import logging
import os
import time
from enum import Enum
from aiohttp import web, ClientSession, ClientWebSocketResponse, WSMsgType, ClientTimeout
import psutil

logging.basicConfig(
    level=logging.INFO,
    format="[proxy] %(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S"
)
log = logging.getLogger("proxy")

IN_DOCKER = os.path.exists("/.dockerenv")

WORKERS = (
    [
        ("ignis",  5001),
        ("aqua",   5002),
        ("terra",  5003),
        ("ventus", 5004),
    ]
    if IN_DOCKER else
    [
        ("127.0.0.1", 5001),
        ("127.0.0.1", 5002),
        ("127.0.0.1", 5003),
        ("127.0.0.1", 5004),
    ]
)

PROXY_PORT = 5000
HEALTH_CHECK_INTERVAL = 5
HEALTH_CHECK_TIMEOUT = ClientTimeout(total=2)
STARTUP_HEALTH_CHECK_INTERVAL = 1
STARTUP_TIMEOUT = 60


class WorkerStatus(Enum):
    ALIVE = "alive"
    DRAINING = "draining"
    DEAD = "dead"


class WsSession:
    def __init__(self, ws_server: web.WebSocketResponse):
        self.ws_server = ws_server
        self.migrating = False

    async def migrate(self):
        if self.migrating:
            return
        self.migrating = True
        try:
            await self.ws_server.close(code=1012, message=b"Worker restarting, reconnecting...")
        except Exception:
            pass


class WorkerState:
    def __init__(self, host: str, port: int):
        self.host = host
        self.port = port
        self.status = WorkerStatus.DEAD
        self.active_connections = 0
        self.total_requests = 0
        self.last_checked = 0.0
        self.sessions: set[WsSession] = set()

    @property
    def alive(self):
        return self.status == WorkerStatus.ALIVE

    @property
    def base_url(self):
        return f"http://{self.host}:{self.port}"

    def add_session(self, session: WsSession):
        self.sessions.add(session)
        self.active_connections += 1

    def remove_session(self, session: WsSession):
        self.sessions.discard(session)
        self.active_connections -= 1

    async def drain_sessions(self):
        log.warning(f"Worker {self.host}:{self.port} draining {len(self.sessions)} sessions")
        for session in list(self.sessions):
            await session.migrate()


workers: list[WorkerState] = [WorkerState(host, port) for host, port in WORKERS]


def get_best_worker() -> WorkerState | None:
    alive = [w for w in workers if w.alive]
    if not alive:
        return None
    return min(alive, key=lambda w: w.active_connections)


async def check_worker_health(worker: WorkerState, session: ClientSession):
    previous_status = worker.status
    try:
        async with session.get(f"{worker.base_url}/health", timeout=HEALTH_CHECK_TIMEOUT) as resp:
            if resp.status == 200:
                worker.status = WorkerStatus.ALIVE
            elif resp.status == 503:
                worker.status = WorkerStatus.DRAINING
            else:
                worker.status = WorkerStatus.DEAD
    except Exception as e:
        if worker.status == WorkerStatus.ALIVE:
            log.warning(f"Worker {worker.host}:{worker.port} health check failed: {e}")
        worker.status = WorkerStatus.DEAD

    worker.last_checked = time.time()

    if previous_status == WorkerStatus.ALIVE and worker.status != WorkerStatus.ALIVE:
        log.warning(f"Worker {worker.host}:{worker.port} went {worker.status.value}, draining sessions")
        asyncio.create_task(worker.drain_sessions())
    elif previous_status != WorkerStatus.ALIVE and worker.status == WorkerStatus.ALIVE:
        log.info(f"Worker {worker.host}:{worker.port} is now alive")


async def health_monitor():
    async with ClientSession() as session:
        while True:
            await asyncio.gather(*[check_worker_health(w, session) for w in workers])
            await asyncio.sleep(HEALTH_CHECK_INTERVAL)


async def wait_for_workers():
    mode = "Docker" if IN_DOCKER else "local"
    log.info(f"Starting in {mode} mode, waiting for workers...")
    deadline = time.time() + STARTUP_TIMEOUT
    async with ClientSession() as session:
        while time.time() < deadline:
            await asyncio.gather(*[check_worker_health(w, session) for w in workers])
            alive = [w for w in workers if w.alive]
            if alive:
                log.info(f"{len(alive)}/{len(workers)} workers ready: {[f'{w.host}:{w.port}' for w in alive]}")
                return
            log.info(f"No workers ready yet, retrying in {STARTUP_HEALTH_CHECK_INTERVAL}s...")
            await asyncio.sleep(STARTUP_HEALTH_CHECK_INTERVAL)

    log.warning(f"No workers became ready within {STARTUP_TIMEOUT}s, starting anyway")


async def forward_ws(ws_server: web.WebSocketResponse, ws_client: ClientWebSocketResponse):
    async def client_to_worker():
        async for msg in ws_server:
            if msg.type == WSMsgType.TEXT:
                await ws_client.send_str(msg.data)
            elif msg.type == WSMsgType.BINARY:
                await ws_client.send_bytes(msg.data)
            elif msg.type == WSMsgType.PING:
                await ws_client.ping(msg.data)
            elif msg.type == WSMsgType.PONG:
                await ws_client.pong(msg.data)
            elif msg.type in (WSMsgType.CLOSE, WSMsgType.CLOSING, WSMsgType.ERROR):
                break
        await ws_client.close()

    async def worker_to_client():
        async for msg in ws_client:
            if msg.type == WSMsgType.TEXT:
                await ws_server.send_str(msg.data)
            elif msg.type == WSMsgType.BINARY:
                await ws_server.send_bytes(msg.data)
            elif msg.type == WSMsgType.PING:
                await ws_server.ping(msg.data)
            elif msg.type == WSMsgType.PONG:
                await ws_server.pong(msg.data)
            elif msg.type in (WSMsgType.CLOSE, WSMsgType.CLOSING, WSMsgType.ERROR):
                break
        await ws_server.close()

    task_a = asyncio.create_task(client_to_worker())
    task_b = asyncio.create_task(worker_to_client())

    done, pending = await asyncio.wait([task_a, task_b], return_when=asyncio.FIRST_COMPLETED)

    for task in pending:
        task.cancel()
        try:
            await task
        except (asyncio.CancelledError, Exception):
            pass


async def handle_websocket(request: web.Request) -> web.WebSocketResponse:
    ws_server = web.WebSocketResponse(heartbeat=None)
    await ws_server.prepare(request)

    log.info(f"WS connect from {request.remote} path={request.rel_url}")

    session = WsSession(ws_server)
    
    forward_headers = {
        k: v for k, v in request.headers.items()
        if k.lower() not in ("host", "upgrade", "connection", "sec-websocket-key",
                              "sec-websocket-version", "sec-websocket-extensions")
    }

    while True:
        worker = get_best_worker()
        if worker is None:
            log.error("No alive workers available to handle WS request")
            await ws_server.close(code=1013, message=b"No workers available")
            return ws_server

        worker.add_session(session)
        try:
            async with ClientSession() as http_session:
                try:
                    ws_client = await http_session.ws_connect(
                        f"{worker.base_url}{request.rel_url}",
                        headers=forward_headers,
                        heartbeat=None,
                        autoclose=False,
                        autoping=False,
                    )
                except Exception as e:
                    log.error(f"Failed to connect to worker {worker.host}:{worker.port}: {e}")
                    worker.remove_session(session)
                    await asyncio.sleep(1)
                    continue

                try:
                    await forward_ws(ws_server, ws_client)
                finally:
                    await ws_client.close()
        finally:
            worker.remove_session(session)

        if ws_server.closed:
            break

        log.info(f"Reconnecting WS client {request.remote} to a new worker...")
        await asyncio.sleep(0.5)

    log.info(f"WS fully closed from {request.remote}")
    return ws_server


async def handle_http(request: web.Request, worker: WorkerState) -> web.Response:
    worker.active_connections += 1
    worker.total_requests += 1
    try:
        async with ClientSession() as session:
            headers = {k: v for k, v in request.headers.items() if k != "Host"}
            data = await request.read()
            async with session.request(
                request.method,
                f"{worker.base_url}{request.rel_url}",
                headers=headers,
                data=data,
            ) as resp:
                body = await resp.read()
                return web.Response(body=body, status=resp.status, headers=dict(resp.headers))
    except Exception as e:
        log.error(f"HTTP proxy error to worker {worker.host}:{worker.port}: {e}")
        return web.Response(text="Bad Gateway", status=502)
    finally:
        worker.active_connections -= 1


async def stats_handler(_: web.Request) -> web.Response:
    cpu_percent = psutil.cpu_percent(interval=0.5)
    virtual_mem = psutil.virtual_memory()

    data = {
        "workers": [
            {
                "host": w.host,
                "port": w.port,
                "status": w.status.value,
                "active_connections": w.active_connections,
                "total_requests": w.total_requests,
                "last_checked_seconds_ago": round(time.time() - w.last_checked, 1) if w.last_checked else None,
            }
            for w in workers
        ],
        "total_alive": sum(1 for w in workers if w.alive),
        "total_connections": sum(w.active_connections for w in workers),
        "total_requests": sum(w.total_requests for w in workers),
        "system": {
            "cpu_percent": cpu_percent,
            "ram_used_mb": virtual_mem.used // (1024 * 1024),
            "ram_total_mb": virtual_mem.total // (1024 * 1024),
            "ram_percent": virtual_mem.percent,
        },
    }
    return web.Response(
        text=json.dumps(data, indent=2),
        content_type="application/json",
        headers={"Access-Control-Allow-Origin": "*"},
    )

async def handler(request: web.Request) -> web.StreamResponse:
    if request.path == "/proxy/stats":
        return await stats_handler(request)

    worker = get_best_worker()
    if worker is None:
        log.error("No alive workers available to handle request")
        return web.Response(text="No workers available", status=503)

    if request.headers.get("Upgrade", "").lower() == "websocket":
        return await handle_websocket(request)

    return await handle_http(request, worker)


async def on_startup(app: web.Application):
    await wait_for_workers()
    app["health_monitor"] = asyncio.create_task(health_monitor())


async def on_cleanup(app: web.Application):
    app["health_monitor"].cancel()
    try:
        await app["health_monitor"]
    except asyncio.CancelledError:
        pass


app = web.Application()
app.on_startup.append(on_startup)
app.on_cleanup.append(on_cleanup)
app.router.add_route("*", "/{tail:.*}", handler)

if __name__ == "__main__":
    web.run_app(app, host="0.0.0.0", port=PROXY_PORT)