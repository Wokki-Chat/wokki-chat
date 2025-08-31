from server.config import HEX_COLOR_RE
import json
import server.sio_instance as sio_instance
import server.config as config
import aiomysql
from server.helpers.logs import addMessageToLogs

def is_valid_hex_color(color):
    return color is None or (isinstance(color, str) and HEX_COLOR_RE.match(color))

def validate_embed(embed):
    if isinstance(embed, list):
        for e in embed:
            if not isinstance(e, dict) or not validate_single_embed(e):
                return False
        return True
    elif isinstance(embed, dict):
        return validate_single_embed(embed)
    else:
        return False


def validate_single_embed(embed):
    if not isinstance(embed, dict):
        return False
    
    
    title = embed.get('title')
    if title is not None and not isinstance(title, str):
        return False
    
    description = embed.get('description')
    if description is not None and not isinstance(description, str):
        return False
    
    color = embed.get('color')
    if color is not None and not is_valid_hex_color(color):
        return False
    
    fields = embed.get('fields')
    if fields is None:
        fields = []
    if not isinstance(fields, list):
        return False
    for f in fields:
        if not isinstance(f, dict):
            return False
        if not isinstance(f.get('name'), str) or not isinstance(f.get('value'), str):
            return False
    
    footer = embed.get('footer')
    if footer is not None and not isinstance(footer, str):
        return False
    
    components = embed.get('components', [])
    if not isinstance(components, list):
        return False
    for row in components:
        if not isinstance(row, list):
            return False
        for btn in row:
            if not isinstance(btn, dict):
                return False
            if btn.get('type') not in ('button', 'link'):
                return False
            if not isinstance(btn.get('label'), str) or btn.get('label') == '':
                return False
            if not isinstance(btn.get('id'), str) or btn.get('id') == '':
                return False
            
            if not is_valid_hex_color(btn.get('color')):
                return False
            if not is_valid_hex_color(btn.get('text_color')):
                return False
            if btn['type'] == 'link':
                url = btn.get('url')
                if not isinstance(url, str):
                    return False
                try:
                    from urllib.parse import urlparse
                    parsed = urlparse(url)
                    if not parsed.scheme or not parsed.netloc:
                        return False
                except:
                    return False
    return True

async def verify_bot_token(cur, bot_token):
    await addMessageToLogs(f"verifying bot token ({bot_token})", "INFO")
    await cur.execute('SELECT id FROM bots WHERE bot_token = %s', (bot_token,))
    row = await cur.fetchone()
    if not row:
        await addMessageToLogs(f"bot token ({bot_token}) not found", "INFO")
        return None
    
    await addMessageToLogs(f"bot token ({bot_token}) found. Bot id: {row['id']}", "INFO")
    return row['id']

async def is_bot_in_server(cur, bot_id, server_id):
    await addMessageToLogs(f"checking if bot ({bot_id}) is in server ({server_id})", "INFO")
    await cur.execute(
        "SELECT 1 FROM server_members WHERE server_id = %s AND bot_id = %s LIMIT 1",
        (server_id, bot_id)
    )
    server = await cur.fetchone()
    await addMessageToLogs(f"Bot ({bot_id}) in server ({server_id}): {bool(server)}", "INFO")
    return bool(server)

async def get_bot_info_from_id(cur, bot_id):
    await addMessageToLogs(f"getting bot info from id ({bot_id})", "INFO")
    await cur.execute('SELECT id, name, status, profile_picture, created_at, bio FROM bots WHERE id = %s', (bot_id,))
    bot = await cur.fetchone()
    if bot:
        bot['id'] = str(bot['id'])
        bot['premium'] = False
        bot['bot'] = True
        bot['username'] = bot['name']
        bot['tags'] = []
        bot['staff'] = False
        bot['developer'] = False
        bot['created_at'] = str(bot['created_at'].isoformat()),
        bot['bio'] = bot['bio']
        del bot['name']
        await addMessageToLogs(f"got bot info from id ({bot_id})", "INFO")
        return dict(bot)

    await addMessageToLogs(f"bot ({bot_id}) not found", "INFO")
    return None


async def initialize_commands(sid, data):
    bot_token = data.get('bot_token')
    commands = data.get('commands')

    if not bot_token or commands is None:
        await addMessageToLogs(f"Missing required fields for initialize_commands", "INFO")
        await sio_instance.sio.emit('initialize_commands_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
    
    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            bot_id = await verify_bot_token(cur, bot_token)
            if not bot_id:
                await addMessageToLogs(f"Invalid token for initialize_commands. Bot token: {bot_token}", "INFO")
                await sio_instance.sio.emit('initialize_commands_response', {'success': False, 'error': 'Invalid token'}, to=sid)
                return

            valid_commands = [
                cmd for cmd in commands
                if isinstance(cmd, dict) and cmd.get('command', '').startswith('/')
            ]
            new_command_names = set(cmd['command'] for cmd in valid_commands)

            await cur.execute('SELECT command FROM bot_commands WHERE bot_id = %s', (bot_id,))
            existing_commands = await cur.fetchall()
            existing_command_names = set(cmd['command'] for cmd in existing_commands)

            if commands:
                removed_commands = existing_command_names - new_command_names
                for cmd in removed_commands:
                    await addMessageToLogs(f"Removing command: {cmd} from bot {bot_id}", "INFO")
                    await cur.execute(
                        'DELETE FROM bot_commands WHERE bot_id = %s AND command = %s',
                        (bot_id, cmd)
                    )

            for command in valid_commands:
                cmd_name = command['command']
                options = json.dumps(command.get('options', {}), sort_keys=True)

                await addMessageToLogs(f"Initializing command: {cmd_name} for bot {bot_id}", "INFO")
                await cur.execute(
                    'SELECT options FROM bot_commands WHERE bot_id = %s AND command = %s',
                    (bot_id, cmd_name)
                )
                existing = await cur.fetchone()

                if existing:
                    if existing['options'] != options:
                        await addMessageToLogs(f"Updating command: {cmd_name} for bot {bot_id}", "INFO")
                        await cur.execute(
                            'UPDATE bot_commands SET options = %s WHERE bot_id = %s AND command = %s',
                            (options, bot_id, cmd_name)
                        )
                else:
                    await addMessageToLogs(f"Adding command: {cmd_name} for bot {bot_id}", "INFO")
                    await cur.execute(
                        'INSERT INTO bot_commands (bot_id, command, options) VALUES (%s, %s, %s)',
                        (bot_id, cmd_name, options)
                    )
                    
            await addMessageToLogs(f"Initialized commands for bot {bot_id}", "INFO")
            await sio_instance.sio.emit('initialize_commands_response', {'success': True}, to=sid)