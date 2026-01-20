from datetime import datetime, timezone
import json
import uuid
from server.config import get_sids_for_user, get_bot_sid_from_id, get_cached_users, cache_users
import server.sio_instance as sio_instance
from server.helpers.user_helpers import auth_required, get_user_info_from_id
from server.helpers.bot_helpers import get_bot_info_from_id, is_bot_in_server
import aiomysql
import re
import server.config as config
from server.helpers.logs import addMessageToLogs
import asyncio

async def server_permissions(cur, user_id, server_id, permission_identifier):
    u_owns_server_query = """
        SELECT created_by
        FROM servers
        WHERE id = %s AND created_by = %s AND server_type = 'normal'
    """
    await cur.execute(u_owns_server_query, (server_id, user_id))
    if await cur.fetchone():
        return True
    
    query = """
        SELECT role_id, permissions
        FROM server_roles
        WHERE server_id = %s
    """
    await cur.execute(query, (server_id,))
    rows = await cur.fetchall()

    permitted_role_ids = []
    for row in rows:
        permissions = row['permissions']

        if isinstance(permissions, str):
            permissions = json.loads(permissions)


        has_permission = False
        for perm in permissions:
            if perm.get('permission_identifier') == permission_identifier and perm.get('permission_value') is True:
                has_permission = True
                break

        if has_permission:
            permitted_role_ids.append(row['role_id'])

    if not permitted_role_ids:
        return False

    query = "SELECT role_id FROM user_server_roles WHERE server_id = %s AND user_id = %s"
    await cur.execute(query, (server_id, user_id))
    results = await cur.fetchall()

    if not results:
        return False

    user_roles = [
        row['role_id'] if isinstance(row, dict) else row[0]
        for row in results
    ]

    await addMessageToLogs(f"User {user_id} has roles {user_roles} for server {server_id}", "INFO")
    return bool(set(user_roles) & set(permitted_role_ids))

async def is_user_in_server(cur, user_id, server_id):
    await cur.execute(
        "SELECT 1 FROM server_members WHERE server_id = %s AND user_id = %s LIMIT 1",
        (server_id, user_id)
    )
    server = await cur.fetchone()
    await addMessageToLogs(f"User {user_id} in server {server_id}: {bool(server)}", "INFO")
    return bool(server)

async def send_server_notifications(cur, server_id, channel_id):
    query = """
        SELECT user_id FROM server_members WHERE server_id = %s
    """
    
    await cur.execute(query, (server_id,))
    result = await cur.fetchall()

    if not result:
        return

    members = [row['user_id'] if isinstance(row, dict) else row[0] for row in result]

    for user_id in members:
        if not user_id:
            continue
        
        user_sids = await get_sids_for_user(user_id)
        notification_id = str(uuid.uuid4())
        timestamp = datetime.now(timezone.utc)

        await cur.execute(
            "INSERT INTO notifications (notification_id, user_id, server_id, channel_id, created_at) VALUES (%s, %s, %s, %s, %s)",
            (notification_id, user_id, server_id, channel_id, timestamp)
        )
        
        if user_sids:
            for sid in user_sids:
                await sio_instance.sio.emit(
                    'server_notification',
                    {
                        'server_id': server_id,
                        'channel_id': channel_id,
                        'notification_id': notification_id,
                        'timestamp': timestamp.isoformat().replace('+00:00', 'Z')
                    },
                    to=sid
                )
                await addMessageToLogs(f"Server notification sent to user {user_id} in server {server_id}", "INFO")
                
async def get_member_ids_from_server(cur, server_id):
    await cur.execute(
        "SELECT user_id, bot_id FROM server_members WHERE server_id = %s",
        (server_id,)
    )
    rows = await cur.fetchall()

    if not rows:
        return []

    member_ids = []

    for row in rows:
        user_id = row.get('user_id') if isinstance(row, dict) else row[0]
        bot_id = row.get('bot_id') if isinstance(row, dict) else row[1]

        if user_id:
            member_ids.append({"id": user_id, "type": "user"})
        if bot_id:
            member_ids.append({"id": bot_id, "type": "bot"})

    return member_ids

async def get_server_users_info(cur, server_id):
    member_entries = await get_member_ids_from_server(cur, server_id)

    users = []
    for entry in member_entries:
        if entry["type"] == "user":
            uid = entry["id"]

            if not await is_user_in_server(cur, uid, server_id):
                continue
            
            user = await get_user_info_from_id(cur, uid)
            
            if user:
                users.append(user)
        if entry["type"] == "bot":
            uid = entry["id"]
            
            if not await is_bot_in_server(cur, uid, server_id):
                continue
            
            bot = await get_bot_info_from_id(cur, uid)
            
            if bot:
                users.append(bot)
                
    return users

@auth_required(server_required=True, allow_bots=False)
async def server_commands(sid, metadata, data):
    server_id = data.get('server_id')
    user_id = metadata.get('account_id')
            
    if not server_id:
        await addMessageToLogs("Missing required field server_id for server_commands", "INFO")
        await sio_instance.sio.emit('server_commands_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            member_entries = await get_member_ids_from_server(cur, server_id)

            bots = []
            for entry in member_entries:
                if entry["type"] != "bot":
                    continue
                uid = entry["id"]
                
                if not await is_bot_in_server(cur, uid, server_id):
                    await addMessageToLogs(f"Bot is not in server for server_commands, bot id: {uid}, server id: {server_id}", "INFO")
                    continue
                
                await cur.execute(
                    'SELECT id, name, profile_picture FROM bots WHERE id = %s', 
                    (uid,)
                )
                bot = await cur.fetchone()
                
                if bot:
                    bot["id"] = str(bot["id"])
                    bot["username"] = bot["name"]
                    del bot["name"]
                    bots.append(bot) 
            
            if not bots:
                await addMessageToLogs(f"No bots in server for server_commands, server id: {server_id}", "INFO")
                await sio_instance.sio.emit('server_commands_response', {'success': False, 'error': 'No bots in server'}, to=sid)
                return
            
            bots_with_commands = []
            for bot in bots:
                if not bot['id']:
                    continue
                await cur.execute(
                    'SELECT command, options FROM bot_commands WHERE bot_id = %s', 
                    (bot['id'],)
                )
                commands = await cur.fetchall()
                if commands:
                    bot['commands'] = commands
                    bots_with_commands.append(bot)
                    
                else:
                    bot['commands'] = []
                    bots_with_commands.append(bot)
                    
            await sio_instance.sio.emit('server_commands_response', {'success': True, 'bots': bots_with_commands}, to=sid)
            await addMessageToLogs(f"Emitted server_commands_response for server_commands, server id: {server_id}", "INFO")
            
@auth_required(server_required=True, allow_bots=False)
async def get_server_users(sid, metadata, data):
    server_id = data.get('server_id')
    user_id = metadata.get('account_id')

    if not all([server_id]):
        await addMessageToLogs(f"Missing required fields for get_messages", "INFO")
        await sio_instance.sio.emit('get_server_users_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
        
    cached_users = await get_cached_users(server_id)
    if cached_users:
        await sio_instance.sio.emit('server_users', cached_users, to=sid)
        
    async def get_users():
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                users = await get_server_users_info(cur, server_id)
                await cache_users(server_id, users)
                await sio_instance.sio.emit("server_users", users, to=sid)
                await addMessageToLogs(f"Emitted server_users to {user_id}, Sent {len(users)} users to {user_id}", "INFO")
        
    asyncio.create_task(get_users())
    
@auth_required(server_required=True, allow_bots=False)
async def command(sid, metadata, data):
    command = data.get('command')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    options_input = data.get('options') or {}
    bot_id = data.get('bot_id')

    user_id = metadata.get('account_id')

    if not all([command, server_id, channel_id, bot_id]):
        await addMessageToLogs("Missing required fields for command", "INFO")
        await sio_instance.sio.emit('command_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if not await is_bot_in_server(cur, bot_id, server_id):
                await addMessageToLogs(f"Bot is not in server for command, bot id: {bot_id}, server id: {server_id}", "INFO")
                await sio_instance.sio.emit('command_response', {'success': False, 'error': 'Bot is not in server'}, to=sid)
                return

            await cur.execute(
                'SELECT command, options FROM bot_commands WHERE command = %s AND bot_id = %s',
                (command, bot_id)
            )
            row = await cur.fetchone()
            if not row:
                await addMessageToLogs(f"Command not found for command, command: {command}, bot id: {bot_id}", "INFO")
                await sio_instance.sio.emit('command_response', {'success': False, 'error': 'Command not found'}, to=sid)
                return
        
            try:
                command_options = row['options']
                if isinstance(command_options, str):
                    command_options = json.loads(command_options)
            except Exception:
                command_options = []
            
            if command_options:
                for opt_def in command_options:
                    name = opt_def.get('option_name')
                    opt_type = opt_def.get('option_type')
                    required = opt_def.get('required', False)

                    if required and name not in options_input:
                        await addMessageToLogs(f"Missing required option '{name}' for command", "INFO")
                        await sio_instance.sio.emit('command_response', {
                            'success': False,
                            'error': f"Missing required option '{name}'"
                        }, to=sid)
                        return

                    if name in options_input:
                        val = options_input[name]

                        if opt_type == 'boolean':
                            if not isinstance(val, bool):
                                await addMessageToLogs(f"Option '{name}' must be boolean for command", "INFO")
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"Option '{name}' must be boolean"
                                }, to=sid)
                                return
                        elif opt_type == 'string':
                            if not isinstance(val, str):
                                await addMessageToLogs(f"Option '{name}' must be string for command", "INFO")
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"Option '{name}' must be string"
                                }, to=sid)
                                return
                        elif opt_type == 'number':
                            if not (isinstance(val, int) or isinstance(val, float)):
                                await addMessageToLogs(f"Option '{name}' must be number for command", "INFO")
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"Option '{name}' must be number"
                                }, to=sid)
                                return
                        elif opt_type == 'user':
                            if not isinstance(val, str):
                                await addMessageToLogs(f"Option '{name}' must be string for command", "INFO")
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"Option '{name}' must be string"
                                }, to=sid)
                                return

                            if not re.match(r'^[^#]+#\d+$', val):
                                await addMessageToLogs(f"User '{val}' must be in format username#user_id for command", "INFO")
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"User '{val}' must be in format username#user_id"
                                }, to=sid)
                                return

                            user_id_val = int(val.split('#')[1])
                            if not await is_user_in_server(cur, user_id_val, server_id):
                                await addMessageToLogs(f"User '{val}' is not in server for command", "INFO")
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"User '{val}' is not in server"
                                }, to=sid)
                                return
                        
            bot_sid = await get_bot_sid_from_id(bot_id)

            if bot_sid:
                await sio_instance.sio.emit('bot_command_received', {
                    'command': command,
                    'options': options_input,
                    'sent_by_user_id': user_id,
                    'server_id': server_id,
                    'channel_id': channel_id
                }, to=bot_sid)
                await addMessageToLogs(f"Emitted bot_command_received for command, command: {command}, bot id: {bot_id}, sid: {bot_sid}", "INFO")
            else:
                await addMessageToLogs(f"Bot not found for command, bot id: {bot_id}, bot sid: {bot_sid}", "INFO")
                await sio_instance.sio.emit('command_response', {'success': False, 'error': 'Bot not found'}, to=sid)
                return

            await sio_instance.sio.emit('command_response', {'success': True}, to=sid)
            await addMessageToLogs(f"Emitted command_response for command, command: {command}", "INFO")

@auth_required(server_required=True, allow_bots=False)
async def change_room(sid, metadata, data):
    user_id = metadata.get('account_id')
    new_server_id = data.get('server_id')
    new_channel_id = data.get('channel_id')
    
    if not new_server_id or not new_channel_id:
        await addMessageToLogs("Missing required fields for change_room", "INFO")
        await sio_instance.sio.emit('switch_channel_response', None, to=sid)
        return

    combined_room = f"server:{new_server_id}:channel:{new_channel_id}"
    server_room = f"server:{new_server_id}"
    channel_room = f"channel:{new_channel_id}"

    current_rooms = sio_instance.sio.rooms(sid)

    for room in current_rooms:
        if room.startswith("server:") and room != server_room and ":channel:" not in room:
            await sio_instance.sio.leave_room(sid, room)

    for room in current_rooms:
        if room.startswith("channel:") and room != channel_room:
            await sio_instance.sio.leave_room(sid, room)

    for room in current_rooms:
        if room.startswith("server:") and ":channel:" in room and room != combined_room:
            await sio_instance.sio.leave_room(sid, room)

    if server_room not in current_rooms:
        await sio_instance.sio.enter_room(sid, server_room)

    if channel_room not in current_rooms:
        await sio_instance.sio.enter_room(sid, channel_room)

    if combined_room not in current_rooms:
        await sio_instance.sio.enter_room(sid, combined_room)
        
    await sio_instance.sio.emit(
        'switch_channel_response',
        {"server_id": new_server_id, "channel_id": new_channel_id},
        to=sid
    )