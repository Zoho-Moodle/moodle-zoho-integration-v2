"""
Pytest configuration and fixtures for integration tests
"""
import pytest
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker, Session
from app.core.config import settings
from app.infra.db.base import Base


@pytest.fixture(scope="function")
def db():
    """
    Create a database session for testing.
    Each test gets a fresh session with clean slate.
    """
    # Create engine
    engine = create_engine(settings.DATABASE_URL)
    
    # Create session
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    session = SessionLocal()
    
    # Clean up test data before test (comprehensive cleanup for all test patterns)
    session.execute(text("DELETE FROM grades WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%grade%'"))
    session.execute(text("DELETE FROM payments WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%pay%'"))
    session.execute(text("DELETE FROM registrations WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%reg%'"))
    session.execute(text("DELETE FROM units WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%grade%'"))
    session.execute(text("DELETE FROM students WHERE zoho_id LIKE '%test%' OR zoho_id LIKE 'stud_%'"))
    session.execute(text("DELETE FROM programs WHERE zoho_id LIKE '%test%' OR zoho_id LIKE 'prog_%'"))
    session.commit()
    
    yield session
    
    # Clean up test data after test
    session.execute(text("DELETE FROM grades WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%grade%'"))
    session.execute(text("DELETE FROM payments WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%pay%'"))
    session.execute(text("DELETE FROM registrations WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%reg%'"))
    session.execute(text("DELETE FROM units WHERE zoho_id LIKE '%test%' OR zoho_id LIKE '%batch%' OR zoho_id LIKE '%grade%'"))
    session.execute(text("DELETE FROM students WHERE zoho_id LIKE '%test%' OR zoho_id LIKE 'stud_%'"))
    session.execute(text("DELETE FROM programs WHERE zoho_id LIKE '%test%' OR zoho_id LIKE 'prog_%'"))
    session.commit()
    session.close()


@pytest.fixture(scope="function")
def db_session(db: Session):
    """Alias fixture for db."""
    return db
