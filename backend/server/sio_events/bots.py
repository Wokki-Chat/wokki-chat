from server.sio_instance import sio
from server.helpers.bot_message_helpers import edit_bot_message, embed_button
from server.helpers.bot_helpers import initialize_commands
    
@sio.on('edit_bot_message')
async def handle_edit_bot_message(sid, data):
    await edit_bot_message(sid, data)
    
@sio.on('initialize_commands')
async def handle_initialize_commands(sid, data):
    await initialize_commands(sid, data)
    
@sio.on('embed_button')
async def handle_embed_button(sid, data):
    await embed_button(sid, data)