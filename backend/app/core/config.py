from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    # Database Configuration
    DATABASE_URL: str

    # App Configuration
    APP_NAME: str = "Moodle Zoho Integration"
    ENV: str = "development"
    LOG_LEVEL: str = "INFO"

    # Moodle Configuration
    MOODLE_BASE_URL: Optional[str] = None
    MOODLE_TOKEN: Optional[str] = None
    MOODLE_ENABLED: bool = False

    # Multi-tenancy Configuration
    DEFAULT_TENANT_ID: str = "default"
    class Config:
        env_file = ".env"
        extra = "ignore"


# ✅ Instance of settings - loaded from .env
settings = Settings()