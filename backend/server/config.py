import os
import re
from collections import defaultdict, deque
from dotenv import load_dotenv
import asyncio
import redis.asyncio as redis
import json

load_dotenv()

# --------------------
# Redis
# --------------------
redis_client = redis.Redis(host="localhost", port=6379, db=0, decode_responses=True)

# --------------------
# Local per-worker state
# --------------------
typing_lock = asyncio.Lock()
server_name = os.getenv("WORKER_NAME", "Unknown")
pool = None

# --------------------
# API Keys
# --------------------
API_KEY = os.getenv("API_KEY")
API_SECRET = os.getenv("API_SECRET")
SPOTIFY_CLIENT_ID = os.getenv("SPOTIFY_CLIENT_ID")
SPOTIFY_CLIENT_SECRET = os.getenv("SPOTIFY_CLIENT_SECRET")
BETTERSTACK_TOKEN = os.getenv("BETTERSTACK_TOKEN", "")
BETTERSTACK_HOST = os.getenv("BETTERSTACK_HOST", "")

# --------------------
# LiveKit / voice
# --------------------
LIVEKIT_BASE_URL = os.getenv("LIVEKIT_BASE_URL", "http://localhost:7880")

# --------------------
# Runtime / in-memory state
# --------------------
message_timestamps = defaultdict(lambda: deque())
user_current_room = {}
room = {}

# --------------------
# Rate limiting
# --------------------
MAX_MESSAGES = 10
TIME_WINDOW_SECONDS = 3
DISCONNECT_TIMEOUT = 30
TYPING_TIMEOUT = 10

# --------------------
# Regex / validation patterns
# --------------------
HEX_COLOR_RE = re.compile(r'^#([0-9A-Fa-f]{6})$')

# --------------------
# SID ↔ BOT
# --------------------
async def add_sid_to_bot(sid: str, bot_id: str):
    await redis_client.hset("sid_to_bot_id", sid, bot_id)

async def get_bot_id_from_sid(sid: str):
    return await redis_client.hget("sid_to_bot_id", sid)

async def remove_sid(sid: str):
    await redis_client.hdel("sid_to_bot_id", sid)
    
async def get_bot_sid_from_id(bot_id: str) -> str | None:
    all_sids = await redis_client.hkeys("sid_to_bot_id")
    for sid in all_sids:
        value = await redis_client.hget("sid_to_bot_id", sid)
        if value == bot_id:
            return sid
    return None

# --------------------
# USER ↔ SID
# --------------------
async def add_user_to_sid(user_id: str, sid: str):
    await redis_client.sadd(f"user_to_sid:{user_id}", sid)

async def get_sids_for_user(user_id: str):
    return await redis_client.smembers(f"user_to_sid:{user_id}")

async def remove_user_sid(user_id: str, sid: str):
    await redis_client.srem(f"user_to_sid:{user_id}", sid)
    
async def get_user_from_sid(sid: str) -> str | None:
    keys = await redis_client.keys("user_to_sid:*")
    for key in keys:
        sids = await redis_client.smembers(key)
        if sid in sids:
            return key.split("user_to_sid:")[1]
    return None

# --------------------
# TYPING USERS
# --------------------
async def add_typing_user(user_id: str, channel_id: str, server_id: str):
    key = f"typing_user:{server_id}:{channel_id}:{user_id}"
    await redis_client.set(key, 1, ex=TYPING_TIMEOUT)

async def remove_typing_user(user_id: str, channel_id: str, server_id: str):
    key = f"typing_user:{server_id}:{channel_id}:{user_id}"
    await redis_client.delete(key)

async def get_typing_users(channel_id: str, server_id: str):
    pattern = f"typing_user:{server_id}:{channel_id}:*"
    keys = await redis_client.keys(pattern)
    users = {key.split(":")[-1] for key in keys}
    return users

# --------------------
# CACHING
# --------------------
CHANNEL_CACHE_LIMIT = 100

async def cache_message(server_id: str, channel_id: str, message: dict):
    key = f"channel_messages:{server_id}:{channel_id}"
    await redis_client.rpush(key, json.dumps(message))
    await redis_client.ltrim(key, 0, CHANNEL_CACHE_LIMIT - 1)

async def get_cached_messages(server_id: str, channel_id: str, offset: int = 0, limit: int = 50):
    key = f"channel_messages:{server_id}:{channel_id}"
    total = await redis_client.llen(key)
    if offset >= total:
        return []
    start = offset
    end = min(offset + limit - 1, total - 1)
    cached = await redis_client.lrange(key, start, end)
    return [json.loads(msg) for msg in cached]

async def delete_cached_message(server_id: str, channel_id: str, message_id: str):
    key = f"channel_messages:{server_id}:{channel_id}"
    cached = await redis_client.lrange(key, 0, -1)
    for msg in cached:
        data = json.loads(msg)
        if data.get("id") == message_id:
            await redis_client.lrem(key, 0, msg)
            break

async def get_cached_users(server_id: str):
    key = f"server_users:{server_id}"
    cached = await redis_client.lrange(key, 0, -1)
    return [json.loads(msg) for msg in cached]

async def cache_users(server_id: str, users: list):
    key = f"server_users:{server_id}"
    await redis_client.delete(key)
    for user in users:
        await redis_client.rpush(key, json.dumps(user))

# --------------------
# LOCKS
# --------------------
async def acquire_user_lock(user_id: str, sid: str, expire: int = 60):
    return await redis_client.set(f"user_disconnect_lock:{user_id}", sid, nx=True, ex=expire)

async def release_user_lock(user_id: str):
    await redis_client.delete(f"user_disconnect_lock:{user_id}")

# --------------------
# BOTS
# --------------------
async def save_command_id(user_id: str, command_id: str, command: str):
    data = {"user_id": user_id, "command": command}
    await redis_client.hset("command_id_to_user_id", command_id, json.dumps(data))
    
async def get_command_id(command_id: str) -> dict | None:
    data = await redis_client.hget("command_id_to_user_id", command_id)
    if data is None:
        return None
    await redis_client.hdel("command_id_to_user_id", command_id)
    return json.loads(data)