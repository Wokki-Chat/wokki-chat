from server.helpers.user_helpers import auth_required
import aiomysql
import server.sio_instance as sio_instance
import server.config as config
from server.helpers.logs import addMessageToLogs
import uuid

@auth_required(server_required=False, allow_bots=False)
async def send_friend_request(sid, metadata, data):
    friend_username = data.get('friend_username')
    user_id = metadata.get('account_id')
    
    if friend_username is None:
        await addMessageToLogs(f"Missing required field friend_username for send_friend_request", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            try:
                await cur.execute("SELECT id FROM users WHERE username = %s", (friend_username,))
                friend_row = await cur.fetchone()
                if not friend_row:
                    await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'User not found'}, to=sid)
                    return
                
                if user_id == friend_row['id']:
                    await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'Cannot send friend request to yourself'}, to=sid)
                    return
                
                if await friends(cur, user_id, friend_row['id']):
                    await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'Already friends'}, to=sid)
                    return
                
                await cur.execute("""
                    SELECT 1 FROM contact_requests cr
                    JOIN contact_users cu ON cr.contact_id = cu.contact_id
                    WHERE cr.user_id = %s AND cu.user_id = %s
                """, (friend_row['id'], user_id))
                if await cur.fetchone():
                    await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'Friend request already sent'}, to=sid)
                    return
                
                contact_id = uuid.uuid4()
                await cur.execute("INSERT INTO contacts (contact_id) VALUES (%s)", (contact_id,))
                await cur.execute("INSERT INTO contact_users (contact_id, user_id) VALUES (%s, %s)", (contact_id, user_id))
                await cur.execute("INSERT INTO contact_requests (contact_id, user_id) VALUES (%s, %s)", (contact_id, friend_row['id']))
                
                await conn.commit()
                
                await sio_instance.sio.emit('friend_request_sent', {'success': True, 'msg': 'Friend request sent'}, to=sid)
                await sio_instance.sio.emit('friend_request_received', {'sender_id': user_id, 'contact_id': contact_id}, room="user:" + str(friend_row['id']))
                
            except Exception as e:
                await conn.rollback()
                await addMessageToLogs(f"Error sending friend request: {e}", "ERROR")
                await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'Internal error'}, to=sid)


async def friends(cur, user_id, friend_id):
    await cur.execute("""
        SELECT contact_id
        FROM contact_users
        WHERE user_id IN (%s, %s)
        GROUP BY contact_id
        HAVING COUNT(*) = 2
           AND COUNT(DISTINCT user_id) = 2
    """, (user_id, friend_id))
    return await cur.fetchone() is not None

async def inContact(cur, user_id, contact_id):
    await cur.execute("""
        SELECT contact_id
        FROM contact_users
        WHERE user_id = %s AND contact_id = %s
    """, (user_id, contact_id))
    return await cur.fetchone() is not None
   
@auth_required(server_required=False, allow_bots=False)
async def accept_friend_request(sid, metadata, data):
    requested_friend_id = data.get('requested_friend_id')
    user_id = metadata.get('account_id')
    
    if requested_friend_id is None:
        await addMessageToLogs(f"Invalid field requested_friend_id for accept_friend_request", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            try:
                await cur.execute("SELECT id FROM users WHERE id = %s", (requested_friend_id,))
                friend_row = await cur.fetchone()
                if not friend_row:
                    await addMessageToLogs(f"User not found for accept_friend_request, user id: {requested_friend_id}", "INFO")
                    await sio_instance.sio.emit('accept_friend_request_response', {'success': False, 'msg': 'User not found'}, to=sid)
                    return
                    
                if await friends(cur, user_id, friend_row['id']):
                    await addMessageToLogs(f"Already friends for accept_friend_request, user ids: {user_id}, {friend_row['id']}", "INFO")
                    await sio_instance.sio.emit('accept_friend_request_response', {'success': False, 'msg': 'Already friends'}, to=sid)
                    return
                
                await cur.execute("""
                    SELECT cr.contact_id
                    FROM contact_requests cr
                    JOIN contact_users cu ON cr.contact_id = cu.contact_id
                    WHERE cr.user_id = %s AND cu.user_id = %s
                """, (user_id, requested_friend_id))
                
                request_row = await cur.fetchone()
                if not request_row:
                    await addMessageToLogs(f"Friend request not found for accept_friend_request, user ids: {user_id}, {requested_friend_id}", "INFO")
                    await sio_instance.sio.emit('accept_friend_request_response', {'success': False, 'msg': 'Friend request not found'}, to=sid)
                    return
                
                contact_id = request_row['contact_id']
                
                await cur.execute("INSERT INTO contact_users (contact_id, user_id) VALUES (%s, %s)", (contact_id, user_id))
                
                await cur.execute("DELETE FROM contact_requests WHERE contact_id = %s AND user_id = %s", (contact_id, user_id))
                
                await conn.commit()
                
                await sio_instance.sio.emit('accept_friend_request_response', {'success': True, 'msg': 'Friend request accepted'}, to=sid)
                await sio_instance.sio.emit('friend_request_accepted', {'contact_id': contact_id}, room="user:" + str(requested_friend_id))
                
            except Exception as e:
                await conn.rollback()
                await addMessageToLogs(f"Error accepting friend request: {e}", "ERROR")
                await sio_instance.sio.emit('accept_friend_request_response', {'success': False, 'msg': 'Internal error'}, to=sid)

@auth_required(server_required=False, allow_bots=False)
async def pending_friend_requests(sid, metadata, data):
    user_id = metadata.get('account_id')

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("""
                SELECT u.id AS user_id, u.username, u.profile_picture
                FROM contact_requests cr
                JOIN contact_users cu ON cr.contact_id = cu.contact_id
                JOIN users u ON cu.user_id = u.id
                WHERE cr.user_id = %s AND cu.user_id != %s
            """, (user_id, user_id))
            
            friend_requests = await cur.fetchall()
            await sio_instance.sio.emit('pending_friend_requests', {'friend_requests': friend_requests}, to=sid)

            
@auth_required(server_required=False, allow_bots=False)
async def outgoing_friend_requests(sid, metadata, data):
    user_id = metadata.get('account_id')

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("""
                SELECT u.id AS user_id, u.username, u.profile_picture
                FROM contact_requests cr
                JOIN contact_users cu ON cr.contact_id = cu.contact_id
                JOIN users u ON cr.user_id = u.id
                WHERE cu.user_id = %s AND cr.user_id != %s
            """, (user_id, user_id))
            
            outgoing_requests = await cur.fetchall()
            await sio_instance.sio.emit('outgoing_friend_requests', {'outgoing_friend_requests': outgoing_requests}, to=sid)

            
@auth_required(server_required=False, allow_bots=False)
async def cancel_outgoing_friend_request(sid, metadata, data):
    friend_id = data.get('friend_id')
    user_id = metadata.get('account_id')
    
    if friend_id is None:
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            try:
                await cur.execute("""
                    DELETE c, cu, cr
                    FROM contacts c
                    JOIN contact_users cu ON c.contact_id = cu.contact_id
                    JOIN contact_requests cr ON c.contact_id = cr.contact_id
                    WHERE cu.user_id = %s AND cr.user_id = %s
                """, (user_id, friend_id))
                
                await conn.commit()
                await sio_instance.sio.emit('cancel_outgoing_friend_request_response', {'success': True}, to=sid)
            except Exception as e:
                await conn.rollback()
                await sio_instance.sio.emit('cancel_outgoing_friend_request_response', {'success': False, 'msg': 'Error'}, to=sid)


@auth_required(server_required=False, allow_bots=False)
async def deny_friend_request(sid, metadata, data):
    friend_id = data.get('friend_id')
    user_id = metadata.get('account_id')
    
    if friend_id is None:
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            try:
                await cur.execute("""
                    DELETE c, cu, cr
                    FROM contacts c
                    JOIN contact_users cu ON c.contact_id = cu.contact_id
                    JOIN contact_requests cr ON c.contact_id = cr.contact_id
                    WHERE cr.user_id = %s AND cu.user_id = %s
                """, (user_id, friend_id))
                
                await conn.commit()
                await sio_instance.sio.emit('deny_friend_request_response', {'success': True}, to=sid)
            except Exception as e:
                await conn.rollback()
                await sio_instance.sio.emit('deny_friend_request_response', {'success': False, 'msg': 'Error'}, to=sid)