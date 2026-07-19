from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.services.add_data.wishlist_movie_cover import (
    WishlistMovieUploadError,
    create_wishlist_movie_with_cover,
)

router = APIRouter(
    prefix="/wishlist",
    tags=["wishlist"],
)


@router.post("/movies")
async def create_wishlist_movie(
    title: str = Form(...),
    original_title: str | None = Form(None),
    first_release_year: int | None = Form(None),
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
            cover_bytes=cover_bytes,
            cover_content_type=cover_image.content_type,
        )
    except WishlistMovieUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
