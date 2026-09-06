from fastapi import APIRouter, Body, Depends, File, Form, HTTPException, UploadFile
from sqlalchemy.orm import Session

from app.api_key import require_api_key
from app.db import User
from app.media_db import get_media_db
from app.security import get_current_user
from app.services.add_data.custom_list_manager import (
    add_item_to_custom_list,
    create_custom_list,
    delete_list_item,
    get_list_items,
    list_custom_lists,
    reorder_list_items,
    update_list_item,
)
from app.services.add_data.list_item_shared import ListItemUploadError
from app.services.add_data.wishlist_movie_cover import WISHLIST_LIST_NAME

router = APIRouter(
    prefix="/lists",
    tags=["custom-lists"],
)


@router.get("", dependencies=[Depends(require_api_key)])
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
    current_user: User = Depends(get_current_user),
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
    current_user: User = Depends(get_current_user),
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


@router.get("/{list_id}/items", dependencies=[Depends(require_api_key)])
async def get_list_items_route(
    list_id: str,
    db: Session = Depends(get_media_db),
):
    try:
        return get_list_items(db, list_id=list_id)
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/{list_id}/items/reorder")
async def patch_reorder_list_items(
    list_id: str,
    list_item_ids: list[str] = Body(..., embed=True),
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        return reorder_list_items(db, list_id=list_id, list_item_ids=list_item_ids)
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/items/{list_item_id}")
async def patch_custom_list_item(
    list_item_id: str,
    title: str = Form(...),
    original_title: str | None = Form(None),
    first_release_year: int | None = Form(None),
    imdb_id: str | None = Form(None),
    tmdb_id: str | None = Form(None),
    tvdb_id: str | None = Form(None),
    season: str | None = Form(None),
    cover_image: UploadFile | None = File(None),
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        cover_bytes = await cover_image.read() if cover_image is not None else None
        cover_content_type = cover_image.content_type if cover_image is not None else None
        return update_list_item(
            db=db,
            list_item_id=list_item_id,
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


@router.delete("/{list_id}/items/{list_item_id}")
async def delete_custom_list_item(
    list_id: str,
    list_item_id: str,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        return delete_list_item(db, list_id=list_id, list_item_id=list_item_id)
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
