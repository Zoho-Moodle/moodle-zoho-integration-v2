from pydantic_settings import BaseSettings, SettingsConfigDict
from typing import Optional
import os

# Resolve .env path relative to this file's directory (backend/app/core/ → backend/)
_ENV_FILE = os.path.join(os.path.dirname(__file__), "..", "..", ".env")
_ENV_FILE = os.path.normpath(_ENV_FILE)


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
    MOODLE_TOKEN: Optional[str] = None          # All WS functions (single unified service)
    MOODLE_ENABLED: bool = False
    MOODLE_DEFAULT_CATEGORY_ID: int = 1  # Default Moodle course category ID for new classes
    # Users enrolled in EVERY new Moodle course (IT Support, Student Affairs, CEO, Super Admin).
    # role IDs: 1=manager, 3=editingteacher, 4=non-editing teacher, 5=student
    # Default values match the original Zoho Deluge script IDs:
    #   8157=IT Support (role 3), 8181=Student Affairs (role 3),
    #   8154=CEO (role 3), 2=Moodle Super Admin (role 1)
    MOODLE_DEFAULT_COURSE_ENROLMENTS: str = (
        '[{"userid":8157,"roleid":3},{"userid":8181,"roleid":3},'
        '{"userid":8154,"roleid":3},{"userid":2,"roleid":1}]'
    )
    # Extra enrolments added ONLY when class_major == "IT"
    # 8133 = IT Program Leader (role 3)
    MOODLE_COURSE_ENROLMENTS_IT: str = '[{"userid":8133,"roleid":3}]'

    # Zoho CRM Configuration
    ZOHO_CLIENT_ID: Optional[str] = None
    ZOHO_ACCESS_TOKEN: Optional[str] = None  # Short-lived OAuth2 access token for Zoho API
    ZOHO_API_BASE_URL: str = "https://www.zohoapis.com/crm/v2"
    ZOHO_CLIENT_SECRET: Optional[str] = None
    ZOHO_REFRESH_TOKEN: Optional[str] = None
    ZOHO_ORGANIZATION_ID: Optional[str] = None
    ZOHO_REGION: str = "com"
    ZOHO_TIMEOUT: float = 30.0

    # Webhook Security
    ZOHO_WEBHOOK_SECRET: Optional[str] = None
    ZOHO_WEBHOOK_HMAC_SECRET: Optional[str] = None
    MOODLE_WEBHOOK_SECRET: Optional[str] = None

    # Webhook Base URL (ngrok in dev, public domain in prod)
    # Used by /admin/setup-zoho-webhooks to register notification URLs in Zoho CRM
    WEBHOOK_BASE_URL: Optional[str] = None

    # Multi-tenancy Configuration
    DEFAULT_TENANT_ID: str = "default"

    model_config = SettingsConfigDict(env_file=_ENV_FILE, extra="ignore")


# ✅ Instance of settings - loaded from .env
settings = Settings()