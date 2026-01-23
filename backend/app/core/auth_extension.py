"""
HMAC Authentication for Extension API

Implements secure signature-based authentication:
- X-Ext-Key: API key identifier
- X-Ext-Timestamp: Request timestamp (Unix epoch)
- X-Ext-Nonce: Unique request identifier
- X-Ext-Signature: HMAC-SHA256 signature
- X-Tenant-ID: Tenant identifier

Signature = HMAC_SHA256(secret, "{timestamp}.{nonce}.{method}.{path}.{body_hash}")
"""

import hashlib
import hmac
import time
from typing import Optional
from fastapi import Header, HTTPException, Request, Depends
from sqlalchemy.orm import Session
from app.infra.db.session import get_db
from app.infra.db.models.extension import IntegrationSettings


# Nonce store (in-memory for MVP; use Redis in production)
_nonce_store = {}  # {tenant_id: {nonce: timestamp}}


def _clean_old_nonces(tenant_id: str):
    """Remove nonces older than 10 minutes"""
    cutoff = time.time() - 600  # 10 minutes
    if tenant_id in _nonce_store:
        _nonce_store[tenant_id] = {
            n: ts for n, ts in _nonce_store[tenant_id].items() 
            if ts > cutoff
        }


def _verify_signature(
    method: str,
    path: str,
    body: bytes,
    timestamp: str,
    nonce: str,
    signature: str,
    secret: str
) -> bool:
    """Verify HMAC-SHA256 signature (accepts both hex and base64 format)"""
    import base64
    
    body_hash = hashlib.sha256(body).hexdigest()
    message = f"{timestamp}.{nonce}.{method}.{path}.{body_hash}"
    
    expected_signature_hex = hmac.new(
        secret.encode('utf-8'),
        message.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    
    # Try hex comparison first
    if hmac.compare_digest(signature, expected_signature_hex):
        return True
    
    # If signature looks like base64, convert it to hex and compare
    try:
        if '/' in signature or '+' in signature or signature.endswith('='):
            # Decode base64 to bytes, then convert to hex
            signature_bytes = base64.b64decode(signature)
            signature_hex = signature_bytes.hex()
            return hmac.compare_digest(signature_hex, expected_signature_hex)
    except Exception:
        pass
    
    return False


async def verify_extension_auth(
    request: Request,
    x_ext_key: str = Header(..., alias="X-Ext-Key"),
    x_ext_timestamp: str = Header(..., alias="X-Ext-Timestamp"),
    x_ext_nonce: str = Header(..., alias="X-Ext-Nonce"),
    x_ext_signature: str = Header(..., alias="X-Ext-Signature"),
    x_tenant_id: str = Header(..., alias="X-Tenant-ID"),
    db: Session = Depends(get_db)
) -> dict:
    """
    Verify extension API authentication via HMAC signature.
    
    Returns:
        dict: {tenant_id, api_key, settings}
    
    Raises:
        HTTPException: 401 if auth fails
    """
    
    # 1. Validate timestamp (within 5 minutes)
    try:
        # Handle various timestamp formats from Zoho Deluge
        timestamp_str = str(x_ext_timestamp).strip()
        
        # If timestamp has decimals, remove them (e.g., "1737532845.0" -> "1737532845")
        if '.' in timestamp_str:
            timestamp_str = timestamp_str.split('.')[0]
        
        # Convert to float for validation
        req_timestamp = float(timestamp_str)
        now = time.time()
        if abs(now - req_timestamp) > 300:  # 5 minutes
            raise HTTPException(
                status_code=401,
                detail="Request timestamp outside acceptable window (5 minutes)"
            )
    except (ValueError, AttributeError) as e:
        raise HTTPException(status_code=401, detail=f"Invalid timestamp format: {x_ext_timestamp}")
    
    # 2. Check nonce uniqueness
    _clean_old_nonces(x_tenant_id)
    
    if x_tenant_id not in _nonce_store:
        _nonce_store[x_tenant_id] = {}
    
    if x_ext_nonce in _nonce_store[x_tenant_id]:
        raise HTTPException(status_code=401, detail="Nonce already used (replay attack detected)")
    
    # 3. Fetch tenant settings
    settings = db.query(IntegrationSettings).filter(
        IntegrationSettings.tenant_id == x_tenant_id,
        IntegrationSettings.extension_api_key == x_ext_key
    ).first()
    
    if not settings:
        raise HTTPException(status_code=401, detail="Invalid API key or tenant")
    
    # 4. Verify signature
    body = await request.body()
    
    if not _verify_signature(
        method=request.method,
        path=request.url.path,
        body=body,
        timestamp=x_ext_timestamp,
        nonce=x_ext_nonce,
        signature=x_ext_signature,
        secret=settings.extension_api_secret
    ):
        raise HTTPException(status_code=401, detail="Invalid signature")
    
    # 5. Store nonce
    _nonce_store[x_tenant_id][x_ext_nonce] = time.time()
    
    return {
        "tenant_id": x_tenant_id,
        "api_key": x_ext_key,
        "settings": settings
    }


# Dependency for protected endpoints
ExtensionAuth = Depends(verify_extension_auth)
