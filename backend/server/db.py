import os
import aiomysql
from dotenv import load_dotenv
import asyncio

load_dotenv()

DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")

_pool = None

async def get_db_pool(retries=30, delay=2):
    """Returns a singleton MySQL connection pool, retrying until the DB is ready."""
    global _pool
    if _pool is not None and not _pool.closed:
        return _pool

    for attempt in range(1, retries + 1):
        try:
            _pool = await aiomysql.create_pool(
                host=os.getenv("DB_HOST", "db"),
                user=os.getenv("DB_USER", "root"),
                password=os.getenv("DB_PASSWORD", "dev"),
                db=os.getenv("DB_NAME", "wokki_chat"),
                port=3306,
                autocommit=True,
                charset='utf8mb4',
                maxsize=20,
                connect_timeout=5
            )
            return _pool
        except aiomysql.OperationalError as e:
            print(f"[DB] Attempt {attempt}/{retries} failed: {e}")
            await asyncio.sleep(delay)

    raise RuntimeError("Could not connect to the database after multiple attempts")