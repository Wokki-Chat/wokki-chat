from datetime import datetime, timezone
from dateutil import parser
import server.config as config
import server.sio_instance as sio_instance
import aiomysql
from server.helpers.bot_helpers import get_bot_info_from_id, verify_bot_token

async def verify_access_token(cur, access_token):
    await cur.execute('SELECT user_id, access_token_expires_at FROM user_tokens WHERE access_token = %s', (access_token,))
    row = await cur.fetchone()
    if not row:
        return None

    user_id = row['user_id']
    expires_at = row['access_token_expires_at']

    if isinstance(expires_at, str):
        expires_at = parser.parse(expires_at)

    if expires_at.tzinfo is None:
        expires_at = expires_at.replace(tzinfo=timezone.utc)

    now = datetime.now(timezone.utc)
    if expires_at < now:
        return None

    return user_id

async def get_user_premium_status(cur, user_id):
    await cur.execute('SELECT premium_expires_at, premium FROM users WHERE id = %s', (user_id,))
    row = await cur.fetchone()
    if not row:
        return False 

    premium = row.get('premium')
    if premium == 0:
        return False

    premium_expires_at = row.get('premium_expires_at')
    if premium_expires_at is None:
        return True

    if isinstance(premium_expires_at, str):
        premium_expires_at = parser.parse(premium_expires_at)
    if premium_expires_at.tzinfo is None:
        premium_expires_at = premium_expires_at.replace(tzinfo=timezone.utc)

    now = datetime.now(timezone.utc)
    return premium_expires_at > now

async def broadcast_user_update(user_id, is_bot=False):
    if not is_bot:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                user_info = await get_user_info_from_id(cur, user_id)
                if not user_info:
                    return
                
                await sio_instance.sio.emit('user_updated', user_info)
                print(f"[user_updated] Broadcasted for user {user_id}")
                return
    if is_bot:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                bot_info = await get_bot_info_from_id(cur, user_id)
                if not bot_info:
                    return
                
                await sio_instance.sio.emit('user_updated', bot_info)
                print(f"[user_updated] Broadcasted for bot {user_id}")
                return
            


async def get_user_info_from_id(cur, user_id):
    query = """
        SELECT u.id, u.username, u.status, u.profile_picture,
               t.tag_name, t.tag_icon, t.created_at
        FROM users u
        LEFT JOIN tags t ON u.id = t.user_id
        WHERE u.id = %s
    """
    await cur.execute(query, (user_id,))
    rows = await cur.fetchall()

    if not rows:
        return None

    user = {
        "id": str(rows[0]["id"]),
        "username": rows[0]["username"],
        "status": rows[0]["status"],
        "profile_picture": rows[0]["profile_picture"],
        "premium": await get_user_premium_status(cur, user_id),
        "bot": False,
        "tags": []
    }

    for row in rows:
        if row["tag_name"]:
            user["tags"].append({
                "tag_name": row["tag_name"],
                "tag_icon": row["tag_icon"],
                "created_at": row["created_at"].isoformat() if row["created_at"] else None
            })

    return user

async def is_user_friends_with(cur, user_id, friend_id):
    await cur.execute("SELECT 1 FROM friends WHERE user_id = %s AND friend_id = %s LIMIT 1", (user_id, friend_id))
    row1 = await cur.fetchone()
    
    await cur.execute("SELECT 1 FROM friends WHERE user_id = %s AND friend_id = %s LIMIT 1", (friend_id, user_id))
    row2 = await cur.fetchone()
    
    return row1 is not None and row2 is not None


async def get_user_info(sid, data):
    access_token = data.get('access_token')
    bot_token = data.get('bot_token')
    requested_user_id = data.get('user_id')
    requested_bot_id = data.get('bot_id')

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:

            caller_user_id = None
            caller_bot_id = None

            if bot_token:
                caller_bot_id = await verify_bot_token(cur, bot_token)
                if not caller_bot_id:
                    await sio_instance.sio.emit('user_info', {'error': 'Invalid bot token'}, to=sid)
                    return
            elif access_token:
                caller_user_id = await verify_access_token(cur, access_token)
                if not caller_user_id:
                    await sio_instance.sio.emit('user_info', {'error': 'Invalid access token'}, to=sid)
                    return
            else:
                await sio_instance.sio.emit('user_info', {'error': 'No authentication provided'}, to=sid)
                return

            if requested_user_id:
                user_info = await get_user_info_from_id(cur, requested_user_id)
                if not user_info:
                    await sio_instance.sio.emit('user_info', {'error': 'User not found'}, to=sid)
                    return
                await sio_instance.sio.emit('user_info', user_info, to=sid)
                return


            elif requested_bot_id:
                bot_info = await get_bot_info_from_id(cur, requested_bot_id)
                if not bot_info:
                    await sio_instance.sio.emit('user_info', {'error': 'Bot not found'}, to=sid)
                    return
                await sio_instance.sio.emit('user_info', bot_info, to=sid)
                return

            if caller_user_id:
                user_info = await get_user_info_from_id(cur, caller_user_id)
                if not user_info:
                    await sio_instance.sio.emit('user_info', {'error': 'User not found'}, to=sid)
                    return
                await sio_instance.sio.emit('user_info', user_info, to=sid)
                return

            elif caller_bot_id:
                bot_info = await get_bot_info_from_id(cur, caller_bot_id)
                if not bot_info:
                    await sio_instance.sio.emit('user_info', {'error': 'Bot not found'}, to=sid)
                    return
                await sio_instance.sio.emit('user_info', bot_info, to=sid)
                return

            await sio_instance.sio.emit('user_info', {'error': 'User/bot not found'}, to=sid)