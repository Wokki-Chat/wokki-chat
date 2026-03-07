import json
import uuid
import asyncio
import aiomysql
import re

import server.sio_instance as sio_instance
from server.config import get_sids_for_user, get_bot_sid_from_id, get_cached_users, cache_users, save_command_id, get_typing_users
import server.config as config
from server.helpers.user_helpers import auth_required, get_user_info_from_id
from server.helpers.bot_helpers import get_bot_info_from_id, is_bot_in_server
from server.helpers.logs import addMessageToLogs


async def check_permissions(cur, account_id, server_id, permission_identifier, is_bot=False):
    perm_column_map = {
        "send_messages": "send_messages",
        "view_channels": "view_channels",
        "manage_channels": "manage_channels",
        "manage_server": "manage_server",
        "manage_roles": "manage_roles",
        "kick_members": "kick_members",
        "ban_members": "ban_members",
        "mute_members": "mute_members",
        "manage_groups": "manage_groups",
        "read_message_history": "read_message_history"
    }

    col = perm_column_map.get(permission_identifier)
    if not col:
        await addMessageToLogs(f"Unknown permission: {permission_identifier}", "WARN")
        return False

    if is_bot:
        query = """
            SELECT bsr.role_id, rp.*
            FROM bot_server_roles bsr
            JOIN role_permissions rp ON bsr.role_id = rp.role_id
            JOIN server_roles sr ON rp.role_id = sr.role_id
            WHERE bsr.server_id = %s AND bsr.bot_id = %s
        """
    else:
        query = """
            SELECT usr.role_id, rp.*
            FROM user_server_roles usr
            JOIN role_permissions rp ON usr.role_id = rp.role_id
            JOIN server_roles sr ON rp.role_id = sr.role_id
            WHERE usr.server_id = %s AND usr.user_id = %s
        """

    await cur.execute(query, (server_id, account_id))
    rows = await cur.fetchall()

    if not rows:
        return False

    for row in rows:
        if row.get(col) or getattr(row, col, False):
            await addMessageToLogs(
                f"Account {account_id} has permission {permission_identifier} via role {row['role_id']}",
                "INFO"
            )
            return True

    return False


async def check_channel_permission(cur, account_id, server_id, channel_id, permission_identifier, is_bot=False):
    if is_bot:
        await cur.execute("""
            SELECT allow
            FROM channel_permission_overrides
            WHERE channel_id = %s
              AND bot_id = %s
              AND permission = %s
            LIMIT 1
        """, (channel_id, account_id, permission_identifier))
    else:
        await cur.execute("""
            SELECT allow
            FROM channel_permission_overrides
            WHERE channel_id = %s
              AND (
                    (user_id = %s AND role_id IS NULL)
                 OR (role_id IN (
                         SELECT role_id FROM user_server_roles
                         WHERE user_id = %s AND server_id = %s
                     ) AND user_id IS NULL)
              )
              AND permission = %s
            ORDER BY (user_id IS NOT NULL) DESC
            LIMIT 1
        """, (channel_id, account_id, account_id, server_id, permission_identifier))

    row = await cur.fetchone()

    if row is not None:
        if row['allow'] == 0:
            await addMessageToLogs(
                f"Account {account_id} DENIED {permission_identifier} in channel {channel_id} (override)",
                "INFO"
            )
            return False
        else:
            await addMessageToLogs(
                f"Account {account_id} ALLOWED {permission_identifier} in channel {channel_id} (override)",
                "INFO"
            )
            return True

    return await check_permissions(cur, account_id, server_id, permission_identifier, is_bot=is_bot)


async def server_permissions(cur, user_id, server_id, permission_identifier):
    return await check_permissions(cur, user_id, server_id, permission_identifier, is_bot=False)


async def is_user_in_server(cur, user_id, server_id):
    await cur.execute(
        "SELECT 1 FROM server_members WHERE server_id = %s AND user_id = %s LIMIT 1",
        (server_id, user_id)
    )
    server = await cur.fetchone()
    await addMessageToLogs(f"User {user_id} in server {server_id}: {bool(server)}", "INFO")
    return bool(server)


async def get_user_roles_in_server(cur, user_id, server_id):
    await cur.execute("""
        SELECT sr.role_id, sr.role_name, sr.role_color
        FROM user_server_roles usr
        JOIN server_roles sr ON usr.role_id = sr.role_id
        WHERE usr.user_id = %s AND usr.server_id = %s
        ORDER BY sr.role_id
    """, (user_id, server_id))

    roles = await cur.fetchall()
    return [
        {
            "role_id": str(role["role_id"]),
            "role_name": role["role_name"],
            "role_color": role["role_color"]
        }
        for role in roles
    ]


async def get_bot_roles_in_server(cur, bot_id, server_id):
    await cur.execute("""
        SELECT sr.role_id, sr.role_name, sr.role_color
        FROM bot_server_roles bsr
        JOIN server_roles sr ON bsr.role_id = sr.role_id
        WHERE bsr.bot_id = %s AND bsr.server_id = %s
        ORDER BY sr.role_id
    """, (bot_id, server_id))

    roles = await cur.fetchall()
    return [
        {
            "role_id": str(role["role_id"]),
            "role_name": role["role_name"],
            "role_color": role["role_color"]
        }
        for role in roles
    ]


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
                user['roles'] = await get_user_roles_in_server(cur, uid, server_id)
                users.append(user)

        elif entry["type"] == "bot":
            uid = entry["id"]
            if not await is_bot_in_server(cur, uid, server_id):
                continue
            bot = await get_bot_info_from_id(cur, uid)
            if bot:
                bot['roles'] = await get_bot_roles_in_server(cur, uid, server_id)
                users.append(bot)

    return users


@auth_required(server_or_contact_required=True, allow_bots=False, permissions=['manage_channels'])
async def set_channel_permission_override(sid, metadata, data):
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    permission = data.get('permission')
    allow = data.get('allow')
    target_user_id = data.get('target_user_id')
    target_role_id = data.get('target_role_id')

    if not all([server_id, channel_id, permission]) or allow is None:
        await sio_instance.sio.emit('set_channel_permission_override_response', {
            'success': False, 'error': 'Missing required fields'
        }, to=sid)
        return

    if (target_user_id is None) == (target_role_id is None):
        await sio_instance.sio.emit('set_channel_permission_override_response', {
            'success': False,
            'error': 'Provide exactly one of target_user_id or target_role_id'
        }, to=sid)
        return

    valid_permissions = [
        'send_messages', 'view_channels', 'manage_channels', 'manage_server',
        'manage_roles', 'kick_members', 'ban_members', 'mute_members',
        'manage_groups', 'read_message_history'
    ]
    if permission not in valid_permissions:
        await sio_instance.sio.emit('set_channel_permission_override_response', {
            'success': False, 'error': f'Unknown permission: {permission}'
        }, to=sid)
        return

    allow_int = 1 if allow else 0

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(
                "SELECT 1 FROM channels WHERE channel_id = %s AND server_id = %s LIMIT 1",
                (channel_id, server_id)
            )
            if not await cur.fetchone():
                await sio_instance.sio.emit('set_channel_permission_override_response', {
                    'success': False, 'error': 'Channel not found in server'
                }, to=sid)
                return

            if target_user_id:
                await cur.execute("""
                    INSERT INTO channel_permission_overrides
                        (channel_id, user_id, role_id, bot_id, permission, allow)
                    VALUES (%s, %s, NULL, NULL, %s, %s)
                    ON DUPLICATE KEY UPDATE allow = VALUES(allow)
                """, (channel_id, target_user_id, permission, allow_int))
            else:
                await cur.execute("""
                    INSERT INTO channel_permission_overrides
                        (channel_id, user_id, role_id, bot_id, permission, allow)
                    VALUES (%s, NULL, %s, NULL, %s, %s)
                    ON DUPLICATE KEY UPDATE allow = VALUES(allow)
                """, (channel_id, target_role_id, permission, allow_int))

            await conn.commit()

    await sio_instance.sio.emit('set_channel_permission_override_response', {
        'success': True
    }, to=sid)
    await addMessageToLogs(
        f"Channel permission override set: channel={channel_id} permission={permission} "
        f"allow={allow} user={target_user_id} role={target_role_id}",
        "INFO"
    )


@auth_required(server_or_contact_required=True, allow_bots=False, permissions=['manage_channels'])
async def delete_channel_permission_override(sid, metadata, data):
    channel_id = data.get('channel_id')
    permission = data.get('permission')
    target_user_id = data.get('target_user_id')
    target_role_id = data.get('target_role_id')

    if not channel_id or not permission:
        await sio_instance.sio.emit('delete_channel_permission_override_response', {
            'success': False, 'error': 'Missing required fields'
        }, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if target_user_id:
                await cur.execute("""
                    DELETE FROM channel_permission_overrides
                    WHERE channel_id = %s AND user_id = %s AND permission = %s
                """, (channel_id, target_user_id, permission))
            elif target_role_id:
                await cur.execute("""
                    DELETE FROM channel_permission_overrides
                    WHERE channel_id = %s AND role_id = %s AND permission = %s
                """, (channel_id, target_role_id, permission))
            else:
                await sio_instance.sio.emit('delete_channel_permission_override_response', {
                    'success': False,
                    'error': 'Provide target_user_id or target_role_id'
                }, to=sid)
                return

            await conn.commit()

    await sio_instance.sio.emit('delete_channel_permission_override_response', {
        'success': True
    }, to=sid)


@auth_required(server_or_contact_required=True, allow_bots=False, permissions=['view_channels'])
async def get_channel_permission_overrides(sid, metadata, data):
    channel_id = data.get('channel_id')
    server_id = data.get('server_id')

    if not channel_id or not server_id:
        await sio_instance.sio.emit('channel_permission_overrides', {
            'success': False, 'error': 'Missing required fields'
        }, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("""
                SELECT cpo.*,
                       u.username AS user_name,
                       sr.role_name
                FROM channel_permission_overrides cpo
                LEFT JOIN users u ON cpo.user_id = u.id
                LEFT JOIN server_roles sr ON cpo.role_id = sr.role_id
                WHERE cpo.channel_id = %s
            """, (channel_id,))
            overrides = await cur.fetchall()

    await sio_instance.sio.emit('channel_permission_overrides', {
        'success': True,
        'channel_id': channel_id,
        'overrides': [dict(o) for o in overrides]
    }, to=sid)


@auth_required(server_or_contact_required=True, allow_bots=False, permissions=['view_channels'])
async def server_commands(sid, metadata, data):
    server_id = data.get('server_id')
    user_id = metadata.get('account_id')

    if not server_id:
        await addMessageToLogs("Missing required field server_id for server_commands", "INFO")
        await sio_instance.sio.emit('server_commands_response', {
            'success': False, 'error': 'Missing required fields'
        }, to=sid)
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
                await sio_instance.sio.emit('server_commands_response', {
                    'success': False, 'error': 'No bots in server'
                }, to=sid)
                return

            bots_with_commands = []
            for bot in bots:
                if not bot['id']:
                    continue
                await cur.execute(
                    'SELECT command, options, description FROM bot_commands WHERE bot_id = %s',
                    (bot['id'],)
                )
                commands = await cur.fetchall()
                bot['commands'] = commands if commands else []
                bots_with_commands.append(bot)

            await sio_instance.sio.emit('server_commands_response', {
                'success': True, 'bots': bots_with_commands
            }, to=sid)
            await addMessageToLogs(
                f"Emitted server_commands_response for server_id: {server_id}", "INFO"
            )


@auth_required(server_or_contact_required=True, allow_bots=False, permissions=['view_channels'])
async def get_server_users(sid, metadata, data):
    server_id = data.get('server_id')
    user_id = metadata.get('account_id')

    if not server_id:
        await sio_instance.sio.emit('get_server_users_response', {
            'success': False, 'error': 'Missing required fields'
        }, to=sid)
        return

    cached_users = await get_cached_users(server_id=server_id)
    if cached_users:
        await sio_instance.sio.emit('server_users', cached_users, to=sid)

    async def get_users():
        async with config.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                users = await get_server_users_info(cur, server_id)
                await cache_users(server_id=server_id, users=users)
                await sio_instance.sio.emit("server_users", users, to=sid)
                await addMessageToLogs(
                    f"Emitted server_users to {user_id}, sent {len(users)} users", "INFO"
                )

    asyncio.create_task(get_users())


@auth_required(server_or_contact_required=True, allow_bots=False, permissions=['send_messages'])
async def command(sid, metadata, data):
    command_str = data.get('command')
    server_id = data.get('server_id')
    channel_id = data.get('channel_id')
    options_input = data.get('options') or {}
    bot_id = data.get('bot_id')
    user_id = metadata.get('account_id')

    if not all([command_str, server_id, channel_id, bot_id]):
        await sio_instance.sio.emit('command_response', {
            'success': False, 'error': 'Missing required fields'
        }, to=sid)
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if not await is_bot_in_server(cur, bot_id, server_id):
                await sio_instance.sio.emit('command_response', {
                    'success': False, 'error': 'Bot is not in server'
                }, to=sid)
                return

            await cur.execute(
                'SELECT command, options FROM bot_commands WHERE command = %s AND bot_id = %s',
                (command_str, bot_id)
            )
            row = await cur.fetchone()
            if not row:
                await sio_instance.sio.emit('command_response', {
                    'success': False, 'error': 'Command not found'
                }, to=sid)
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
                        await sio_instance.sio.emit('command_response', {
                            'success': False,
                            'error': f"Missing required option '{name}'"
                        }, to=sid)
                        return

                    if name in options_input:
                        val = options_input[name]
                        type_ok = (
                            (opt_type == 'boolean' and isinstance(val, bool)) or
                            (opt_type == 'string' and isinstance(val, str)) or
                            (opt_type == 'number' and isinstance(val, (int, float))) or
                            (opt_type == 'user' and isinstance(val, str))
                        )
                        if not type_ok:
                            await sio_instance.sio.emit('command_response', {
                                'success': False,
                                'error': f"Option '{name}' has wrong type (expected {opt_type})"
                            }, to=sid)
                            return

                        if opt_type == 'user':
                            if not re.match(r'^[^#]+#\d+$', val):
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"User '{val}' must be in format username#user_id"
                                }, to=sid)
                                return
                            user_id_val = int(val.split('#')[1])
                            if not await is_user_in_server(cur, user_id_val, server_id):
                                await sio_instance.sio.emit('command_response', {
                                    'success': False,
                                    'error': f"User '{val}' is not in server"
                                }, to=sid)
                                return

            bot_sid = await get_bot_sid_from_id(bot_id)
            command_id = str(uuid.uuid4())
            await save_command_id(user_id, command_id, command_str)

            if bot_sid:
                await sio_instance.sio.emit('bot_command_received', {
                    'command': command_str,
                    'options': options_input,
                    'sent_by_user_id': user_id,
                    'command_id': command_id,
                    'server_id': server_id,
                    'channel_id': channel_id
                }, to=bot_sid)
            else:
                await sio_instance.sio.emit('command_response', {
                    'success': False, 'error': 'Bot not found'
                }, to=sid)
                return

            await sio_instance.sio.emit('command_response', {'success': True}, to=sid)