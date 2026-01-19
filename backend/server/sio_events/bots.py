from server.sio_instance import sio
from server.helpers.bot_message_helpers import send_bot_message, edit_bot_message, delete_bot_message, embed_button
from server.helpers.bot_helpers import initialize_commands
    
@sio.safe('send_bot_message')
async def handle_send_bot_message(sid, data):
    await send_bot_message(sid, data)

@sio.safe('edit_bot_message')
async def handle_edit_bot_message(sid, data):
    await edit_bot_message(sid, data)

@sio.safe('delete_bot_message')
async def handle_delete_bot_message(sid, data):
    await delete_bot_message(sid, data)
    
@sio.safe('initialize_commands')
async def handle_initialize_commands(sid, data):
    await initialize_commands(sid, data)
    
@sio.safe('embed_button')
async def handle_embed_button(sid, data):
    await embed_button(sid, data)
