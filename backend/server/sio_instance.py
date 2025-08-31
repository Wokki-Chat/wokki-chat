import socketio
REDIS_URL = 'redis://localhost:6379'
sio = socketio.AsyncServer(
    async_mode='aiohttp',
    cors_allowed_origins='*',
    client_manager=socketio.AsyncRedisManager(REDIS_URL)
)
