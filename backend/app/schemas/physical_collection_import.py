from typing import List, Literal, Optional, Union
from pydantic import BaseModel, Field


class BonusItemPayload(BaseModel):
    seq_no: Optional[int] = None
    title: str
    item_type: str
    runtime_seconds: Optional[int] = None
    notes: Optional[str] = None


class SingleDiscPayload(BaseModel):
    type_disc: str
    format: str
    label: Optional[str] = None
    storage_slot_no: Optional[int] = Field(default=None, ge=1)
    add_to_storage: bool = False
    bonus_items: List[BonusItemPayload] = Field(default_factory=list)


class SingleRowPayload(BaseModel):
    title: str
    format: str
    barcode: Optional[str] = Field(default=None, max_length=13)
    imdb_id: Optional[str] = None
    tmdb_id: Optional[str] = None
    discs: List[SingleDiscPayload] = Field(default_factory=list)


class SinglesImportPayload(BaseModel):
    kind: Literal["singles"]
    storage_id: Optional[str] = None
    default_copy_count: int = Field(ge=1)
    rows: List[SingleRowPayload] = Field(default_factory=list)


class BoxSetMoviePayload(BaseModel):
    order: int = Field(ge=1)
    title: str
    imdb_id: Optional[str] = None
    tmdb_id: Optional[str] = None
    inner_case_ean: Optional[str] = None
    treat_as_single: bool = False


class BoxSetDiscPayload(BaseModel):
    order: int = Field(ge=1)
    type_disc: str
    format: str
    label: Optional[str] = None
    storage_slot_no: Optional[int] = Field(default=None, ge=1)
    add_to_storage: bool = False
    related_index: Optional[int] = Field(default=None, ge=0)
    related_title: Optional[str] = None
    bonus_items: List[BonusItemPayload] = Field(default_factory=list)


class BoxSetPayload(BaseModel):
    box_set_index: int = Field(ge=1)
    storage_id: Optional[str] = None
    format: str
    box_set_barcode: Optional[str] = Field(default=None, max_length=13)
    copy_count: int = Field(ge=1)
    movies: List[BoxSetMoviePayload] = Field(default_factory=list)
    discs: List[BoxSetDiscPayload] = Field(default_factory=list)


class BoxSetsBulkImportPayload(BaseModel):
    kind: Literal["box_sets_bulk"]
    storage_id: Optional[str] = None
    box_sets: List[BoxSetPayload] = Field(default_factory=list)


PhysicalCollectionImportPayload = Union[
    SinglesImportPayload,
    BoxSetsBulkImportPayload,
]
