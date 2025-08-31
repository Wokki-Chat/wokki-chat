from server.config import get_sids_for_user

async def get_sid_from_dm_id(dm_id: str):
    """
    Given a DM ID, return the first connected SID for that user.
    Returns None if no SID is found.
    """
    sids = await get_sids_for_user(dm_id)
    if sids:
        return next(iter(s.decode() for s in sids), None)
    return None
