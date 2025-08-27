def get_sid_from_dm_id(user_to_sid, dm_id):
    sids = user_to_sid.get(dm_id)
    if sids and len(sids) > 0:
        return sids[0]
    return None

