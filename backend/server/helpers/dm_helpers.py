from server.config import user_to_sid

def get_sid_from_dm_id(dm_id):
    """
    Given a DM ID, return the first connected SID for that user.
    Returns None if no SID is found.
    """
    sids = user_to_sid.get(dm_id)
    if sids and len(sids) > 0:
        return sids[0]
    return None
