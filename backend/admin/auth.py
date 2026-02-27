"""
Admin panel authentication & session management.

Users stored in: backend/admin_users.json
Sessions stored in:  in-memory dict (TTL = 8 hours)
Passwords hashed with: hashlib.sha256 (simple, no extra deps)
"""

import hashlib
import json
import os
import secrets
import time
from pathlib import Path
from typing import Optional

# -------------------------------------------------------------------
# Config
# -------------------------------------------------------------------
USERS_FILE = Path(__file__).parent.parent / "admin_users.json"
SESSION_TTL = 60 * 60 * 8  # 8 hours
COOKIE_NAME = "mzi_admin_session"

# In-memory session store: { token: { "username": .., "role": .., "expires": .. } }
_SESSIONS: dict = {}

ROLES = {"admin", "operator"}

# -------------------------------------------------------------------
# Password helpers
# -------------------------------------------------------------------

def _hash_password(password: str) -> str:
    """SHA-256 hash (simple, no extra deps required)."""
    return hashlib.sha256(password.encode()).hexdigest()


# -------------------------------------------------------------------
# User store (JSON file)
# -------------------------------------------------------------------

def _load_users() -> dict:
    """Load users from JSON file, create default admin if missing."""
    if not USERS_FILE.exists():
        default = {
            "admin": {
                "password_hash": _hash_password("admin123"),
                "role": "admin",
                "full_name": "Administrator",
                "created_at": int(time.time()),
            }
        }
        _save_users(default)
        return default
    with open(USERS_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


def _save_users(users: dict) -> None:
    USERS_FILE.parent.mkdir(parents=True, exist_ok=True)
    with open(USERS_FILE, "w", encoding="utf-8") as f:
        json.dump(users, f, indent=2, ensure_ascii=False)


# -------------------------------------------------------------------
# Auth API
# -------------------------------------------------------------------

def authenticate(username: str, password: str) -> Optional[dict]:
    """Verify credentials. Returns user dict (without hash) or None."""
    users = _load_users()
    user = users.get(username)
    if not user:
        return None
    if user["password_hash"] != _hash_password(password):
        return None
    return {"username": username, "role": user["role"], "full_name": user.get("full_name", username)}


def create_session(username: str, role: str, full_name: str) -> str:
    """Generate a session token and store it in memory."""
    token = secrets.token_urlsafe(32)
    _SESSIONS[token] = {
        "username": username,
        "role": role,
        "full_name": full_name,
        "expires": time.time() + SESSION_TTL,
    }
    return token


def get_session(token: str) -> Optional[dict]:
    """Validate session token and return session data, or None if invalid/expired."""
    if not token:
        return None
    session = _SESSIONS.get(token)
    if not session:
        return None
    if time.time() > session["expires"]:
        del _SESSIONS[token]
        return None
    return session


def destroy_session(token: str) -> None:
    _SESSIONS.pop(token, None)


# -------------------------------------------------------------------
# User management
# -------------------------------------------------------------------

def list_users() -> list:
    """Return all users (without password hashes)."""
    users = _load_users()
    result = []
    for username, data in users.items():
        result.append({
            "username": username,
            "role": data["role"],
            "full_name": data.get("full_name", username),
            "created_at": data.get("created_at", 0),
        })
    return sorted(result, key=lambda u: u["created_at"])


def create_user(username: str, password: str, role: str, full_name: str) -> bool:
    """Create a new user. Returns False if username already exists."""
    if role not in ROLES:
        raise ValueError(f"Invalid role: {role}. Must be one of {ROLES}")
    users = _load_users()
    if username in users:
        return False
    users[username] = {
        "password_hash": _hash_password(password),
        "role": role,
        "full_name": full_name,
        "created_at": int(time.time()),
    }
    _save_users(users)
    return True


def delete_user(username: str) -> bool:
    """Delete a user. Returns False if not found. Cannot delete last admin."""
    users = _load_users()
    if username not in users:
        return False
    # Prevent deleting last admin
    admins = [u for u, d in users.items() if d["role"] == "admin"]
    if len(admins) == 1 and users[username]["role"] == "admin":
        raise ValueError("Cannot delete the last admin user.")
    del users[username]
    _save_users(users)
    return True


def change_password(username: str, new_password: str) -> bool:
    """Change a user's password."""
    users = _load_users()
    if username not in users:
        return False
    users[username]["password_hash"] = _hash_password(new_password)
    _save_users(users)
    return True
