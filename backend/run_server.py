#!/usr/bin/env python
"""Start the development server"""
import subprocess
import sys
import os

os.chdir(os.path.dirname(__file__))
subprocess.run([
    sys.executable, "-m", "uvicorn",
    "app.main:app",
    "--host", "127.0.0.1",
    "--port", "9000",
    "--reload"
])
