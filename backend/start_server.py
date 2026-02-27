#!/usr/bin/env python
"""Start the FastAPI server"""
import os
import sys

# Ensure backend is in path and working directory is set to backend/
# so that the .env file is loaded from the correct location
backend_dir = os.path.dirname(os.path.abspath(__file__))
os.chdir(backend_dir)
sys.path.insert(0, backend_dir)

import uvicorn
import logging

# Configure root logger so all app loggers (INFO+) appear in the terminal.
logging.basicConfig(
    level=logging.INFO,
    format="%(levelname)s:     %(name)s - %(message)s",
)

# Suppress noisy "Invalid HTTP request received" warnings from uvicorn.
# These are caused by ngrok sending HTTP/2 upgrade / TLS keepalive probes
# to the plain-HTTP uvicorn server — harmless but spammy.
logging.getLogger("uvicorn.error").addFilter(
    type("_IgnoreInvalidHTTP", (logging.Filter,), {
        "filter": lambda self, record: "Invalid HTTP request received" not in record.getMessage()
    })()
)

if __name__ == "__main__":
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8001,
        reload=False,
        log_level="info",
    )
