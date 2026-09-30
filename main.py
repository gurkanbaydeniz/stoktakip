from pathlib import Path

from fastapi import FastAPI
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles

BASE_DIR = Path(__file__).resolve().parent
STATIC_DIR = BASE_DIR / "static"

app = FastAPI(
    title="Mikro Mola",
    description="Bilgisayar başında çalışanlar için esneme ve su içme takip uygulaması",
    version="1.0.0",
)

app.mount("/static", StaticFiles(directory=STATIC_DIR), name="static")


@app.get("/")
async def index():
    """Ana uygulamayı sunar."""
    return FileResponse(STATIC_DIR / "index.html")


@app.get("/api/health")
async def health():
    """Basit sağlık kontrolü."""
    return {"status": "ok", "app": "mikro-mola", "version": "1.0.0"}
