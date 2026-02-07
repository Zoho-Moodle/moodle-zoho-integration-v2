"""
Zoho OAuth 2.0 Authentication Client

Handles token refresh and access token management.
"""

import logging
from datetime import datetime, timedelta
from typing import Optional
import httpx

from .exceptions import ZohoAuthError

logger = logging.getLogger(__name__)


class ZohoAuthClient:
    """
    Zoho OAuth 2.0 client for managing access tokens.
    
    Uses refresh token to obtain short-lived access tokens.
    Automatically refreshes when token expires.
    
    Usage:
        auth = ZohoAuthClient(
            client_id=settings.ZOHO_CLIENT_ID,
            client_secret=settings.ZOHO_CLIENT_SECRET,
            refresh_token=settings.ZOHO_REFRESH_TOKEN
        )
        access_token = await auth.get_access_token()
    """
    
    BASE_URL = "https://accounts.zoho.com/oauth/v2"
    
    def __init__(
        self,
        client_id: str,
        client_secret: str,
        refresh_token: str,
        region: str = "com"  # com, eu, in, au, etc.
    ):
        """
        Initialize Zoho auth client.
        
        Args:
            client_id: Zoho OAuth client ID
            client_secret: Zoho OAuth client secret
            refresh_token: Long-lived refresh token
            region: Zoho data center (com, eu, in, au)
        """
        if not client_id or not client_secret or not refresh_token:
            raise ValueError("client_id, client_secret, and refresh_token are required")
        
        self.client_id = client_id
        self.client_secret = client_secret
        self.refresh_token = refresh_token
        self.region = region
        
        # Token cache
        self._access_token: Optional[str] = None
        self._expires_at: Optional[datetime] = None
        
        # Update base URL for region
        if region != "com":
            self.BASE_URL = f"https://accounts.zoho.{region}/oauth/v2"
    
    async def get_access_token(self, force_refresh: bool = False) -> str:
        """
        Get valid access token.
        
        Returns cached token if still valid, otherwise refreshes.
        
        Args:
            force_refresh: Force token refresh even if cached token is valid
        
        Returns:
            Valid access token
        
        Raises:
            ZohoAuthError: If token refresh fails
        """
        # Return cached token if valid
        if not force_refresh and self._is_token_valid():
            logger.debug("Using cached Zoho access token")
            return self._access_token
        
        # Refresh token
        logger.info("Refreshing Zoho access token")
        await self._refresh_access_token()
        return self._access_token
    
    def _is_token_valid(self) -> bool:
        """Check if cached token is still valid."""
        if not self._access_token or not self._expires_at:
            return False
        
        # Consider token invalid 5 minutes before expiry (safety margin)
        return datetime.now() < (self._expires_at - timedelta(minutes=5))
    
    async def _refresh_access_token(self) -> None:
        """
        Refresh access token using refresh token.
        
        Raises:
            ZohoAuthError: If refresh fails
        """
        url = f"{self.BASE_URL}/token"
        
        params = {
            'refresh_token': self.refresh_token,
            'client_id': self.client_id,
            'client_secret': self.client_secret,
            'grant_type': 'refresh_token'
        }
        
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(url, params=params)
                
                if response.status_code != 200:
                    error_data = response.json() if response.text else {}
                    raise ZohoAuthError(
                        f"Token refresh failed: {error_data.get('error', 'Unknown error')}",
                        status_code=response.status_code,
                        response_data=error_data
                    )
                
                data = response.json()
                
                # Validate response
                if 'access_token' not in data:
                    raise ZohoAuthError(
                        "Invalid token response: missing access_token",
                        response_data=data
                    )
                
                # Cache token
                self._access_token = data['access_token']
                expires_in = data.get('expires_in', 3600)  # Default 1 hour
                self._expires_at = datetime.now() + timedelta(seconds=expires_in)
                
                logger.info(
                    f"Access token refreshed successfully. Expires in {expires_in}s"
                )
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error during token refresh: {e}")
            raise ZohoAuthError(f"Token refresh failed: {str(e)}")
        
        except Exception as e:
            logger.error(f"Unexpected error during token refresh: {e}")
            raise ZohoAuthError(f"Token refresh failed: {str(e)}")
    
    async def revoke_token(self) -> None:
        """
        Revoke the refresh token.
        
        Use this when deactivating integration or for security cleanup.
        """
        url = f"{self.BASE_URL}/token/revoke"
        
        params = {
            'token': self.refresh_token
        }
        
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(url, params=params)
                
                if response.status_code == 200:
                    logger.info("Refresh token revoked successfully")
                    self._access_token = None
                    self._expires_at = None
                else:
                    logger.warning(f"Token revoke returned status {response.status_code}")
        
        except Exception as e:
            logger.error(f"Error revoking token: {e}")
            raise ZohoAuthError(f"Token revoke failed: {str(e)}")
