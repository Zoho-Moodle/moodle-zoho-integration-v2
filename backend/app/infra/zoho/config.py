"""
Zoho Integration Configuration

Settings and factory functions for Zoho client.
"""

import os
from typing import Optional
from pydantic_settings import BaseSettings
from pydantic import Field

from .auth import ZohoAuthClient
from .client import ZohoClient


class ZohoSettings(BaseSettings):
    """
    Zoho API configuration settings.
    
    Load from environment variables with ZOHO_ prefix.
    """
    
    client_id: str = Field(..., description="Zoho OAuth client ID")
    client_secret: str = Field(..., description="Zoho OAuth client secret")
    refresh_token: str = Field(..., description="Zoho OAuth refresh token")
    organization_id: Optional[str] = Field(None, description="Zoho organization ID")
    region: str = Field("com", description="Zoho data center region")
    timeout: float = Field(30.0, description="API request timeout in seconds")
    
    class Config:
        env_prefix = "ZOHO_"
        case_sensitive = False
        env_file = ".env"
        extra = "ignore"


def create_zoho_client(
    client_id: Optional[str] = None,
    client_secret: Optional[str] = None,
    refresh_token: Optional[str] = None,
    organization_id: Optional[str] = None,
    region: str = "com",
    timeout: float = 30.0
) -> ZohoClient:
    """
    Factory function to create configured Zoho client.
    
    Args:
        client_id: OAuth client ID (or from ZOHO_CLIENT_ID env)
        client_secret: OAuth client secret (or from ZOHO_CLIENT_SECRET env)
        refresh_token: OAuth refresh token (or from ZOHO_REFRESH_TOKEN env)
        organization_id: Organization ID (or from ZOHO_ORGANIZATION_ID env)
        region: Data center region
        timeout: Request timeout
    
    Returns:
        Configured ZohoClient instance
    
    Example:
        # From environment variables
        zoho = create_zoho_client()
        
        # Explicit credentials
        zoho = create_zoho_client(
            client_id="1000.XXX",
            client_secret="xxx",
            refresh_token="1000.xxx"
        )
    """
    # Load from env if not provided
    if not all([client_id, client_secret, refresh_token]):
        settings = ZohoSettings()
        client_id = client_id or settings.client_id
        client_secret = client_secret or settings.client_secret
        refresh_token = refresh_token or settings.refresh_token
        organization_id = organization_id or settings.organization_id
        region = settings.region
        timeout = settings.timeout
    
    # Create auth client
    auth = ZohoAuthClient(
        client_id=client_id,
        client_secret=client_secret,
        refresh_token=refresh_token,
        region=region
    )
    
    # Create API client
    return ZohoClient(
        auth_client=auth,
        organization_id=organization_id,
        region=region,
        timeout=timeout
    )
