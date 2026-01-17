import asyncio
from aiohttp import web, ClientSession, ClientWebSocketResponse, WSMsgType

WORKER_PORTS = [5001, 5002, 5003, 5004]
idx = 0

async def handler(request):
    global idx
    port = WORKER_PORTS[idx]
    idx = (idx + 1) % len(WORKER_PORTS)

    if request.headers.get("Upgrade", "").lower() == "websocket":
        ws_server = web.WebSocketResponse()
        await ws_server.prepare(request)

        async with ClientSession() as session:
            ws_client: ClientWebSocketResponse = await session.ws_connect(
                f'http://127.0.0.1:{port}{request.rel_url}'
            )

            async def forward_client_to_worker():
                async for msg in ws_server:
                    if msg.type == WSMsgType.TEXT:
                        await ws_client.send_str(msg.data)
                    elif msg.type == WSMsgType.BINARY:
                        await ws_client.send_bytes(msg.data)
                    elif msg.type == WSMsgType.CLOSE:
                        await ws_client.close()

            async def forward_worker_to_client():
                async for msg in ws_client:
                    if msg.type == WSMsgType.TEXT:
                        await ws_server.send_str(msg.data)
                    elif msg.type == WSMsgType.BINARY:
                        await ws_server.send_bytes(msg.data)
                    elif msg.type == WSMsgType.CLOSE:
                        await ws_server.close()

            await asyncio.gather(forward_client_to_worker(), forward_worker_to_client())
        return ws_server

    async with ClientSession() as session:
        method = request.method
        url = f"http://127.0.0.1:{port}{request.rel_url}"
        headers = {k: v for k, v in request.headers.items() if k != "Host"}
        data = await request.read()
        async with session.request(method, url, headers=headers, data=data) as resp:
            body = await resp.read()
            return web.Response(body=body, status=resp.status, headers=resp.headers)

app = web.Application()
app.router.add_route('*', '/{tail:.*}', handler)

if __name__ == "__main__":
    web.run_app(app, host="0.0.0.0", port=5000)
