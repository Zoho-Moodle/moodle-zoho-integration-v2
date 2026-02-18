from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    # Database Configuration (Backend DB - PostgreSQL/SQLite)
    DATABASE_URL: str = "sqlite:///./test.db"
    
    # Moodle Database Configuration (Direct DB access for Student Dashboard)
    MOODLE_DB_URL: Optional[str] = None  # mysql://user:pass@host:3306/moodle_db or mariadb://...
    MOODLE_DB_ENABLED: bool = False

    # App Configuration
    APP_NAME: str = "Moodle Zoho Integration"
    ENV: str = "development"
    LOG_LEVEL: str = "INFO"

    # Moodle Configuration (API access)
    MOODLE_BASE_URL: Optional[str] = None
    MOODLE_TOKEN: Optional[str] = None
    MOODLE_ENABLED: bool = False

    # Zoho CRM Configuration
    ZOHO_CLIENT_ID: Optional[str] = None
    ZOHO_CLIENT_SECRET: Optional[str] = None
    ZOHO_REFRESH_TOKEN: Optional[str] = None
    ZOHO_ORGANIZATION_ID: Optional[str] = None
    ZOHO_REGION: str = "com"
    ZOHO_TIMEOUT: float = 30.0

    # Webhook Security
    ZOHO_WEBHOOK_SECRET: Optional[str] = None
    ZOHO_WEBHOOK_HMAC_SECRET: Optional[str] = None
    MOODLE_WEBHOOK_SECRET: Optional[str] = None

    # Multi-tenancy Configuration
    DEFAULT_TENANT_ID: str = "default"
    class Config:
        env_file = ".env"
        extra = "ignore"


# ✅ Instance of settings - loaded from .env
settings = Settings()