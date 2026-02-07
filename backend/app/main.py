from fastapi import FastAPI
# from app.core.logging import setup_logging
from app.api.v1.router import router as api_router
from app.core.config import settings


# setup_logging()

app = FastAPI(title=settings.APP_NAME)

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
