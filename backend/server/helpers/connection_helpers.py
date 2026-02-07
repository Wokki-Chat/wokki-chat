import uuid
from server.config import typing_lock, user_current_room, add_user_to_sid, get_sids_for_user, remove_user_sid, get_user_from_sid, get_bot_sid_from_id, add_sid_to_bot, get_bot_id_from_sid, remove_sid, get_typing_users, remove_typing_user, redis_client, server_name, acquire_user_lock, release_user_lock, DISCONNECT_TIMEOUT
from server.helpers.user_helpers import verify_access_token, broadcast_user_update, get_user_premium_status, broadcast_user_widget_update
from server.helpers.server_helpers import is_user_in_server, get_member_ids_from_server
from server.helpers.bot_helpers import is_bot_in_server, verify_bot_token
import server.sio_instance as sio_instance
import server.config as config
import asyncio
import aiomysql
from server.helpers.logs import addMessageToLogs
from server.helpers.user_helpers import refresh_spotify_token
import aiohttp

async def poll_spotify(user_id, access_token=None, refresh_token=None):
    interval = 10 if access_token else 300
    key = f"spotify_polling:{user_id}"
    await redis_client.set(key, "1")

    try:
        while await redis_client.get(key) == "1":
            if access_token and refresh_token:
                new_access_token, new_refresh_token, valid_until = await refresh_spotify_token(refresh_token)
                headers = {"Authorization": f"Bearer {new_access_token}"}
                async with aiohttp.ClientSession() as session:
                    async with session.get("https://api.spotify.com/v1/me/player", headers=headers) as resp:
                        if resp.status == 200:
                            data = await resp.json()
                            await broadcast_user_widget_update(user_id, "Spotify")
                        elif resp.status == 204:
                            await broadcast_user_widget_update(user_id, "Spotify")
                        else:
                            await addMessageToLogs(f"Spotify API returned {resp.status} for user {user_id}", "INFO")
            else:
                await broadcast_user_widget_update(user_id, "Spotify")

            await asyncio.sleep(interval)
    except asyncio.CancelledError:
        await addMessageToLogs(f"Spotify polling for user {user_id} cancelled", "INFO")
    finally:
        await redis_client.delete(key)
        await asyncio.sleep(0)

async def handle_connect(sid, environ):
    query = environ.get('QUERY_STRING', '')
    params = dict(qc.split('=') for qc in query.split('&') if '=' in qc)
    access_token = params.get('access_token')
    bot_token = params.get('bot_token')
    server_id = params.get('server_id')

    if not access_token and not bot_token:
        await addMessageToLogs(f"Missing access_token or bot_token for sid {sid}", "INFO")
        await sio_instance.sio.disconnect(sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if access_token:
                user_id = await verify_access_token(cur, access_token)
                if not user_id:
                    await addMessageToLogs(f"Invalid access_token for sid {sid}", "INFO")
                    await sio_instance.sio.disconnect(sid)
                    return

                disconnect_key = f"user_disconnect:{user_id}"
                if await redis_client.get(disconnect_key):
                    await redis_client.delete(disconnect_key)
                    await addMessageToLogs(f"User {user_id} reconnected, canceled pending disconnect", "INFO")
                    await cur.execute(
                        "UPDATE users SET status = 'online' WHERE id = %s AND status_manually_set = 0",
                        (user_id,)
                    )
                    await conn.commit()
                    await broadcast_user_update(user_id)

                await cur.execute("""
                    SELECT widget_name, widget_access_token, widget_refresh_token, show_on_profile
                    FROM profile_widgets
                    WHERE user_id = %s
                """, (user_id,))
                widgets = await cur.fetchall()

                spotify_tokens = next(
                    ((w["widget_access_token"], w["widget_refresh_token"]) for w in widgets if w["widget_name"] == "Spotify" and w["show_on_profile"]),
                    None
                )

                if not await redis_client.exists(disconnect_key):
                    await cur.execute(
                        "UPDATE users SET status = 'online' WHERE id = %s AND status_manually_set = 0",
                        (user_id,)
                    )
                    await conn.commit()

                if spotify_tokens:
                    asyncio.create_task(poll_spotify(user_id, *spotify_tokens))
                else:
                    asyncio.create_task(poll_spotify(user_id))
                    
                await sio_instance.sio.enter_room(sid, f"user:{user_id}")

                await addMessageToLogs(f"User {user_id} connected", "INFO")
                await sio_instance.sio.emit('connected to server', {'server_name': server_name}, room=f"user:{user_id}")
                await sio_instance.sio.emit('user_connected', {'user_id': user_id, 'server_id': server_id}, room=f"user:{user_id}")
                await broadcast_user_update(user_id)

                sids = await get_sids_for_user(user_id)
                if sid not in sids:
                    await add_user_to_sid(user_id, sid)

                if server_id and not await is_user_in_server(cur, user_id, server_id):
                    await addMessageToLogs(f"User {user_id} not in server {server_id}", "INFO")
                    await sio_instance.sio.emit('user_not_in_server', {'user_id': user_id, 'server_id': server_id}, to=sid)
                return

            if bot_token:
                bot_id = await verify_bot_token(cur, bot_token)
                if not bot_id:
                    await addMessageToLogs(f"Invalid bot_token for sid {sid}", "INFO")
                    await sio_instance.sio.disconnect(sid)
                    return

                await cur.execute("UPDATE bots SET status = 'online' WHERE id = %s", (bot_id,))
                await conn.commit()
                await broadcast_user_update(bot_id, is_bot=True)

                await addMessageToLogs(f"Bot {bot_id} connected", "INFO")
                await sio_instance.sio.emit('bot_connected', {'bot_id': bot_id, 'server_id': server_id}, to=sid)
                await sio_instance.sio.emit('connected to server', {'server_name': server_name}, to=sid)

                await add_sid_to_bot(sid, bot_id)

                if server_id and not await is_bot_in_server(cur, bot_id, server_id):
                    await addMessageToLogs(f"Bot {bot_id} not in server {server_id}", "INFO")
                    await sio_instance.sio.emit('bot_not_in_server', {'bot_id': bot_id, 'server_id': server_id}, to=sid)
                    return

                await send_server_member_list(cur, bot_id, server_id, sid)
                return

async def send_server_member_list(cur, bot_id, server_id, sid):
    members = await get_member_ids_from_server(cur, server_id)
    users = []

    for m in members:
        if m["type"] == "user":
            if not await is_user_in_server(cur, m["id"], server_id):
                continue
            await cur.execute("SELECT id, username, status, profile_picture FROM users WHERE id = %s", (m["id"],))
            u = await cur.fetchone()
            if u:
                u["id"] = str(u["id"])
                u["premium"] = await get_user_premium_status(cur, m["id"])
                u["bot"] = False
                users.append(u)
        else:
            if not await is_bot_in_server(cur, m["id"], server_id):
                continue
            await cur.execute("SELECT id, name, status, profile_picture FROM bots WHERE id = %s", (m["id"],))
            b = await cur.fetchone()
            if b:
                b["id"] = str(b["id"])
                b["premium"] = False
                b["bot"] = True
                b["username"] = b.pop("name")
                users.append(b)

    await sio_instance.sio.emit("server_users", users, to=sid)
    await addMessageToLogs(f"Sent server user list to sid {sid} for server {server_id}", "INFO")

async def handle_disconnect(sid):
    user_id = await get_user_from_sid(sid)
    bot_id = await get_bot_id_from_sid(sid)

    if user_id:
        await addMessageToLogs(f"User {user_id} disconnected", "INFO")
        await remove_user_sid(user_id, sid)

        token = str(uuid.uuid4())
        disconnect_key = f"user_disconnect:{user_id}"
        await redis_client.set(disconnect_key, token, ex=DISCONNECT_TIMEOUT)

        room = user_current_room.get(user_id)
        if room:
            await sio_instance.sio.leave_room(sid, room)
            await sio_instance.sio.emit('user_disconnected', {'user_id': user_id}, room=room)
            user_current_room.pop(user_id, None)

        async with config.pool.acquire() as conn:
            async with conn.cursor() as cur:
                if await redis_client.get(disconnect_key) != token:
                    return
                
                await cur.execute(
                    "UPDATE users SET status = 'idle' WHERE id = %s AND status_manually_set = 0",
                    (user_id,)
                )
                await conn.commit()
                await addMessageToLogs(f"User {user_id} set to idle after disconnect", "INFO")
                await broadcast_user_update(user_id)

        current_token = await redis_client.get(disconnect_key)
        if current_token == token:
            asyncio.create_task(handle_delayed_disconnect(user_id, token))
            return
        return

    if bot_id:
        async with config.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("UPDATE bots SET status = 'offline' WHERE id = %s", (bot_id,))
                await conn.commit()
                await broadcast_user_update(bot_id, is_bot=True)

        await remove_sid(sid)
        await addMessageToLogs(f"Bot {bot_id} disconnected", "INFO")
        return

async def handle_delayed_disconnect(user_id, disconnect_token):
    disconnect_key = f"user_disconnect:{user_id}"

    await asyncio.sleep(DISCONNECT_TIMEOUT)

    async with config.pool.acquire() as conn:
        async with conn.cursor() as cur:
            if await redis_client.get(disconnect_key) != disconnect_token and set(await get_sids_for_user(user_id)) != set():
                await cur.execute(
                    "UPDATE users SET status = 'online' WHERE id = %s AND status_manually_set = 0",
                    (user_id,)
                )
                await conn.commit()
                await broadcast_user_update(user_id)
                await addMessageToLogs(f"User {user_id} reconnected before timeout, skipping offline, user set to online. Users sids: {await get_sids_for_user(user_id)}, disconnect token: {disconnect_token}. Expected token: {await redis_client.get(disconnect_key)}", "INFO")
                return
            
            await cur.execute(
                "UPDATE users SET status = 'offline' WHERE id = %s AND status_manually_set = 0",
                (user_id,)
            )
            await conn.commit()
            await addMessageToLogs(f"User {user_id} set to offline after disconnect timeout", "INFO")
            await broadcast_user_update(user_id)

    await redis_client.delete(disconnect_key)