from server.sio_instance import sio
from server.helpers.friend_helpers import send_friend_request, accept_friend_request, pending_friend_requests, outgoing_friend_requests, cancel_outgoing_friend_request, deny_friend_request

@sio.safe('send_friend_request')
async def handle_send_friend_request(sid, data):
    await send_friend_request(sid, data)
    
@sio.safe('accept_friend_request')
async def handle_accept_friend_request(sid, data):
    await accept_friend_request(sid, data)
    
@sio.safe('pending_friend_requests')
async def handle_pending_friend_requests(sid, data):
    await pending_friend_requests(sid, data)
    
@sio.safe('outgoing_friend_requests')
async def handle_outgoing_friend_requests(sid, data):
    await outgoing_friend_requests(sid, data)
    
@sio.safe('cancel_outgoing_friend_request')
async def handle_cancel_outgoing_friend_request(sid, data):
    await cancel_outgoing_friend_request(sid, data)
    
@sio.safe('deny_friend_request')
async def handle_deny_friend_request(sid, data):
    await deny_friend_request(sid, data)