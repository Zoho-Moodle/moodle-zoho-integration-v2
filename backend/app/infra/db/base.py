from sqlalchemy import create_engine
from sqlalchemy.orm import declarative_base
from app.core.config import settings

# Database engine
engine = create_engine(
    settings.DATABASE_URL,
    pool_pre_ping=True,
    future=True,
)

# Declarative base for models
Base = declarative_base()

# Export both for external use
__all__ = ['Base', 'engine']

