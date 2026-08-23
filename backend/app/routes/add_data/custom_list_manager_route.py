from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.services.add_data.custom_list_manager import (
    add_item_to_custom_list,
    create_custom_list,
    list_custom_lists,
)
from app.services.add_data.list_item_shared import ListItemUploadError
from app.services.add_data.wishlist_movie_cover import WISHLIST_LIST_NAME

router = APIRouter(
    prefix="/lists",
    tags=["custom-lists"],
)


@router.get("")
async def get_custom_lists(
    include_wishlist: bool = False,
    db: Session = Depends(get_media_db),
):
    exclude_names = [] if include_wishlist else [WISHLIST_LIST_NAME]
    return list_custom_lists(db, exclude_names=exclude_names)


@router.post("")
async def post_custom_list(
    list_name: str = Form(...),
    db: Session = Depends(get_media_db),
):
    try:
        return create_custom_list(db, list_name=list_name)
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/items")
async def post_custom_list_item(
    list_id: str = Form(...),
    title: str = Form(...),
    original_title: str | None = Form(None),
    first_release_year: int | None = Form(None),
    imdb_id: str | None = Form(None),
    tmdb_id: str | None = Form(None),
    tvdb_id: str | None = Form(None),
    season: str | None = Form(None),
    cover_image: UploadFile | None = File(None),
    db: Session = Depends(get_media_db),
):
    try:
        cover_bytes = await cover_image.read() if cover_image is not None else None
        cover_content_type = cover_image.content_type if cover_image is not None else None
        return add_item_to_custom_list(
            db=db,
            list_id=list_id,
            title=title,
            original_title=original_title,
            first_release_year=first_release_year,
            imdb_id=imdb_id,
            tmdb_id=tmdb_id,
            tvdb_id=tvdb_id,
            season=season,
            cover_bytes=cover_bytes,
            cover_content_type=cover_content_type,
        )
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
