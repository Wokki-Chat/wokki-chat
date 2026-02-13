from datetime import datetime, timezone, timedelta
from dateutil import parser
from functools import wraps
import server.config as config
import server.sio_instance as sio_instance
import aiomysql
from server.helpers.bot_helpers import get_bot_info_from_id, is_bot_in_server, verify_bot_token
from server.helpers.logs import addMessageToLogs
import aiohttp
import asyncio

async def verify_access_token(cur, access_token):
    await cur.execute(
        'SELECT user_id, access_token_expires_at FROM user_tokens WHERE access_token = %s',
        (access_token,)
    )
    row = await cur.fetchone()

    if not row:
        await addMessageToLogs(f"verify_access_token: invalid token {access_token}", "INFO")
        return None

    await addMessageToLogs(f"verify_access_token: raw row -> {row}", "INFO")

    user_id = row['user_id']
    expires_at = row['access_token_expires_at']

    if isinstance(expires_at, str):
        expires_at = parser.parse(expires_at)

    if expires_at.tzinfo is None:
        expires_at = expires_at.replace(tzinfo=timezone.utc)

    now = datetime.now(timezone.utc)

    if expires_at < now:
        await cur.execute(
            'DELETE FROM user_tokens WHERE user_id = %s AND access_token = %s',
            (user_id, access_token)
        )
        await addMessageToLogs(f"verify_access_token: expired token for user {user_id}, deleted", "INFO")
        return None

    await addMessageToLogs(f"verify_access_token: valid for user {user_id}, expires at {expires_at}", "INFO")
    return user_id

def auth_required(server_required = True, allow_bots = True): # problem: it doesn't send correct name upon error!
    from server.helpers.server_helpers import is_user_in_server
    """
    Decorator to validate tokens.
    1. Input function must be async.
    2. Passes `metadata` containing `is_bot`, `server_id` and `account_id` (user/bot id) into the input function.
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
                async with conn.cursor(aiomysql.DictCursor) as cur:
                    if bot_token:
                        if not allow_bots:
                            await addMessageToLogs("Bots not allowed", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Bots not allowed'}, to=sid
                            )
                            return
                        bot_id = await verify_bot_token(cur, bot_token)
                        if not bot_id:
                            await addMessageToLogs(f"Invalid bot token, bot token: {bot_token}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Invalid bot token'}, to=sid
                            )
                            return
                        if server_required and not await is_bot_in_server(cur, bot_id, server_id):
                            await addMessageToLogs(f"Bot not in server, bot id: {bot_id}, server id: {server_id}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Bot not in server'}, to=sid
                            )
                            return
                        is_bot = True
                        account_id = bot_id
                    else:
                        if not access_token:
                            await addMessageToLogs("Missing access token", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Missing access token'}, to=sid
                            )
                            return
                        user_id = await verify_access_token(cur, access_token)
                        if not user_id:
                            await addMessageToLogs(f"Invalid access token, access token: {access_token}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'Invalid access token'}, to=sid
                            )
                            return
                        if server_required and server_id and not await is_user_in_server(cur, user_id, server_id) :
                            await addMessageToLogs(f"User not in server, user id: {user_id}, server id: {server_id}", "INFO")
                            await sio_instance.sio.emit(
                                'error', {'success': False, 'error': 'User not in server'}, to=sid
                            )
                            return
                        account_id = user_id

            metadata = {
                'is_bot': is_bot,
                'account_id': account_id
            }
            return await func(sid, metadata, data, *args, **kwargs)
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

async def get_user_rooms(cur, user_id, notification_only=False):
    query = "SELECT server_id FROM server_members WHERE user_id = %s"
    await cur.execute(query, (user_id,))
    rows = await cur.fetchall()
    rooms = [f"{'server_notif' if notification_only else 'server'}:{r['server_id']}" for r in rows]
    
    query = "SELECT contact_id FROM contact_users WHERE user_id = %s"
    await cur.execute(query, (user_id,))
    rows = await cur.fetchall()
    rooms.extend([f"{'contact_notif' if notification_only else 'contact'}:{r['contact_id']}" for r in rows])
    
    return rooms
    
async def get_bot_rooms(cur, bot_id):
    query = "SELECT server_id FROM server_members WHERE bot_id = %s"
    await cur.execute(query, (bot_id,))
    rows = await cur.fetchall()
    return [f"server:{r['server_id']}" for r in rows]

async def broadcast_user_update(user_id, is_bot=False):
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if is_bot:
                info = await get_bot_info_from_id(cur, user_id)
                if not info:
                    await addMessageToLogs(f"Bot not found, bot id: {user_id}", "INFO")
                    return
                rooms = await get_bot_rooms(cur, user_id)
            else:
                info = await get_user_info_from_id(cur, user_id)
                if not info:
                    await addMessageToLogs(f"User not found, user id: {user_id}", "INFO")
                    return
                rooms = await get_user_rooms(cur, user_id)
            
            await addMessageToLogs(f"broadcast_user_update: rooms -> {rooms}", "INFO")
            
            await asyncio.gather(*[
                sio_instance.sio.emit('user_updated', info, room=room)
                for room in rooms
            ])

async def broadcast_widgets(user_id):
    try:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                widgets = await get_user_widgets(cur, user_id)
                if widgets:
                    await broadcast_widget_update(user_id, widgets=widgets)
    except Exception as e:
        await addMessageToLogs(f"Error broadcasting widgets for user {user_id}: {e}")


async def broadcast_widget_update(user_id, widget_name=None, widgets=None):
    try:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                if widgets is None:
                    widgets = await get_user_widgets(cur, user_id, widget_name)
                    if not widgets:
                        return
                
                user_rooms = await get_user_rooms(cur, user_id)
                
                update_data = {
                    "user_id": user_id,
                    **widgets
                }
                
                for u_room in user_rooms:
                    await sio_instance.sio.emit('user_widget_updated', update_data, room=u_room)
                    
    except Exception as e:
        await addMessageToLogs(f"Error broadcasting widgets for user {user_id}: {e}")

async def is_user_online(cur, user_id):
    """Check if user is currently online"""
    query = "SELECT COUNT(*) as count FROM user_sessions WHERE user_id = %s AND is_active = 1"
    await cur.execute(query, (user_id,))
    result = await cur.fetchone()
    return result and result["count"] > 0


async def get_user_widgets(cur, user_id, widget_name=None):
    query = """
        SELECT widget_name, widget_access_token, widget_refresh_token, show_on_profile, widget_access_token_valid_until
        FROM profile_widgets
        WHERE user_id = %s
    """
    await cur.execute(query, (user_id,))
    widgets = await cur.fetchall() or []

    if not widgets:
        return {}
    
    await cur.execute(
        "SELECT connection_user_name, connection_name FROM user_connections WHERE user_id = %s",
        (user_id,)
    )
    connections = await cur.fetchall() or []
    connection_map = {c["connection_name"]: c["connection_user_name"] for c in connections}

    result = {}
    for widget in widgets:
        if widget_name and widget["widget_name"] != widget_name:
            continue

        if widget["widget_name"] == "Spotify" and widget["show_on_profile"] == 1:
            is_online = await is_user_online(cur, user_id)
            if not is_online:
                continue
                
            try:
                now = datetime.now(timezone.utc)
                token_valid_until = widget.get("widget_access_token_valid_until")

                if token_valid_until and token_valid_until.tzinfo is None:
                    token_valid_until = token_valid_until.replace(tzinfo=timezone.utc)

                if not token_valid_until or token_valid_until <= now:
                    access_token, new_refresh_token, valid_until = await refresh_spotify_token(widget["widget_refresh_token"])
                    if access_token:
                        await cur.execute(
                            """
                            UPDATE profile_widgets
                            SET widget_access_token = %s, widget_refresh_token = %s, widget_access_token_valid_until = %s
                            WHERE user_id = %s AND widget_name = 'Spotify'
                            """,
                            (access_token, new_refresh_token or widget["widget_refresh_token"], valid_until, user_id)
                        )
                else:
                    access_token = widget["widget_access_token"]

                async with aiohttp.ClientSession() as session:
                    async with session.get(
                        "https://api.spotify.com/v1/me/player",
                        headers={"Authorization": f"Bearer {access_token}"}
                    ) as resp:
                        if resp.status == 200:
                            result["Spotify"] = await resp.json()
            except Exception:
                pass
        elif widget["widget_name"] == "GitHub" and widget["show_on_profile"] == 1:
            try:
                github_username = connection_map.get("GitHub")
                access_token = widget.get("widget_access_token")
                if not github_username or not access_token:
                    continue

                async with aiohttp.ClientSession() as session:
                    graphql_query = {
                        "query": f"""
                        {{
                          user(login: "{github_username}") {{
                            contributionsCollection {{
                              contributionCalendar {{
                                totalContributions
                                weeks {{
                                  contributionDays {{
                                    date
                                    contributionCount
                                    color
                                  }}
                                }}
                              }}
                            }}
                          }}
                        }}
                        """
                    }

                    async with session.post(
                        "https://api.github.com/graphql",
                        json=graphql_query,
                        headers={"Authorization": f"Bearer {access_token}"}
                    ) as resp:
                        if resp.status == 200:
                            data = await resp.json()
                            result["GitHub"] = data
                        else:
                            result["GitHub"] = {"error": f"GitHub API returned status {resp.status}"}

            except Exception as e:
                result["GitHub"] = {"error": str(e)}
        else:
            result[widget["widget_name"]] = True

    return result

async def get_user_connections(cur, user_id):
    query = "SELECT * FROM user_connections WHERE user_id = %s"
    await cur.execute(query, (user_id,))
    rows = await cur.fetchall()
    connections = []
    for row in rows:
        connection = {
            "id": row["id"],
            "connection_type": row["connection_name"],
            "connection_name": row["connection_user_name"],
            "connection_user_url": row["connection_user_url"],
        }
        connections.append(connection)
    return connections

async def get_user_info_from_id(cur, user_id, load_widgets=True):
    query = """
        SELECT u.id, u.username, u.status, u.profile_picture, u.created_at, u.bio, u.profile_color_primary, u.profile_color_accent, u.nickname, u.profile_banner,
               t.tag_name, t.tag_icon, t.created_at
        FROM users u
        LEFT JOIN tags t ON u.id = t.user_id
        WHERE u.id = %s
    """
    await cur.execute(query, (user_id,))
    rows = await cur.fetchall()

    if not rows:
        return None

    resolved_user_id = rows[0]["id"]
    haspremium = await get_user_premium_status(cur, resolved_user_id)

    user = {
        "id": str(resolved_user_id),
        "username": rows[0]["username"],
        "display_name": rows[0]["nickname"],
        "status": rows[0]["status"],
        "profile_picture": rows[0]["profile_picture"],
        "profile_banner": rows[0]["profile_banner"],
        "premium": haspremium,
        "bot": False,
        "tags": [],
        "staff": await is_user_staff(cur, resolved_user_id),
        "developer": await is_user_developer(cur, resolved_user_id),
        "created_at": str(rows[0]["created_at"].isoformat()),
        "bio": rows[0]["bio"],
        "profile_color_primary": rows[0]["profile_color_primary"] if haspremium else None,
        "profile_color_accent": rows[0]["profile_color_accent"] if haspremium else None,
        "widgets": {},
        "connections": await get_user_connections(cur, resolved_user_id)
    }

    for row in rows:
        if row["tag_name"]:
            user["tags"].append({
                "tag_name": row["tag_name"],
                "tag_icon": row["tag_icon"],
                "created_at": row["created_at"].isoformat() if row["created_at"] else None
            })
    
    if load_widgets:
        asyncio.create_task(broadcast_widgets(resolved_user_id))

    return user

async def refresh_spotify_token(refresh_token):
    async with aiohttp.ClientSession() as session:
        async with session.post(
            "https://accounts.spotify.com/api/token",
            data={
                "grant_type": "refresh_token",
                "refresh_token": refresh_token,
                "client_id": config.SPOTIFY_CLIENT_ID,
                "client_secret": config.SPOTIFY_CLIENT_SECRET
            }
        ) as resp:
            if resp.status != 200:
                return None, None, None

            data = await resp.json()
            new_access_token = data.get("access_token")
            new_refresh_token = data.get("refresh_token")
            expires_in = data.get("expires_in")
            valid_until = datetime.now(timezone.utc) + timedelta(seconds=expires_in) if expires_in else None
            return new_access_token, new_refresh_token, valid_until

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
                    await addMessageToLogs(f"Invalid bot token for user info, bot token: {bot_token}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Invalid bot token', 'req_id': req_id}, to=sid)
                    return
            elif access_token:
                caller_user_id = await verify_access_token(cur, access_token)
                if not caller_user_id:
                    await addMessageToLogs(f"Invalid access token for user info, access token: {access_token}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Invalid access token', 'req_id': req_id}, to=sid)
                    return
            else:
                await addMessageToLogs(f"No authentication provided for user info", "INFO")
                await sio_instance.sio.emit('user_info', {'error': 'No authentication provided', 'req_id': req_id}, to=sid)
                return

            if requested_user_id:
                user_info = await get_user_info_from_id(cur, requested_user_id)
                if not user_info:
                    await addMessageToLogs(f"User not found for user info, user id: {requested_user_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'User not found', 'req_id': req_id}, to=sid)
                    return
                await addMessageToLogs(f"User found for user info, user id: {requested_user_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': user_info, 'req_id': req_id}, to=sid)
                return


            elif requested_bot_id:
                bot_info = await get_bot_info_from_id(cur, requested_bot_id)
                if not bot_info:
                    await addMessageToLogs(f"Bot not found for user info, bot id: {requested_bot_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Bot not found', 'req_id': req_id}, to=sid)
                    return
                await addMessageToLogs(f"Bot found for user info, bot id: {requested_bot_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': bot_info, 'req_id': req_id}, to=sid)
                return

            if caller_user_id:
                user_info = await get_user_info_from_id(cur, caller_user_id)
                if not user_info:
                    await addMessageToLogs(f"User not found for user info, user id: {caller_user_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'User not found', 'req_id': req_id}, to=sid)
                    return
                await addMessageToLogs(f"User found for user info, user id: {caller_user_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': user_info, 'req_id': req_id}, to=sid)
                return

            elif caller_bot_id:
                bot_info = await get_bot_info_from_id(cur, caller_bot_id)
                if not bot_info:
                    await addMessageToLogs(f"Bot not found for user info, bot id: {caller_bot_id}", "INFO")
                    await sio_instance.sio.emit('user_info', {'error': 'Bot not found', 'req_id': req_id}, to=sid)
                    return
                await addMessageToLogs(f"Bot found for user info, bot id: {caller_bot_id}", "INFO")
                await sio_instance.sio.emit('user_info', {'info': bot_info, 'req_id': req_id}, to=sid)
                return

            await addMessageToLogs(f"User/bot not found for user info", "INFO")
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

