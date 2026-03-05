"""
In-memory HTTP access log  ·  ring buffer + ASGI middleware
Stores the last MAX_ENTRIES requests; newest-first on read.
"""
from __future__ import annotations

import time
from collections import deque
from dataclasses import dataclass
from datetime import datetime
from typing import Deque

# ── Config ────────────────────────────────────────────────────────────────────

MAX_ENTRIES = 2000
PER_PAGE    = 100

# ── Data model ────────────────────────────────────────────────────────────────

@dataclass
class AccessLogEntry:
    timestamp:   datetime
    method:      str
    path:        str
    query:       str
    status_code: int
    duration_ms: float
    client_ip:   str
    category:    str   # admin | api | webhook | sync | health | other


# ── Ring buffer ───────────────────────────────────────────────────────────────

_store: Deque[AccessLogEntry] = deque(maxlen=MAX_ENTRIES)


def _categorize(path: str) -> str:
    if path.startswith("/admin"):
        return "admin"
    if "/webhooks/" in path:
        return "webhook"
    if "/sync" in path or "/full-sync" in path:
        return "sync"
    if path == "/health":
        return "health"
    if path.startswith("/api"):
        return "api"
    return "other"


def _status_class(code: int) -> str:
    """Return a short CSS-friendly class name for a status code."""
    if code < 300:
        return "2xx"
    if code < 400:
        return "3xx"
    if code < 500:
        return "4xx"
    return "5xx"


# ── Query helper ──────────────────────────────────────────────────────────────

def get_entries(
    *,
    method:       str = "",
    category:     str = "",
    status_class: str = "",   # "2xx" | "3xx" | "4xx" | "5xx"
    search:       str = "",
    page:         int = 1,
    per_page:     int = PER_PAGE,
) -> tuple[list[AccessLogEntry], int]:
    """Return filtered, paginated entries (newest first)."""
    entries: list[AccessLogEntry] = list(reversed(_store))

    if method:
        entries = [e for e in entries if e.method == method.upper()]
    if category:
        entries = [e for e in entries if e.category == category]
    if status_class:
        prefix = status_class[0]          # "2", "3", "4", "5"
        entries = [e for e in entries if str(e.status_code).startswith(prefix)]
    if search:
        s = search.lower()
        entries = [
            e for e in entries
            if s in e.path.lower()
            or s in e.query.lower()
            or s in e.client_ip.lower()
        ]

    total = len(entries)
    start = (page - 1) * per_page
    return entries[start : start + per_page], total


# ── ASGI middleware ───────────────────────────────────────────────────────────

class AccessLogMiddleware:
    """
    Starlette-compatible middleware that appends every HTTP request
    to the in-memory ring buffer as an AccessLogEntry.
    """

    def __init__(self, app):
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        t0 = time.perf_counter()
        status_holder: list[int] = [0]

        async def _send(message):
            if message["type"] == "http.response.start":
                status_holder[0] = message.get("status", 0)
            await send(message)

        try:
            await self.app(scope, receive, _send)
        finally:
            duration_ms = round((time.perf_counter() - t0) * 1000, 1)
            path   = scope.get("path", "")
            query  = scope.get("query_string", b"").decode(errors="replace")
            method = scope.get("method", "")
            client = scope.get("client")
            ip     = client[0] if client else "—"

            _store.append(AccessLogEntry(
                timestamp   = datetime.now(),
                method      = method,
                path        = path,
                query       = query,
                status_code = status_holder[0],
                duration_ms = duration_ms,
                client_ip   = ip,
                category    = _categorize(path),
            ))
