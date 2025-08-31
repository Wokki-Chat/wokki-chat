from livekit.api import LiveKitAPI, CreateRoomRequest, ListRoomsRequest
from livekit import api 
from server.config import LIVEKIT_BASE_URL, API_KEY, API_SECRET
async def livekit_room_exists(room_name: str) -> bool:
    async with LiveKitAPI(
        url=LIVEKIT_BASE_URL,
        api_key=API_KEY,
        api_secret=API_SECRET,
    ) as lkapi:
        rooms = await lkapi.room.list_rooms(ListRoomsRequest())
        return any(room.name == room_name for room in rooms.rooms)

async def livekit_create_room(room_name: str) -> bool:
    async with LiveKitAPI(
        url=LIVEKIT_BASE_URL,
        api_key=API_KEY,
        api_secret=API_SECRET,
    ) as lkapi:
        room = await lkapi.room.create_room(CreateRoomRequest(
            name=room_name,
            empty_timeout=600,
        ))
        return True if room else False


def generate_livekit_token(identity, room_name):
    token = api.AccessToken(
        API_KEY,
        API_SECRET
    ).with_identity(str(identity)) \
     .with_grants(api.VideoGrants(
         room_join=True,
         room=room_name
     ))
    return token.to_jwt()