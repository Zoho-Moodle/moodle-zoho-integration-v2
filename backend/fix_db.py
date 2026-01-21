#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Database Fix - Add Missing Columns
"""

from sqlalchemy import create_engine, text
from app.core.config import settings

engine = create_engine(settings.DATABASE_URL, echo=False)

sql_commands = [
    "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS username VARCHAR UNIQUE;",
    "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS fingerprint VARCHAR;",
    "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS moodle_userid INTEGER;",
    "CREATE INDEX IF NOT EXISTS idx_username ON students(username);",
    "CREATE INDEX IF NOT EXISTS idx_moodle_userid ON students(moodle_userid);",
]

print("[*] Applying missing columns...\n")

with engine.connect() as conn:
    for cmd in sql_commands:
        try:
            conn.execute(text(cmd))
            conn.commit()
            print(f"[OK] {cmd}")
        except Exception as e:
            print(f"[!] {cmd}\n    Error: {str(e)[:80]}")

print("\n[OK] Database update complete!")
engine.dispose()
