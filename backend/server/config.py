import os
import re
from collections import defaultdict, deque
from dotenv import load_dotenv
import asyncio
import redis.asyncio as redis

load_dotenv()

# --------------------
# Redis
# --------------------
redis_client = redis.Redis(host="localhost", port=6379, db=0, decode_responses=True)

# --------------------
# Local per-worker state
# --------------------
typing_lock = asyncio.Lock()

pool = None

# --------------------
# API Keys
# --------------------
API_KEY = os.getenv("API_KEY")
API_SECRET = os.getenv("API_SECRET")

# --------------------
# LiveKit / voice
# --------------------
LIVEKIT_BASE_URL = os.getenv("LIVEKIT_BASE_URL", "http://localhost:7880")

# --------------------
# Runtime / in-memory state
# --------------------
user_message_timestamps = defaultdict(lambda: deque())
bot_message_timestamps = defaultdict(lambda: deque())
user_current_room = {}
room = {}

# --------------------
# Rate limiting
# --------------------
MAX_MESSAGES = 10
TIME_WINDOW_SECONDS = 3

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
    keys = await redis_client.keys("sid_to_bot_id:*")
    for key in keys:
        sid = await redis_client.hget(key, bot_id)
        if sid:
            return key.split("sid_to_bot_id:")[1]
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
    key = f"typing_users:{server_id}:{channel_id}"
    await redis_client.sadd(key, user_id)

async def remove_typing_user(user_id: str, channel_id: str, server_id: str):
    key = f"typing_users:{server_id}:{channel_id}"
    await redis_client.srem(key, user_id)

async def get_typing_users():
    users = set()
    keys = await redis_client.keys("typing_users:*")
    for key in keys:
        members = await redis_client.smembers(key)
        users.update(members)
    return users