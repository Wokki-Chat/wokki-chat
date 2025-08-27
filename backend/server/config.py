import os
import re
from collections import defaultdict, deque
from dotenv import load_dotenv

load_dotenv()

# --------------------
# API Keys
# --------------------
API_KEY = os.getenv("API_KEY")
API_SECRET = os.getenv("API_SECRET")

# --------------------
# LiveKit / voice
# --------------------
LIVEKIT_BASE_URL = os.getenv("LIVEKIT_BASE_URL", "http://localhost:7880")

# --------------------
# Runtime / in-memory state
# --------------------
user_to_sid = {}
sid_to_bot_id = {}
pending_disconnects = {}
user_message_timestamps = defaultdict(lambda: deque())
bot_message_timestamps = defaultdict(lambda: deque())
user_current_room = {}
room = {}

pool = None

typing_users = set()
typing_lock = None

# --------------------
# Rate limiting
# --------------------
MAX_MESSAGES = 10
TIME_WINDOW_SECONDS = 3

# --------------------
# Regex / validation patterns
# --------------------
HEX_COLOR_RE = re.compile(r'^#([0-9A-Fa-f]{6})$')
