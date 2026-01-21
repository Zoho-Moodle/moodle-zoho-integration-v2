from sqlalchemy.orm import sessionmaker
from app.infra.db.base import engine

# Session factory
SessionLocal = sessionmaker(
    bind=engine,
    autocommit=False,
    autoflush=False,
)

# ✅ هذا هو المهم (Dependency)
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

