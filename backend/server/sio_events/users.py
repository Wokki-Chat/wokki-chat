from server.sio_instance import sio
from server.helpers.user_helpers import get_user_info

@sio.on('get_user_info')
async def handle_get_user_info(sid, data):
    await get_user_info(sid, data)