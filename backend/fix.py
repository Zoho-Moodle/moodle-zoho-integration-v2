# Database fix
import sys
sys.path.insert(0, '.')
from sqlalchemy import create_engine, text
from app.core.config import settings

e = create_engine(settings.DATABASE_URL)
with e.connect() as c:
    cmds = [
        "ALTER TABLE students ADD COLUMN IF NOT EXISTS username VARCHAR UNIQUE",
        "ALTER TABLE students ADD COLUMN IF NOT EXISTS fingerprint VARCHAR",
        "ALTER TABLE students ADD COLUMN IF NOT EXISTS moodle_userid INTEGER",
    ]
    for cmd in cmds:
        try:
            c.execute(text(cmd))
            c.commit()
            print("OK: " + cmd[:50])
        except Exception as ex:
            print("ERR: " + str(ex)[:60])
print("Done")
e.dispose()
