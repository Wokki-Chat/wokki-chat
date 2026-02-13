from server.sio_instance import sio
from server.helpers.contact_helpers import get_contact_users

@sio.safe('get_contact_users')
async def handle_get_contact_users(sid, data):
    await get_contact_users(sid, data)