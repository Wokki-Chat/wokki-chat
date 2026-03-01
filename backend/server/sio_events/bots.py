from server.sio_instance import sio
from server.helpers.bot_helpers import initialize_commands, embed_button

@sio.safe('initialize_commands')
async def handle_initialize_commands(sid, data):
    await initialize_commands(sid, data)
    
@sio.safe('embed_button')
async def handle_embed_button(sid, data):
    await embed_button(sid, data)
