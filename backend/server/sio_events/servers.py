from server.sio_instance import sio
from server.helpers.server_helpers import server_commands, command, get_server_users

@sio.safe('get_server_users')
async def handle_get_server_users(sid, data):
    await get_server_users(sid, data)
    
@sio.safe('server_commands')
async def handle_server_commands(sid, data):
    await server_commands(sid, data)
    
@sio.safe('command')
async def handle_command(sid, data):
    await command(sid, data)