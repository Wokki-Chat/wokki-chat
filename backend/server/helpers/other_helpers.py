import math
from server.helpers.logs import addMessageToLogs
from datetime import datetime
from gibberish_classifier.classify import classify

GIBBERISH_THRESHOLD = 20 

def is_gibberish(message: str) -> bool:
    """
    Detects gibberish using the Gibberish Classifier.
    """
    if not message or len(message.strip()) < 4:
        return False
    score = classify(message)
    return score >= GIBBERISH_THRESHOLD

async def is_duplicate_message(cur, user_id, server_id, channel_id, message: str) -> bool:
    await cur.execute(
        """
        SELECT message FROM messages 
        WHERE sent_by = %s AND server_id = %s AND channel_id = %s
        ORDER BY created_at DESC LIMIT 1 OFFSET 1
        """, (user_id, server_id, channel_id)
    )
    last_msg = await cur.fetchone()
    if not last_msg:
        return False

    if isinstance(last_msg, dict):
        last_msg_text = last_msg.get('message', '')
    else:
        last_msg_text = last_msg[0]

    return last_msg_text.strip() == message.strip()

async def addKudos(cur, user_id, kudos_to_add, message, server_id, channel_id):
    if is_gibberish(message):
        await addMessageToLogs(f"Rejected gibberish/keysmash message from user id: {user_id}", "WARN")
        return False

    if await is_duplicate_message(cur, user_id, server_id, channel_id, message):
        await addMessageToLogs(f"Rejected duplicate message from user id: {user_id}", "WARN")
        return False

    await cur.execute(
        """
        SELECT created_at FROM messages
        WHERE sent_by = %s AND server_id = %s AND channel_id = %s
        ORDER BY created_at DESC LIMIT 3
        """, (user_id, server_id, channel_id)
    )
    recent = await cur.fetchall()
    if len(recent) >= 2:
        t1 = recent[0][0] if isinstance(recent[0], tuple) else recent[0].get('created_at')
        t2 = recent[1][0] if isinstance(recent[1], tuple) else recent[1].get('created_at')
        if t1 and t2 and (t1 - t2).total_seconds() < 20:
            await addMessageToLogs(f"Rejected spam message from user id: {user_id}", "WARN")
            return False

    await cur.execute("INSERT INTO Kudos (user_id, kudo_amount) VALUES (%s, %s)", (user_id, kudos_to_add))
    await addMessageToLogs(
        f"Inserted {kudos_to_add} kudo{'s' if kudos_to_add > 1 else ''} for user id: {user_id}", 
        "INFO"
    )
    return True
