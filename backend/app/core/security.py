"""
HMAC Signature Verification Utility

Verifies webhook authenticity using HMAC-SHA256 signatures.
"""

import hmac
import hashlib
import logging
from typing import Optional

logger = logging.getLogger(__name__)


class HMACVerifier:
    """
    HMAC signature verification for webhook security.
    
    Prevents unauthorized webhook calls by verifying HMAC-SHA256 signatures.
    """
    
    @staticmethod
    def generate_signature(payload: str, secret: str) -> str:
        """
        Generate HMAC-SHA256 signature for payload.
        
        Args:
            payload: Raw request body as string
            secret: HMAC secret key
            
        Returns:
            Hex-encoded HMAC signature
        """
        signature = hmac.new(
            secret.encode('utf-8'),
            payload.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()
        
        return signature
    
    @staticmethod
    def verify_signature(
        payload: str,
        signature: str,
        secret: str,
        algorithm: str = "sha256"
    ) -> bool:
        """
        Verify HMAC signature matches payload.
        
        Args:
            payload: Raw request body as string
            signature: HMAC signature from request header
            secret: HMAC secret key
            algorithm: Hash algorithm (default: sha256)
            
        Returns:
            True if signature is valid, False otherwise
        """
        try:
            # Generate expected signature
            expected_signature = HMACVerifier.generate_signature(payload, secret)
            
            # Compare signatures (constant-time comparison to prevent timing attacks)
            is_valid = hmac.compare_digest(signature, expected_signature)
            
            if not is_valid:
                logger.warning(
                    f"HMAC signature mismatch. "
                    f"Expected: {expected_signature[:10]}..., "
                    f"Got: {signature[:10]}..."
                )
            
            return is_valid
            
        except Exception as e:
            logger.error(f"HMAC verification error: {e}")
            return False
    
    @staticmethod
    def extract_signature_from_header(
        header_value: Optional[str],
        prefix: str = "sha256="
    ) -> Optional[str]:
        """
        Extract signature from header value.
        
        Some webhook providers prefix the signature (e.g., "sha256=abc123").
        This method handles that.
        
        Args:
            header_value: Full header value
            prefix: Expected prefix (default: "sha256=")
            
        Returns:
            Extracted signature or None
        """
        if not header_value:
            return None
        
        if header_value.startswith(prefix):
            return header_value[len(prefix):]
        
        return header_value


class ZohoHMACVerifier:
    """
    Zoho-specific HMAC verification.
    
    Zoho CRM sends HMAC signatures in X-Zoho-Signature header.
    """
    
    @staticmethod
    def verify(
        payload: str,
        signature_header: str,
        secret: str
    ) -> bool:
        """
        Verify Zoho webhook signature.
        
        Args:
            payload: Raw request body
            signature_header: X-Zoho-Signature header value
            secret: Zoho HMAC secret
            
        Returns:
            True if valid, False otherwise
        """
        # Extract signature (Zoho may prefix with algorithm)
        signature = HMACVerifier.extract_signature_from_header(
            signature_header,
            prefix="sha256="
        )
        
        if not signature:
            logger.warning("No signature found in Zoho webhook header")
            return False
        
        return HMACVerifier.verify_signature(payload, signature, secret)


class MoodleHMACVerifier:
    """
    Moodle-specific HMAC verification.
    
    Custom Moodle webhooks send HMAC signatures in X-Moodle-Signature header.
    """
    
    @staticmethod
    def verify(
        payload: str,
        signature_header: str,
        secret: str
    ) -> bool:
        """
        Verify Moodle webhook signature.
        
        Args:
            payload: Raw request body
            signature_header: X-Moodle-Signature header value
            secret: Moodle HMAC secret
            
        Returns:
            True if valid, False otherwise
        """
        # Extract signature
        signature = HMACVerifier.extract_signature_from_header(
            signature_header,
            prefix=""  # Moodle sends raw signature
        )
        
        if not signature:
            logger.warning("No signature found in Moodle webhook header")
            return False
        
        return HMACVerifier.verify_signature(payload, signature, secret)


# ============================================================================
# Webhook Security Utilities
# ============================================================================

def verify_webhook_signature(
    source: str,
    payload: str,
    signature_header: Optional[str],
    secret: str
) -> bool:
    """
    Verify webhook signature based on source.
    
    Args:
        source: 'zoho' or 'moodle'
        payload: Raw request body
        signature_header: Signature header value
        secret: HMAC secret key
        
    Returns:
        True if signature is valid, False otherwise
    """
    if not signature_header:
        logger.warning(f"No signature header provided for {source} webhook")
        return False
    
    if source == "zoho":
        return ZohoHMACVerifier.verify(payload, signature_header, secret)
    elif source == "moodle":
        return MoodleHMACVerifier.verify(payload, signature_header, secret)
    else:
        logger.error(f"Unknown webhook source: {source}")
        return False
