from server.config import typing_lock, user_current_room, add_user_to_sid, get_sids_for_user, remove_user_sid, get_user_from_sid, get_bot_sid_from_id, add_sid_to_bot, get_bot_id_from_sid, remove_sid, get_typing_users, remove_typing_user, redis_client
from server.helpers.user_helpers import verify_access_token, broadcast_user_update, get_user_premium_status
from server.helpers.server_helpers import is_user_in_server, get_member_ids_from_server
from server.helpers.bot_helpers import is_bot_in_server, verify_bot_token
import server.sio_instance as sio_instance
import server.config as config
import asyncio
import aiomysql
from server.helpers.logs import addMessageToLogs

async def handle_connect(sid, environ):
    query = environ.get('QUERY_STRING', '')
    params = dict(qc.split('=') for qc in query.split('&') if '=' in qc)
    access_token = params.get('access_token')
    server_id = params.get('server_id')
    bot_token = params.get('bot_token')

    if not access_token and not bot_token:
        await addMessageToLogs(f"Missing access_token or bot_token for sid {sid}", "INFO")
        await sio_instance.sio.disconnect(sid)
        return
    
    if access_token:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                user_id = await verify_access_token(cur, access_token)
                if not user_id:
                    await addMessageToLogs(f"Invalid access_token for sid {sid}", "INFO")
                    await sio_instance.sio.disconnect(sid)
                    return

                pending_key = f"user_disconnect:{user_id}"
                pending_task_id = await redis_client.get(pending_key)
                if pending_task_id:
                    await redis_client.delete(pending_key)
                    await addMessageToLogs(f"User {user_id} reconnected, canceled pending disconnect", "INFO")

                await cur.execute(
                    "UPDATE users SET status = 'online' WHERE id = %s AND status_manually_set = FALSE", (user_id,)
                )
                await conn.commit()
                await broadcast_user_update(user_id)

                await addMessageToLogs(f"User {user_id} connected", "INFO")
                await sio_instance.sio.emit('user_connected', {'user_id': user_id, 'server_id': server_id}, to=sid)
                
                sids = await get_sids_for_user(user_id)
                if sid not in sids:
                    await add_user_to_sid(user_id, sid)

                if not server_id:
                    return

                if not await is_user_in_server(cur, user_id, server_id):
                    await addMessageToLogs(f"User {user_id} not in server {server_id}", "INFO")
                    await sio_instance.sio.emit('user_not_in_server', {'user_id': user_id, 'server_id': server_id}, to=sid)
                    return
                
                return

    if bot_token:
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
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
                
                await redis_client.set(f"bot_sid:{bot_id}", sid)
                await addMessageToLogs(f"Stored bot_id {bot_id} for sid {sid} in redis ({await redis_client.get(f'bot_sid:{bot_id}')})", "INFO")
            
                if not server_id:
                    return

                if not await is_bot_in_server(cur, bot_id, server_id):
                    await addMessageToLogs(f"Bot {bot_id} not in server {server_id}", "INFO")
                    await sio_instance.sio.emit('bot_not_in_server', {'bot_id': bot_id, 'server_id': server_id}, to=sid)
                    return
                
                member_entries = await get_member_ids_from_server(cur, server_id)

                users = []
                for entry in member_entries:
                    if entry["type"] == "user":
                        uid = entry["id"]

                        if not await is_user_in_server(cur, uid, server_id):
                            continue

                        await cur.execute(
                            "SELECT id, username, status, profile_picture FROM users WHERE id = %s", (uid,)
                        )
                        user = await cur.fetchone()
                        
                        if user:
                            user["id"] = str(user["id"])
                            user["premium"] = await get_user_premium_status(cur, uid)
                            user["bot"] = False
                            users.append(user)
                    if entry["type"] == "bot":
                        uid = entry["id"]
                        
                        if not await is_bot_in_server(cur, uid, server_id):
                            continue

                        await cur.execute(
                            "SELECT id, name, status, profile_picture FROM bots WHERE id = %s", (uid,)
                        )
                        bot = await cur.fetchone()
                        
                        if bot:
                            bot["id"] = str(bot["id"])
                            bot["premium"] = False
                            bot["bot"] = True
                            bot["username"] = bot["name"]
                            del bot["name"]
                            users.append(bot)

                await sio_instance.sio.emit("server_users", users, to=sid)
                await addMessageToLogs(f"Emitted user list to sid: {sid}, server id: {server_id}, bot id: {bot_id}", "INFO")
                return

async def handle_disconnect(sid):
    user_id = await get_user_from_sid(sid)
    bot_id = await get_bot_id_from_sid(sid)
    
    if not user_id and not bot_id:
        await addMessageToLogs(f"User not found for sid {sid}", "INFO")
        return

    if user_id:
        await addMessageToLogs(f"Disconnecting user {user_id}", "INFO")
        await remove_user_sid(user_id, sid)

        room = user_current_room.get(user_id)
        if room:
            await sio_instance.sio.leave_room(sid, room)
            await sio_instance.sio.emit('user_disconnected', {'user_id': user_id}, room=room)
            await addMessageToLogs(f"User {user_id} disconnected from room {room}", "INFO")
            user_current_room.pop(user_id, None)

        task_key = f"user_disconnect:{user_id}"
        await redis_client.set(task_key, sid, ex=30)
        asyncio.create_task(handle_delayed_disconnect(user_id, sid))
        await addMessageToLogs(f"Stored pending disconnect for user {user_id} in Redis", "INFO")
        return

    if bot_id:
        async with config.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("UPDATE bots SET status = 'offline' WHERE id = %s", (bot_id,))
                await conn.commit()
                await broadcast_user_update(bot_id, is_bot=True)

        await remove_sid(bot_id, sid)
        await addMessageToLogs(f"Bot {bot_id} disconnected", "INFO")
        return


async def handle_delayed_disconnect(user_id, sid):
    task_key = f"user_disconnect:{user_id}"
    
    stored_sid = await redis_client.get(task_key)
    if not stored_sid or stored_sid != sid:
        await addMessageToLogs(f"Disconnect for user {user_id} skipped because user reconnected", "INFO")
        return

    try:
        async with config.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "UPDATE users SET status = 'idle' WHERE id = %s AND status_manually_set = FALSE",
                    (user_id,)
                )
                await conn.commit()
                await addMessageToLogs(f"User {user_id} set to idle (if not overridden manually)", "INFO")
                await broadcast_user_update(user_id)

        await asyncio.sleep(25)

        async with config.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "UPDATE users SET status = 'offline' WHERE id = %s AND status_manually_set = FALSE",
                    (user_id,)
                )
                await conn.commit()
                await addMessageToLogs(f"User {user_id} set to offline (if not overridden manually)", "INFO")
                await broadcast_user_update(user_id)

        async with typing_lock:
            typing_users = await get_typing_users()
            if user_id in typing_users:
                await remove_typing_user(user_id)
                await addMessageToLogs(f"Removed user {user_id} from typing_users", "INFO")
                await sio_instance.sio.emit('users_typing', {'user_ids': list(typing_users), 'channel_id': None})

            sids_left = await get_sids_for_user(user_id)
            for sid_key in sids_left:
                await remove_user_sid(user_id, sid_key)

        await redis_client.delete(task_key)
        await addMessageToLogs(f"Removed pending disconnect for user {user_id} from Redis", "INFO")

    except asyncio.CancelledError:
        await addMessageToLogs(f"Disconnect for user {user_id} cancelled due to reconnect", "INFO")
        return
