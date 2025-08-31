from server.config import user_to_sid, pending_disconnects, sid_to_bot_id, typing_lock, typing_users, user_current_room
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

                tasks = pending_disconnects.pop(user_id, [])
                if tasks:
                    for t in tasks:
                        t.cancel()
                    await asyncio.gather(*tasks, return_exceptions=True)
                    await addMessageToLogs(f"User {user_id} reconnected", "INFO")


                await cur.execute("UPDATE users SET status = 'online' WHERE id = %s AND status_manually_set = FALSE", (user_id,))
                await conn.commit()
                await broadcast_user_update(user_id)

                await addMessageToLogs(f"User {user_id} connected", "INFO")
                await sio_instance.sio.emit('user_connected', {'user_id': user_id, 'server_id': server_id}, to=sid)
                
                if not user_to_sid.get(user_id, []): 
                    user_to_sid[user_id] = []
                
                if sid not in user_to_sid[user_id]:
                    user_to_sid[user_id].append(sid)


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
                
                sid_to_bot_id[sid] = bot_id
            
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
                await addMessageToLogs(f"Emited user list to sid: {sid}, server id: {server_id}, bot id: {bot_id}", "INFO")
                return
            
async def handle_disconnect(sid):
    user_id = None
    for uid, sid_list in user_to_sid.items():
        if sid in sid_list:
            user_id = uid
            break
    
    bot_id = sid_to_bot_id.get(sid)
    
    if not user_id and not bot_id:
        await addMessageToLogs(f"User not found for sid {sid}", "INFO")
        return

    if user_id:
        await addMessageToLogs(f"Disconnecting user {user_id}", "INFO")
        user_to_sid[user_id].remove(sid)
        if len(user_to_sid[user_id]) == 0:
            del user_to_sid[user_id]

        room = user_current_room.get(user_id)
        if room:
            await sio_instance.sio.leave_room(sid, room)
            await sio_instance.sio.emit('user_disconnected', {'user_id': user_id}, room=room)
            await addMessageToLogs(f"User {user_id} disconnected from room {room}", "INFO")
            user_current_room.pop(user_id, None)

        task = asyncio.create_task(handle_delayed_disconnect(user_id, sid))
        pending_disconnects.setdefault(user_id, []).append(task)
        await addMessageToLogs(f"Added user {user_id} to pending disconnects", "INFO")
        return
    
    if bot_id:
        async with config.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute("UPDATE bots SET status = 'offline' WHERE id = %s", (bot_id,))
                await conn.commit()
                await broadcast_user_update(bot_id, is_bot=True)
        
        sid_to_bot_id.pop(sid, None)
        await addMessageToLogs(f"Bot {bot_id} disconnected", "INFO")
        return


async def handle_delayed_disconnect(user_id, sid):
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
            if user_id in typing_users:
                typing_users.discard(user_id)
                await addMessageToLogs(f"Removed user {user_id} from typing_users", "INFO")
                await sio_instance.sio.emit('users_typing', {'user_ids': list(typing_users), 'channel_id': None})

        if user_id in user_to_sid:
            for sid_key in user_to_sid[user_id]:
                pass
            user_to_sid.pop(user_id, None)

        pending_disconnects.pop(user_id, None)

    except asyncio.CancelledError:
        await addMessageToLogs(f"Disconnect for user {user_id} cancelled due to reconnect", "INFO")
        return