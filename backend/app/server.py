from fastapi import FastAPI

from app.db import init_db

from app.routes.add_data.physical_collection_import_route import router as physical_collection_import_router
from app.routes.add_data.wishlist_movie_cover_route import router as wishlist_movie_cover_router
from app.routes.add_data.custom_list_manager_route import router as custom_list_manager_router
from app.routes.media_catalog_route import router as media_catalog_router
from app.routes.auth_route import router as auth_router
from app.routes.section_access_route import router as section_access_router

# =========================================================
# Init DB ved oppstart (opprett tabeller om nødvendig)
# =========================================================
init_db()

# =========================================================
# FastAPI app
# =========================================================

app = FastAPI(title="Mitt Mediearkiv API")

# =========================================================
# Ruter
# =========================================================

app.include_router(physical_collection_import_router)
app.include_router(wishlist_movie_cover_router)
app.include_router(custom_list_manager_router)
app.include_router(media_catalog_router)
app.include_router(auth_router)
app.include_router(section_access_router)

@app.get("/")
async def root():
    return {"message": "Mitt Mediearkiv API"}


@app.get("/health")
async def health_check():
    return {"status": "ok"}


# =========================================================
# Lokal kjøring
# =========================================================

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)

