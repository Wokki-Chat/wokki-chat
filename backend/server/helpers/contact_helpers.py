from server.config import get_cached_users, cache_users
import server.sio_instance as sio_instance
from server.helpers.user_helpers import auth_required
import aiomysql
import re
import server.config as config
import asyncio
from server.helpers.user_helpers import get_user_info_from_id
            
@auth_required(server_required=False, allow_bots=False)
async def get_contact_users(sid, metadata, data):
    contact_id = data.get('contact_id')
    user_id = metadata.get('account_id')

    if not contact_id:
        await sio_instance.sio.emit('get_contact_users_response', {'success': False, 'error': 'Missing required fields'}, to=sid)
        return
        
    cached_users = await get_cached_users(contact_id=contact_id)
    if cached_users is not None:
        await sio_instance.sio.emit('contact_users', cached_users, to=sid)
    
    async def get_users():
        try:
            async with config.pool.acquire() as conn:
                async with conn.cursor(aiomysql.DictCursor) as cur:
                    users = await get_contact_users_info(cur, contact_id)
                    await cache_users(contact_id=contact_id, users=users)
                    await sio_instance.sio.emit("contact_users", users, to=sid)
        except Exception as e:
            print(f"Error fetching contact users: {e}")
            await sio_instance.sio.emit('get_contact_users_response', {'success': False, 'error': 'Failed to fetch users'}, to=sid)

    asyncio.create_task(get_users())
    

async def get_contact_users_info(cur, contact_id):
    await cur.execute("""
        SELECT user_id FROM contact_users WHERE contact_id = %s
    """, (contact_id,))
    
    user_ids = [row['user_id'] async for row in cur]
    users = []

    for user_id in user_ids:
        user = await get_user_info_from_id(cur, user_id)
        if user is not None:
            users.append(user)
    
    return users