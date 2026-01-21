#!/usr/bin/env python3
import sys
sys.path.insert(0, '.')
from sqlalchemy import create_engine, text
from app.core.config import settings

e = create_engine(settings.DATABASE_URL)
with e.connect() as c:
    c.execute(text('ALTER TABLE students ALTER COLUMN userid DROP NOT NULL'))
    c.commit()
    print('OK: userid is now nullable')
e.dispose()
