from datetime import datetime, timezone
from dateutil import parser
from functools import wraps
import server.config as config
import server.sio_instance as sio_instance
import aiomysql
from server.helpers.bot_helpers import get_bot_info_from_id, is_bot_in_server, verify_bot_token
from server.helpers.logs import addMessageToLogs

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
        await cur.execute('DELETE FROM user_tokens WHERE user_id = %s AND access_token = %s', (user_id, access_token))
        addMessageToLogs(f"Deleted expired access token for user {user_id}", "INFO")
        return None

    addMessageToLogs(f"Verified access token for user {user_id}", "INFO")
    return user_id
    
def auth_required(allow_bots=True):
    from server.helpers.server_helpers import is_user_in_server
    """
    Decorator to validate tokens.
    1. Input function must be async.
    2. Passes `is_bot` and `account_id` (user/bot id) into the input function.
    """
    def decorator(func):
        @wraps(func)
        async def wrapper(sid, data, *args, **kwargs):
            bot_token = data.get('bot_token')
            access_token = data.get('access_token')
            server_id = data.get('server_id')

            is_bot = False
            account_id = None

            async with config.pool.acquire() as conn:
                async with conn.cursor() as cur:
                    if bot_token:
                        if not allow_bots:
                            addMessageToLogs("Bots not allowed", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Bots not allowed'}, to=sid
                            )
                            return
                        bot_id = await verify_bot_token(cur, bot_token)
                        if not bot_id:
                            addMessageToLogs(f"Invalid bot token, bot token: {bot_token}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Invalid bot token'}, to=sid
                            )
                            return
                        if not await is_bot_in_server(cur, bot_id, server_id):
                            addMessageToLogs(f"Bot not in server, bot id: {bot_id}, server id: {server_id}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Bot not in server'}, to=sid
                            )
                            return
                        is_bot = True
                        account_id = bot_id
                    else:
                        if not access_token:
                            addMessageToLogs("Missing access token", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Missing access token'}, to=sid
                            )
                            return
                        user_id = await verify_access_token(cur, access_token)
                        if not user_id:
                            addMessageToLogs(f"Invalid access token, access token: {access_token}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Invalid access token'}, to=sid
                            )
                            return
                        if not await is_user_in_server(cur, user_id, server_id):
                            addMessageToLogs(f"User not in server, user id: {user_id}, server id: {server_id}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'User not in server'}, to=sid
                            )
                            return
                        account_id = user_id

            return await func(sid, data, *args, is_bot=is_bot, account_id=account_id, **kwargs)

        return wrapper
    return decorator

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
                    addMessageToLogs(f"User not found, user id: {user_id}", "INFO")
                    return
                
                await sio_instance.sio.emit('user_updated', user_info)
                addMessageToLogs(f"Broadcasted for user {user_id}", "INFO")
                return
    if is_bot:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                bot_info = await get_bot_info_from_id(cur, user_id)
                if not bot_info:
                    addMessageToLogs(f"Bot not found, bot id: {user_id}", "INFO")
                    return
                
                await sio_instance.sio.emit('user_updated', bot_info)
                addMessageToLogs(f"Broadcasted for bot {user_id}", "INFO")
                return
            


async def get_user_info_from_id(cur, user_id):
    query = """
        SELECT u.id, u.username, u.status, u.profile_picture, u.created_at, u.bio,
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
        "tags": [],
        "staff": await is_user_staff(cur, user_id),
        "developer": await is_user_developer(cur, user_id),
        "created_at": str(rows[0]["created_at"].isoformat()),
        "bio": rows[0]["bio"]
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
    req_id = data.get('req_id')

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:

            caller_user_id = None
            caller_bot_id = None

            if bot_token:
                caller_bot_id = await verify_bot_token(cur, bot_token)
                if not caller_bot_id:
                    addMessageToLogs(f"Invalid bot token for user info, bot token: {bot_token}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Invalid bot token', 'req_id': req_id}, to=sid)
                    return
            elif access_token:
                caller_user_id = await verify_access_token(cur, access_token)
                if not caller_user_id:
                    addMessageToLogs(f"Invalid access token for user info, access token: {access_token}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Invalid access token', 'req_id': req_id}, to=sid)
                    return
            else:
                addMessageToLogs(f"No authentication provided for user info", "INFO")
                await sio_instance.sio.emit('user_info', {'error': 'No authentication provided', 'req_id': req_id}, to=sid)
                return

            if requested_user_id:
                user_info = await get_user_info_from_id(cur, requested_user_id)
                if not user_info:
                    addMessageToLogs(f"User not found for user info, user id: {requested_user_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'User not found', 'req_id': req_id}, to=sid)
                    return
                addMessageToLogs(f"User found for user info, user id: {requested_user_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': user_info, 'req_id': req_id}, to=sid)
                return


            elif requested_bot_id:
                bot_info = await get_bot_info_from_id(cur, requested_bot_id)
                if not bot_info:
                    addMessageToLogs(f"Bot not found for user info, bot id: {requested_bot_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Bot not found', 'req_id': req_id}, to=sid)
                    return
                addMessageToLogs(f"Bot found for user info, bot id: {requested_bot_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': bot_info, 'req_id': req_id}, to=sid)
                return

            if caller_user_id:
                user_info = await get_user_info_from_id(cur, caller_user_id)
                if not user_info:
                    addMessageToLogs(f"User not found for user info, user id: {caller_user_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'User not found', 'req_id': req_id}, to=sid)
                    return
                addMessageToLogs(f"User found for user info, user id: {caller_user_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': user_info, 'req_id': req_id}, to=sid)
                return

            elif caller_bot_id:
                bot_info = await get_bot_info_from_id(cur, caller_bot_id)
                if not bot_info:
                    addMessageToLogs(f"Bot not found for user info, bot id: {caller_bot_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Bot not found', 'req_id': req_id}, to=sid)
                    return
                addMessageToLogs(f"Bot found for user info, bot id: {caller_bot_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': bot_info, 'req_id': req_id}, to=sid)
                return

            addMessageToLogs(f"User/bot not found for user info", "INFO")
            await sio_instance.sio.emit('user_info', {'error': 'User/bot not found', 'req_id': req_id}, to=sid)
            return
            
async def is_user_staff(cur, user_id):
    await cur.execute("SELECT is_staff FROM users WHERE id = %s", (user_id,))
    row = await cur.fetchone()
    return row["is_staff"] == 1

async def is_user_developer(cur, user_id):
    await cur.execute("SELECT is_developer, is_staff FROM users WHERE id = %s", (user_id,))
    row = await cur.fetchone()
    return row["is_developer"] == 1

