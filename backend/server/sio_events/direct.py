from server.sio_instance import sio
from server.helpers.direct_message_helpers import get_direct_messages, send_direct_message, get_direct_message_by_id, delete_direct_message

@sio.safe('get_direct_messages')
async def handle_get_direct_messages(sid, data):
    await get_direct_messages(sid, data)
    
@sio.safe('send_direct_message')
async def handle_send_direct_message(sid, data):
    await send_direct_message(sid, data)
    
@sio.safe('get_direct_message_by_id')
async def handle_get_direct_message_by_id(sid, data):
    await get_direct_message_by_id(sid, data)
    
@sio.safe('delete_direct_message')
async def handle_delete_direct_message(sid, data):
    await delete_direct_message(sid, data)