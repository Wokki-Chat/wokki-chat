from server.sio_instance import sio
from server.helpers.message_helpers import send_message, get_messages, get_message_by_id, delete_message

@sio.safe("send_message")
async def handle_send_message(sid, data):
    await send_message(sid, data)

@sio.safe("get_messages")
async def handle_get_messages(sid, data):
    await get_messages(sid, data)
    
@sio.safe('get_message_by_id')
async def handle_get_message_by_id(sid, data):
    await get_message_by_id(sid, data)
    
@sio.safe('delete_message')
async def handle_delete_message(sid, data):
    await delete_message(sid, data)