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

async def get_db_pool():
    """Returns a singleton MySQL connection pool."""
    global _pool
    if _pool is None:
        loop = asyncio.get_running_loop()
        try:
            _pool = await aiomysql.create_pool(
                host=DB_HOST,
                user=DB_USER,
                password=DB_PASSWORD,
                db=DB_NAME,
                port=3306,
                autocommit=True,
                charset='utf8mb4',
                maxsize=10,
                loop=loop
            )
        except aiomysql.OperationalError as e:
            raise RuntimeError(f"Failed to connect to DB: {e}")
    return _pool
