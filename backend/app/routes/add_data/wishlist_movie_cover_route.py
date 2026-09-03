from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from sqlalchemy.orm import Session

from app.api_key import require_api_key
from app.media_db import get_media_db
from app.services.add_data.wishlist_movie_cover import (
    WishlistMovieUploadError,
    create_wishlist_movie_with_cover,
    list_wishlist_movies,
)

router = APIRouter(
    prefix="/wishlist",
    tags=["wishlist"],
)


@router.get("/movies", dependencies=[Depends(require_api_key)])
async def get_wishlist_movies(db: Session = Depends(get_media_db)):
    return list_wishlist_movies(db)


@router.post("/movies")
async def create_wishlist_movie(
    title: str = Form(...),
    original_title: str | None = Form(None),
    first_release_year: int | None = Form(None),
    imdb_id: str | None = Form(None),
    tmdb_id: str | None = Form(None),
    tvdb_id: str | None = Form(None),
    cover_image: UploadFile = File(...),
    db: Session = Depends(get_media_db),
):
    try:
        cover_bytes = await cover_image.read()
        return create_wishlist_movie_with_cover(
            db=db,
            title=title,
            original_title=original_title,
            first_release_year=first_release_year,
            imdb_id=imdb_id,
            tmdb_id=tmdb_id,
            tvdb_id=tvdb_id,
            cover_bytes=cover_bytes,
            cover_content_type=cover_image.content_type,
        )
    except WishlistMovieUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
