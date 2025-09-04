from server.helpers.user_helpers import auth_required, is_user_friends_with
from server.helpers.dm_helpers import get_sid_from_dm_id
import aiomysql
import server.sio_instance as sio_instance
import server.config as config
from server.helpers.logs import addMessageToLogs

@auth_required(server_required=False, allow_bots=False)
async def send_friend_request(sid, metadata, data):
    friend_username = data.get('friend_username')

    user_id = metadata.get('account_id')
    
    if friend_username is None:
        await addMessageToLogs(f"Missing required field friend_username for send_friend_request", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("SELECT id FROM users WHERE username = %s", (friend_username,))
            friend_row = await cur.fetchone()
            if not friend_row:
                await addMessageToLogs(f"User not found for send_friend_request, username: {friend_username}", "INFO")
                await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'User not found'}, to=sid)
                return
            
            if user_id == friend_row['id']:
                await addMessageToLogs(f"Cannot send friend request to yourself for send_friend_request, user id: {user_id}", "INFO")
                await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'Cannot send friend request to yourself'}, to=sid)
                return
            
            if await is_user_friends_with(cur, user_id, friend_row['id']):
                await addMessageToLogs(f"Already friends for send_friend_request, user id: {user_id}, friend id: {friend_row['id']}", "INFO")
                await sio_instance.sio.emit('friend_request_sent', {'success': False, 'msg': 'Already friends'}, to=sid)
                return

            await cur.execute("INSERT INTO friends (user_id, friend_id) VALUES (%s, %s)", (user_id, friend_row['id']))
            await conn.commit()
            await sio_instance.sio.emit('friend_request_sent', {'success': True, 'msg': 'Friend request sent'}, to=sid)
            await addMessageToLogs(f"Friend request sent for send_friend_request, user id: {user_id}, friend id: {friend_row['id']}", "INFO")
            
            await cur.execute("SELECT username, profile_picture FROM users WHERE id = %s", (user_id,))
            user_data = await cur.fetchone()
            
            friend_sid = get_sid_from_dm_id(friend_row['id'])
            if friend_sid:
                await addMessageToLogs(f"Friend request received for send_friend_request, user id: {user_id}, friend id: {friend_row['id']}", "INFO")
                await sio_instance.sio.emit('friend_request_received', {'user_id': user_id, 'username': user_data['username'], 'profile_picture': user_data['profile_picture']}, to=friend_sid)
   
@auth_required(server_required=False, allow_bots=False)
async def accept_friend_request(sid, metadata, data):
    requested_friend_id = data.get('requested_friend_id')
    user_id = metadata.get('account_id')
    
    if requested_friend_id is None:
        await addMessageToLogs(f"Invalid field requested_friend_id for accept_friend_request", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("SELECT id FROM users WHERE id = %s", (requested_friend_id,))
            friend_row = await cur.fetchone()
            if not friend_row:
                await addMessageToLogs(f"User not found for accept_friend_request, user id: {requested_friend_id}", "INFO")
                await sio_instance.sio.emit('accept_friend_request_response', {'success': False, 'msg': 'User not found'}, to=sid)
                return
            
            if await is_user_friends_with(cur, user_id, friend_row['id']):
                await addMessageToLogs(f"Already friends for accept_friend_request, user id: {user_id}, friend id: {friend_row['id']}", "INFO")
                await sio_instance.sio.emit('accept_friend_request_response', {'success': False, 'msg': 'Already friends'}, to=sid)
                return

            await cur.execute("INSERT INTO friends (user_id, friend_id) VALUES (%s, %s)", (user_id, friend_row['id']))
            await conn.commit()
            await sio_instance.sio.emit('accept_friend_request_response', {'success': True, 'msg': 'Friend request accepted'}, to=sid)
            await addMessageToLogs(f"Friend request accepted for accept_friend_request, user id: {user_id}, friend id: {friend_row['id']}", "INFO")

            await cur.execute("SELECT username, profile_picture FROM users WHERE id = %s", (user_id,))
            user_data = await cur.fetchone()

            friend_sid = get_sid_from_dm_id(friend_row['id'])
            if friend_sid:
                await sio_instance.sio.emit('friend_request_accepted', {
                    'user_id': user_id,
                    'username': user_data['username'],
                    'profile_picture': user_data['profile_picture']
                }, to=friend_sid)       
                await addMessageToLogs(f"Friend request accepted for accept_friend_request, user id: {user_id}, friend id: {friend_row['id']}", "INFO")

@auth_required(server_required=False, allow_bots=False)
async def pending_friend_requests(sid, metadata, data):
    user_id = metadata.get('account_id')

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("""
                SELECT u.id AS user_id, u.username, u.profile_picture
                FROM friends f
                JOIN users u ON f.user_id = u.id
                WHERE f.friend_id = %s
            """, (user_id,))
            
            raw_requests = await cur.fetchall()
            friend_requests = []

            for req in raw_requests:
                friend_id = req['user_id']
                if not await is_user_friends_with(cur, user_id, friend_id):
                    friend_requests.append(req)

            await sio_instance.sio.emit('pending_friend_requests', {
                'friend_requests': friend_requests
            }, to=sid)
            await addMessageToLogs(f"Pending friend requests for pending_friend_requests, user id: {user_id}", "INFO")
            
@auth_required(server_required=False, allow_bots=False)
async def outgoing_friend_requests(sid, metadata, data):
    user_id = metadata.get('account_id')

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute("""
                SELECT u.id AS user_id, u.username, u.profile_picture
                FROM friends f
                JOIN users u ON f.friend_id = u.id
                WHERE f.user_id = %s
            """, (user_id,))
            
            raw_requests = await cur.fetchall()
            outgoing_friend_requests = []

            for req in raw_requests:
                friend_id = req['user_id']
                if not await is_user_friends_with(cur, user_id, friend_id):
                    outgoing_friend_requests.append(req)

            await sio_instance.sio.emit('outgoing_friend_requests', {
                'outgoing_friend_requests': outgoing_friend_requests
            }, to=sid)
            await addMessageToLogs(f"Outgoing friend requests for outgoing_friend_requests, user id: {user_id}", "INFO")
            
@auth_required(server_required=False, allow_bots=False)
async def cancel_outgoing_friend_request(sid, metadata, data):
    friend_id = data.get('friend_id')
    user_id = metadata.get('account_id')
    
    if friend_id is None:
        await addMessageToLogs(f"Invalid field friend_id for cancel_outgoing_friend_request", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if await is_user_friends_with(cur, user_id, friend_id):
                await addMessageToLogs(f"Already friends for cancel_outgoing_friend_request, user id: {user_id}, friend id: {friend_id}", "INFO")
                await sio_instance.sio.emit('cancel_outgoing_friend_request_response', {'success': False, 'msg': 'Already friends'}, to=sid)
                return

            await cur.execute("DELETE FROM friends WHERE user_id = %s AND friend_id = %s", (user_id, friend_id))
            await conn.commit()
            await addMessageToLogs(f"Deleted outgoing friend request for cancel_outgoing_friend_request from database, user id: {user_id}, friend id: {friend_id}", "INFO")
            await sio_instance.sio.emit('cancel_outgoing_friend_request_response', {'success': True, 'msg': 'Outgoing friend request canceled'}, to=sid)
            await addMessageToLogs(f"Outgoing friend request canceled for cancel_outgoing_friend_request, user id: {user_id}, friend id: {friend_id}", "INFO")

@auth_required(server_required=False, allow_bots=False)
async def deny_friend_request(sid, metadata, data):
    friend_id = data.get('friend_id')
    user_id = metadata.get('account_id')
    
    if friend_id is None:
        await addMessageToLogs(f"Invalid field friend_id for deny_friend_request", "INFO")
        return

    async with config.pool.acquire() as conn:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            if await is_user_friends_with(cur, user_id, friend_id):
                await addMessageToLogs(f"Already friends for deny_friend_request, user id: {user_id}, friend id: {friend_id}", "INFO")
                await sio_instance.sio.emit('deny_friend_request_response', {'success': False, 'msg': 'Already friends'}, to=sid)
                return
            
            await cur.execute("DELETE FROM friends WHERE friend_id = %s AND user_id = %s", (user_id, friend_id))
            await conn.commit()
            await addMessageToLogs(f"Deleted incoming friend request for deny_friend_request from database, user id: {user_id}, friend id: {friend_id}", "INFO")
            await sio_instance.sio.emit('deny_friend_request_response', {'success': True, 'msg': 'Friend request denied'}, to=sid)
            await addMessageToLogs(f"Friend request denied for deny_friend_request, user id: {user_id}, friend id: {friend_id}", "INFO")