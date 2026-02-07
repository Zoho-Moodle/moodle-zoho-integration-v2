"""
Zoho CRM API Integration

This module provides a complete Zoho CRM API client following ZOHO_API_CONTRACT.md strictly.

⚠️ IMPORTANT: All module and field names MUST match the contract exactly.

Modules (API Names):
- Products (BTEC Programs)
- BTEC (BTEC Units)
- BTEC_Students, BTEC_Teachers, BTEC_Classes, etc.

Forbidden:
- NO SRM_* fields (legacy)
- NO invented field names
- NO Student subforms except Learning_Outcomes_Assessm
"""

from .client import ZohoClient
from .auth import ZohoAuthClient
from .config import create_zoho_client, ZohoSettings
from .exceptions import (
    ZohoAPIError,
    ZohoAuthError,
    ZohoNotFoundError,
    ZohoRateLimitError,
    ZohoValidationError
)

__all__ = [
    'ZohoClient',
    'ZohoAuthClient',
    'create_zoho_client',
    'ZohoSettings',
    'ZohoAPIError',
    'ZohoAuthError',
    'ZohoNotFoundError',
    'ZohoRateLimitError',
    'ZohoValidationError'
]
