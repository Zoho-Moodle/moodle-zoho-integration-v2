"""
Zoho API Custom Exceptions
"""


class ZohoAPIError(Exception):
    """Base exception for all Zoho API errors."""
    
    def __init__(self, message: str, status_code: int = None, response_data: dict = None):
        self.message = message
        self.status_code = status_code
        self.response_data = response_data or {}
        super().__init__(self.message)


class ZohoAuthError(ZohoAPIError):
    """Authentication/authorization errors."""
    pass


class ZohoNotFoundError(ZohoAPIError):
    """Resource not found (404)."""
    pass


class ZohoRateLimitError(ZohoAPIError):
    """Rate limit exceeded (429)."""
    
    def __init__(self, message: str, retry_after: int = None, **kwargs):
        super().__init__(message, **kwargs)
        self.retry_after = retry_after  # Seconds to wait


class ZohoValidationError(ZohoAPIError):
    """Validation errors (400)."""
    pass


class ZohoInvalidModuleError(ZohoValidationError):
    """
    Invalid module name used.
    
    This error is raised when code attempts to use a module name
    that doesn't match ZOHO_API_CONTRACT.md.
    
    Common mistakes:
    - Using 'BTEC_Programs' instead of 'Products'
    - Using 'BTEC_Units' instead of 'BTEC'
    """
    pass
