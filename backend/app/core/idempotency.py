import time
import hashlib
import json
from typing import Dict, Any, Optional


class InMemoryIdempotencyStore:
    """
    Simple in-memory idempotency store with TTL support.
    Prevents duplicate processing of the same request.
    """
    
    def __init__(self, ttl_seconds: int = 3600) -> None:
        self._store: Dict[str, tuple[float, Any]] = {}
        self.ttl_seconds = ttl_seconds

    def generate_key(self, payload: dict) -> str:
        """Generate a hash key from the payload"""
        try:
            payload_str = json.dumps(payload, sort_keys=True, default=str)
            return hashlib.md5(payload_str.encode()).hexdigest()
        except Exception:
            # Fallback: use timestamp if serialization fails
            return hashlib.md5(str(time.time()).encode()).hexdigest()

    def is_duplicate(self, key: str) -> bool:
        """Check if key was already processed"""
        self.cleanup()
        return key in self._store

    def mark_processed(self, key: str) -> None:
        """Mark key as processed"""
        self._store[key] = (time.time(), None)

    def get(self, key: str) -> Optional[Any]:
        """Get cached result or None if expired/missing"""
        self.cleanup()
        if key in self._store:
            timestamp, value = self._store[key]
            return value
        return None
    
    def set(self, key: str, value: Any) -> None:
        """Store result with timestamp"""
        self._store[key] = (time.time(), value)

    def cleanup(self) -> None:
        """Remove expired entries"""
        now = time.time()
        self._store = {
            k: v for k, v in self._store.items()
            if now - v[0] < self.ttl_seconds
        }


def compute_request_hash(payload: dict) -> str:
    """
    Compute SHA256 hash of a request payload for idempotency tracking.
    
    Args:
        payload: Dictionary to hash
        
    Returns:
        Hex digest of SHA256 hash
    """
    try:
        payload_str = json.dumps(payload, sort_keys=True, default=str)
        return hashlib.sha256(payload_str.encode()).hexdigest()
    except Exception:
        # Fallback: use timestamp if serialization fails
        return hashlib.sha256(str(time.time()).encode()).hexdigest()


# ✅ Global singleton instance
idempotency_store = InMemoryIdempotencyStore()
