from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
# from app.core.logging import setup_logging
from app.api.v1.router import router as api_router
from app.core.config import settings
import logging

logger = logging.getLogger(__name__)

# setup_logging()

app = FastAPI(title=settings.APP_NAME)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify Moodle domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(api_router, prefix="/api/v1")

# Health check endpoint
@app.get("/health")
async def health_check():
    """
    Simple health check endpoint for monitoring.
    Returns 200 OK if the service is running.
    """
    return {
        "status": "healthy",
        "service": settings.APP_NAME,
        "version": "3.1.1"
    }
