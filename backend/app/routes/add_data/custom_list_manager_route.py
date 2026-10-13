from fastapi import APIRouter, Body, Depends, File, Form, HTTPException, Query, UploadFile
from sqlalchemy.orm import Session

from app.api_key import require_api_key
from app.db import User, get_db
from app.media_db import get_media_db
from app.security import get_current_user, get_user_by_username
from app.services.add_data.custom_list_manager import (
    add_content_to_custom_list,
    add_item_to_custom_list,
    create_custom_list,
    delete_list_item,
    get_list_item_cover_preview,
    get_list_items,
    list_custom_lists,
    reorder_list_items,
    set_list_item_group_label,
    set_list_visibility,
    update_list_item,
)
from app.services.add_data.list_item_shared import ListItemUploadError
from app.services.add_data.wishlist_movie_cover import WISHLIST_LIST_NAME
from app.services.external_apis import ExternalApiError

router = APIRouter(
    prefix="/lists",
    tags=["custom-lists"],
)


@router.get("", dependencies=[Depends(require_api_key)])
async def get_custom_lists(
    include_wishlist: bool = False,
    only_visible: bool = False,
    db: Session = Depends(get_media_db),
):
    exclude_names = [] if include_wishlist else [WISHLIST_LIST_NAME]
    return list_custom_lists(db, exclude_names=exclude_names, only_visible=only_visible)


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
    group_label: str | None = Form(None),
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
            group_label=group_label,
            cover_bytes=cover_bytes,
            cover_content_type=cover_content_type,
        )
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/items/from-content")
async def post_custom_list_item_from_content(
    list_id: str = Form(...),
    content_id: str = Form(...),
    group_label: str | None = Form(None),
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Adds a movie/series already in the main collection to a custom
    list directly by content_id, without the TMDB search modal - see
    add_content_to_custom_list()."""
    try:
        return add_content_to_custom_list(
            db=db,
            list_id=list_id,
            content_id=content_id,
            group_label=group_label,
        )
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.get("/{list_id}/items", dependencies=[Depends(require_api_key)])
async def get_list_items_route(
    list_id: str,
    db: Session = Depends(get_media_db),
    userdb: Session = Depends(get_db),
    username: str | None = Query(
        default=None,
        description=(
            "Innlogget brukers brukernavn (sendt av api.php server-side, "
            "samme mønster som username-parameteren på GET /media/content "
            "- se current_username() i _shared/auth.php), brukt til å "
            "slå opp denne brukerens watched_flag for elementer som "
            "matcher en content-rad. Utelates/ignoreres for anonyme "
            "besøkende, som alltid får watched_flag=False."
        ),
    ),
):
    current_user = get_user_by_username(userdb, username) if username else None
    try:
        return get_list_items(
            db, list_id=list_id, current_user_id=current_user.id if current_user else None
        )
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.get("/items/{list_item_id}/cover-preview", dependencies=[Depends(require_api_key)])
async def get_list_item_cover_preview_route(
    list_item_id: str,
    db: Session = Depends(get_media_db),
):
    """Returns a cover image for one list item - its own stored
    cover_image, or (if it doesn't have one) a temporary poster fetched
    live from TMDB - see get_list_item_cover_preview()."""
    try:
        return get_list_item_cover_preview(db, list_item_id=list_item_id)
    except ListItemUploadError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
    except ExternalApiError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/{list_id}/visibility")
async def patch_list_visibility(
    list_id: str,
    show_on_frontend: bool = Body(..., embed=True),
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        return set_list_visibility(db, list_id=list_id, show_on_frontend=show_on_frontend)
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


@router.patch("/{list_id}/items/{list_item_id}/group-label")
async def patch_list_item_group_label(
    list_id: str,
    list_item_id: str,
    group_label: str | None = Body(None, embed=True),
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        return set_list_item_group_label(
            db, list_id=list_id, list_item_id=list_item_id, group_label=group_label
        )
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
