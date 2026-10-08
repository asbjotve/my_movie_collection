from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.api_key import require_api_key
from app.db import AppSetting, User, get_db
from app.media_db import get_media_db
from app.schemas.media_catalog import (
    BulkGroupAssignRequest,
    ContentFieldLockRequest,
    ContentFieldUpdateRequest,
    EpisodeCreateRequest,
    EpisodeDiscAssignRequest,
    EpisodeUpdateRequest,
    GroupMembershipAddRequest,
    GroupReorderRequest,
    PhysicalCopyFieldUpdateRequest,
    SeasonCreateRequest,
    SeasonUpdateRequest,
    SeasonWatchedRequest,
    SetContentExternalIdRequest,
)
from app.security import get_current_user
from app.services.external_apis import ExternalApiError, search_tmdb, search_tvdb
from app.services.media_catalog import (
    ContentExternalSourceConflict,
    ContentExternalSourceError,
    add_content_to_group,
    assign_episode_disc,
    backfill_tmdb_cover_images,
    bulk_assign_group,
    create_episode,
    create_season,
    delete_episode,
    delete_season,
    get_bulk_refresh_tmdb_status,
    start_bulk_refresh_tmdb_job,
    get_content_by_id,
    get_collection_stats,
    get_data_health_issues,
    get_similar_content,
    list_content,
    list_content_covers,
    list_discs_for_content,
    list_group_names,
    list_seasons,
    merge_content_from_source,
    refresh_season_from_source,
    remove_content_from_group,
    reorder_group,
    set_content_cover_image,
    set_content_external_id,
    set_content_field_lock,
    update_content_external_source,
    update_content_fields,
    update_episode,
    update_physical_copy_fields,
    set_season_watched,
    update_season,
)


class SetCoverImagePayload(BaseModel):
    file_path: str


router = APIRouter(
    prefix="/media",
    tags=["media-catalog"],
)


@router.get("/content", dependencies=[Depends(require_api_key)])
def get_content(db: Session = Depends(get_media_db)):
    """Alle content-rader (media-katalogen), med fysiske utgaver og
    eksterne kilder gruppert inn per rad.

    Brukes foreløpig av website_template_example v18. Holdes enkel i
    starten - ingen paginering/filtrering ennå, det kommer etter hvert
    som mer av frontend flyttes over til dette API-et.
    """
    return list_content(db)


@router.get("/groups", dependencies=[Depends(require_api_key)])
def get_groups(db: Session = Depends(get_media_db)):
    """Alle filmgrupper (movie_group), for autofullføring i
    redigerings-popupen for "group"-feltet på detaljsiden - se
    list_group_names().
    """
    return list_group_names(db)


@router.get("/stats", dependencies=[Depends(require_api_key)])
def get_stats(db: Session = Depends(get_media_db)):
    """Aggregerte statistikk-tall for hele samlingen (antall filmer
    totalt, fordeling per tiår/sjanger/format, mest-fylte filmgrupper)
    - brukes av statistikksiden. Rent lesende, samme
    require_api_key-beskyttelse som resten av /media-endepunktene uten
    innlogging. Se get_collection_stats() for detaljer.
    """
    return get_collection_stats(db)


@router.get("/health-check", dependencies=[Depends(require_api_key)])
def get_health_check(db: Session = Depends(get_media_db)):
    """"Health check"-oversikt: content-rader som mangler cover,
    overview, runtime eller en gyldig TMDB/TVDB-kilde - brukes av
    health_check.php. Rent lesende. Se get_data_health_issues() for
    detaljer om hvilke felt som sjekkes og hvorfor.
    """
    return get_data_health_issues(db)


@router.get("/content/{content_id}", dependencies=[Depends(require_api_key)])
def get_content_detail(
    content_id: str,
    db: Session = Depends(get_media_db),
    userdb: Session = Depends(get_db),
):
    """Én content-rad (detaljvisning), med fysiske utgaver og eksterne
    kilder. content_id er 32-tegns hex (samme form som feltet i
    /media/content sin respons).
    """
    # default_currency-settingen ligger i mmc_userdb (app_settings),
    # en annen database enn selve media-katalogen (db_mediearkiv) - se
    # docstring i app_settings_route.py for hele fallback-rekkefølgen.
    setting = userdb.query(AppSetting).filter(AppSetting.setting_key == "default_currency").first()
    default_currency = (setting.setting_value if setting else None) or "NOK"

    item = get_content_by_id(db, content_id, default_currency=default_currency)
    if item is None:
        raise HTTPException(status_code=404, detail="Fant ikke content med denne IDen")
    return item


@router.get("/content/{content_id}/similar", dependencies=[Depends(require_api_key)])
def get_content_similar(content_id: str, db: Session = Depends(get_media_db)):
    """"Lignende filmer"-forslag til detaljsiden - se
    get_similar_content() i media_catalog.py for hvordan de velges
    ut (delt sjanger, filmer i samme gruppe ekskludert). Returnerer
    bare en liste (ingen 404) - en tom liste betyr enten at filmen
    ikke har noen sjangre lagret ennå, eller at ingen andre filmer
    deler en sjanger med den.
    """
    return get_similar_content(db, content_id)


@router.patch("/content/{content_id}")
def patch_content_fields(
    content_id: str,
    payload: ContentFieldUpdateRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Manuell redigering av ett eller flere content-felter (penne-
    ikon på detaljsiden), f.eks. title/overview/runtime. Krever
    innlogging (get_current_user).

    Kun feltene som faktisk er med i payload (exclude_unset) blir
    oppdatert - se ContentFieldUpdateRequest og
    update_content_fields() for detaljer, inkludert hvordan
    content.locked_fields håndteres.
    """
    fields = payload.model_dump(exclude_unset=True)
    try:
        return update_content_fields(db, content_id, fields)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/content/bulk-group")
def post_bulk_assign_group(
    payload: BulkGroupAssignRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Tildeler flere valgte filmer samme filmgruppe i ett kall - brukt
    av "velg-modus" i Mine filmer (index.php). Krever innlogging
    (get_current_user), samme som den vanlige content-redigeringen. Se
    bulk_assign_group() for detaljer.
    """
    try:
        return bulk_assign_group(db, payload.content_ids, payload.group)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/content/{content_id}/groups")
def post_add_content_to_group(
    content_id: str,
    payload: GroupMembershipAddRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Legger én film til i én filmgruppe til (fritekst navn, get-or-
    create) - brukt av "+ Legg til i gruppe"-knappen på detaljsiden.
    En film kan tilhøre flere grupper samtidig; kalles denne med en
    gruppe filmen allerede er medlem av, gjøres ingenting (idempotent).
    Krever innlogging. Se add_content_to_group() for detaljer.
    """
    try:
        return add_content_to_group(db, content_id, payload.group)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.delete("/content/{content_id}/groups/{group_id}")
def delete_content_from_group(
    content_id: str,
    group_id: int,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Fjerner én film fra én filmgruppe - brukt av "×"-knappen ved
    siden av hver gruppe på detaljsiden. Filmen beholder alle sine
    ANDRE gruppetilhørigheter uendret. Krever innlogging. Se
    remove_content_from_group() for detaljer.
    """
    try:
        return remove_content_from_group(db, content_id, group_id)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/groups/{group_id}/reorder")
def patch_reorder_group(
    group_id: int,
    payload: GroupReorderRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Setter manuell rekkefølge (group_sort_order) på filmene i en
    filmgruppe - brukt av dra-og-slipp-sortering av filmgruppe-listen
    på detaljsiden. Krever innlogging (get_current_user). Se
    reorder_group() for detaljer.
    """
    try:
        return reorder_group(db, group_id, payload.content_ids)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/content/{content_id}/lock")
def patch_content_field_lock(
    content_id: str,
    payload: ContentFieldLockRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Låser eller åpner ett enkelt content-felt manuelt (hengelås-ikon
    på detaljsiden), UTEN å endre selve verdien. Krever innlogging
    (get_current_user). Se set_content_field_lock() for detaljer.
    """
    try:
        return set_content_field_lock(db, content_id, payload.field, payload.locked)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/physical-copy/{collection_id}/{copy_id}")
def patch_physical_copy_fields(
    collection_id: str,
    copy_id: int,
    payload: PhysicalCopyFieldUpdateRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Manuell redigering av eier-/kjøpsinformasjon på ett fysisk
    eksemplar (penne-ikon på "Samlingsopplysninger"/"Kjøpsinformasjon"),
    f.eks. owner/store/purchased_at/price/currency. Krever innlogging
    (get_current_user).

    Kun feltene som faktisk er med i payload (exclude_unset) blir
    oppdatert - se PhysicalCopyFieldUpdateRequest og
    update_physical_copy_fields() for detaljer, inkludert get-or-create
    for owner/store.
    """
    fields = payload.model_dump(exclude_unset=True)
    try:
        return update_physical_copy_fields(db, collection_id, copy_id, fields)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.get("/external-search/{source}")
def external_search(
    source: str,
    query: str,
    content_type: str = "movie",
    current_user: User = Depends(get_current_user),
):
    """Søk i TMDB/TVDB (brukes av søkemodalen for å SETTE en manglende
    TMDB/TVDB-id på detail.php - se set_content_external_id() under).
    Rent søk - lagrer ingenting, i motsetning til selve
    tildelingsendepunktet."""
    if source not in ("tmdb", "tvdb"):
        raise HTTPException(status_code=400, detail="source må være 'tmdb' eller 'tvdb'.")
    query = query.strip()
    if not query:
        raise HTTPException(status_code=400, detail="query kan ikke være tom.")
    if content_type not in ("movie", "tv"):
        raise HTTPException(status_code=400, detail="content_type må være 'movie' eller 'tv'.")

    try:
        if source == "tmdb":
            return {"results": search_tmdb(query, content_type)}
        return {"results": search_tvdb(query, content_type)}
    except ExternalApiError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/content/{content_id}/external-source/{source}")
def assign_content_external_id(
    content_id: str,
    source: str,
    payload: SetContentExternalIdRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Setter en TMDB/TVDB-id på en content-rad som mangler en (eller
    erstatter en feil id) - i motsetning til PATCH
    /external-source/{source}/{external_id} under, trengs ingen
    eksisterende kobling fra før, se set_content_external_id().

    Hvis IDen allerede er registrert på en annen content-rad (duplikat),
    returneres 409 med info om hvilken film den tilhører - kall på nytt
    med confirm_merge=true for å slå radene sammen i stedet for å
    opprette en duplikatkobling."""
    try:
        return set_content_external_id(
            db,
            content_id=content_id,
            source=source,
            external_id=payload.external_id,
            confirm_merge=payload.confirm_merge,
            content_type=payload.content_type,
        )
    except ContentExternalSourceConflict as e:
        raise HTTPException(
            status_code=e.status_code,
            detail={
                "message": str(e),
                "conflict_content_id": e.conflict_content_id,
                "conflict_title": e.conflict_title,
                "physically_owned": e.physically_owned,
                "digitally_owned": e.digitally_owned,
            },
        )
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/external-source/{source}/{external_id}")
def patch_content_external_source(
    source: str,
    external_id: str,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Oppdaterer data_json for en eksisterende content_external_source-rad,
    identifisert kun via source ('tmdb' eller 'tvdb') og external_id -
    ingen content_id og ingen body trengs.

    Henter selv de fulle detaljene fra TMDB/TVDB (server-side) og
    lagrer dem som data_json - ingen payload sendes fra klienten, siden
    en full TMDB/TVDB-respons kan bli for stor til å sende via request
    body.

    Raden må finnes fra før - hvis ikke, returneres 404. Dette
    endepunktet oppretter bevisst ikke nye rader ennå (se
    update_content_external_source() i media_catalog.py).
    """
    try:
        return update_content_external_source(
            db,
            source=source,
            external_id=external_id,
        )
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/external-source/{source}/{external_id}/merge")
def merge_external_source(
    source: str,
    external_id: str,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Fletter sist lagrede data_json for (source, external_id) inn i
    tilhørende content-rad (title, overview, runtime osv.), med mindre
    feltet står i content.locked_fields.

    Leser IKKE på nytt fra TMDB/TVDB - bruk PATCH
    /external-source/{source}/{external_id} for å hente ferske data
    først. Oppdaterer alltid last_merged_source/last_merged_at ved
    vellykket kall, selv om ingen felt faktisk ble endret (f.eks. hvis
    alt er låst), slik at det er synlig hvilken kilde/tidspunkt som sist
    ble forsøkt flettet inn.
    """
    try:
        return merge_content_from_source(db, source=source, external_id=external_id)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/backfill/tmdb-covers")
def backfill_tmdb_covers(
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Henter cover_image (posterbilde) fra TMDB for alle content-rader
    som har en source='tmdb'-kobling i content_external_source, men som
    ennå mangler cover_image (NULL) i content-tabellen.

    Rader som allerede har cover_image, eller der 'cover_image' står i
    content.locked_fields, hoppes over uten TMDB-kall. Overholder TMDB
    sin rate-grense (strupet til 35 forespørsler/sekund).

    Dette er en engangs-/etterutfyllingsjobb, ikke en del av den vanlige
    "hent/flett fra kilde"-flyten per film - kjøres manuelt ved behov
    (f.eks. etter en stor bulk-import).
    """
    return backfill_tmdb_cover_images(db)


@router.post("/health-check/bulk-refresh-tmdb")
def bulk_refresh_tmdb(
    current_user: User = Depends(get_current_user),
):
    """Starter "hent fra TMDB" + "flett inn i content" for alle
    content-rader som p.t. er flagget av health-check-et med et
    TMDB-relevant problem (mangler cover/overview/runtime/imdb_id) og
    har en TMDB-kobling - en bulk-knapp-variant av de to eksisterende
    enkelt-handlingene, brukt fra "Oppdater flaggede fra TMDB"-knappen
    på health_check.php.

    Kjøres i en bakgrunnstråd og returnerer med en gang - selve jobben
    kan ta 20-30+ minutter (TMDBs reelle svartid per kall dominerer,
    ikke selve rate-limiten), altfor lenge for at nginx/PHP skal vente
    på ett synkront svar (bekreftet i praksis: forespørselen timet ut
    med 504 lenge før jobben faktisk var ferdig). Frontend poller
    GET .../bulk-refresh-tmdb/status i stedet. Se
    start_bulk_refresh_tmdb_job() for detaljer.
    """
    return start_bulk_refresh_tmdb_job()


@router.get("/health-check/bulk-refresh-tmdb/status")
def bulk_refresh_tmdb_status(
    current_user: User = Depends(get_current_user),
):
    """Fremdrift for den (eventuelt) kjørende bulk-TMDB-refresh-jobben -
    se start_bulk_refresh_tmdb_job()/get_bulk_refresh_tmdb_status()."""
    return get_bulk_refresh_tmdb_status()



@router.get("/content/{content_id}/covers", dependencies=[Depends(require_api_key)])
def get_content_covers(content_id: str, db: Session = Depends(get_media_db)):
    """Lister alle TMDB-postere som er tilgjengelige for en content-rad
    (hentet fra sist lagrede data_json, ingen nye TMDB-kall gjøres),
    slik at man kan velge et annet cover enn det som er satt automatisk.
    """
    result = list_content_covers(db, content_id)
    if result is None:
        raise HTTPException(status_code=404, detail="Fant ikke content med denne IDen")
    return result


@router.post("/content/{content_id}/cover")
def set_content_cover(
    content_id: str,
    payload: SetCoverImagePayload,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Setter cover_image for en content-rad til et av posterbildene fra
    GET /content/{content_id}/covers (identifisert via TMDB sin
    file_path). Låser samtidig 'cover_image' i content.locked_fields,
    slik at senere merge/backfill fra TMDB ikke overskriver valget.
    """
    try:
        return set_content_cover_image(db, content_id, payload.file_path)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.get("/content/{content_id}/seasons", dependencies=[Depends(require_api_key)])
def get_content_seasons(content_id: str, db: Session = Depends(get_media_db)):
    """Alle sesonger+episoder for en serie (sesong-fanen på
    detaljsiden) - se list_seasons() for detaljer. Returnerer en tom
    liste (ingen 404) hvis serien ikke har noen sesonger registrert
    ennå."""
    return list_seasons(db, content_id)


@router.get("/content/{content_id}/discs", dependencies=[Depends(require_api_key)])
def get_content_discs(content_id: str, db: Session = Depends(get_media_db)):
    """Diskene til en series fysiske eksemplarer - brukt som
    nedtrekksliste når en episode skal kobles til en disk, se
    list_discs_for_content()."""
    return list_discs_for_content(db, content_id)


@router.post("/content/{content_id}/seasons")
def post_create_season(
    content_id: str,
    payload: SeasonCreateRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Oppretter en ny sesong manuelt. Krever innlogging. Se
    create_season()."""
    try:
        return create_season(db, content_id, payload.model_dump())
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/seasons/{season_id}")
def patch_season(
    season_id: str,
    payload: SeasonUpdateRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Redigerer en sesong (kun feltene som er med i payload). Krever
    innlogging. Se update_season()."""
    fields = payload.model_dump(exclude_unset=True)
    try:
        return update_season(db, season_id, fields)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.delete("/seasons/{season_id}")
def delete_season_route(
    season_id: str,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Sletter en sesong og alle dens episoder (ON DELETE CASCADE).
    Krever innlogging. Se delete_season()."""
    try:
        return delete_season(db, season_id)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/seasons/{season_id}/watched")
def patch_season_watched(
    season_id: str,
    payload: SeasonWatchedRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Merker samtlige episoder i en sesong som sett/usett under ett.
    Krever innlogging. Se set_season_watched()."""
    try:
        return set_season_watched(db, season_id, payload.watched_flag)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/seasons/{season_id}/refresh/{source}")
def post_refresh_season(
    season_id: str,
    source: str,
    season_type: str = "default",
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Henter hele sesongens episodeliste fra TMDB/TVDB og oppdaterer/
    oppretter episoder deretter. Krever innlogging. season_type gjelder
    kun source='tvdb' (f.eks. 'dvd' for DVD-rekkefølge i stedet for
    sendedato-rekkefølge). Se refresh_season_from_source()."""
    try:
        return refresh_season_from_source(db, season_id, source, season_type=season_type)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/seasons/{season_id}/episodes")
def post_create_episode(
    season_id: str,
    payload: EpisodeCreateRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Legger til én episode manuelt i en eksisterende sesong. Krever
    innlogging. Se create_episode()."""
    try:
        return create_episode(db, season_id, payload.model_dump())
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.patch("/episodes/{episode_id}")
def patch_episode(
    episode_id: str,
    payload: EpisodeUpdateRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Redigerer en episode (kun feltene som er med i payload,
    inkludert watched_flag). Krever innlogging. Se update_episode()."""
    fields = payload.model_dump(exclude_unset=True)
    try:
        return update_episode(db, episode_id, fields)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.delete("/episodes/{episode_id}")
def delete_episode_route(
    episode_id: str,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Sletter en episode. Krever innlogging. Se delete_episode()."""
    try:
        return delete_episode(db, episode_id)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.put("/episodes/{episode_id}/disc")
def put_episode_disc(
    episode_id: str,
    payload: EpisodeDiscAssignRequest,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Kobler (eller fjerner koblingen for, med disc_id=null) hvilken
    fysisk disk episoden ligger på. Krever innlogging. Se
    assign_episode_disc()."""
    try:
        return assign_episode_disc(db, episode_id, payload.disc_id)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
