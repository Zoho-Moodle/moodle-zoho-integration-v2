from fastapi import FastAPI
from app.api.v1.router import router as api_router

app = FastAPI(title="Moodle x Zoho Integration")

app.include_router(api_router, prefix="/v1")


@app.get("/health")
def health():
    return {"status": "ok"}
