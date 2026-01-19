from server.sio_instance import sio
from server.helpers.bot_message_helpers import send_bot_message, edit_bot_message, delete_bot_message, embed_button
from server.helpers.bot_helpers import initialize_commands
from server.helpers.logs import addMessageToLogs # temporary
    
@sio.on('send_bot_message')
async def handle_send_bot_message(sid, data):
    await addMessageToLogs(f"debug log for bjarnos", "INFO")
    await send_bot_message(sid, data)

@sio.on('edit_bot_message')
async def handle_edit_bot_message(sid, data):
    await edit_bot_message(sid, data)

@sio.on('delete_bot_message')
async def handle_delete_bot_message(sid, data):
    await delete_bot_message(sid, data)
    
@sio.on('initialize_commands')
async def handle_initialize_commands(sid, data):
    await initialize_commands(sid, data)
    
@sio.on('embed_button')
async def handle_embed_button(sid, data):
    await embed_button(sid, data)