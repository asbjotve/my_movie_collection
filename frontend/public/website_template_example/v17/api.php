<?php
declare(strict_types=1);

/**
 * api.php – henter media-katalogen fra databasen (content + tilhørende
 * tabeller) og returnerer JSON i samme format som demo-dataene i v14,
 * slik at index.php (JS-siden) kan gjenbrukes nesten uendret.
 *
 * ============================================================
 *  VIL DU ENDRE HVILKE FELTER/DATA SOM HENTES?
 *  De tre spørringene nedenfor er bevisst holdt enkle og separate
 *  (én for content, én for utgaver/collections, én for kilder/sources)
 *  fremfor én stor JOIN - lettere å lese og redigere hver for seg.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    $pdo = get_media_db();

    // 1) Hovedtabellen: content
    $contentRows = $pdo->query('
        SELECT
            HEX(content_id)  AS content_id,
            title,
            original_title,
            first_release,
            runtime,
            age_restriction,
            watched_flag,
            temporary_flag,
            content_type
        FROM content
        ORDER BY title ASC
    ')->fetchAll();

    // 2) Utgaver (physical_collection) pr. content_id, via koblingstabellen
    $collectionRows = $pdo->query('
        SELECT
            HEX(cipc.content_id)   AS content_id,
            HEX(pc.collection_id)  AS collection_id,
            pc.format,
            pc.barcode,
            pc.box_set_barcode
        FROM content_in_physical_collection cipc
        JOIN physical_collection pc ON pc.collection_id = cipc.collection_id
    ')->fetchAll();

    // 3) Eksterne kilder (content_external_source) pr. content_id
    $sourceRows = $pdo->query('
        SELECT
            HEX(content_id) AS content_id,
            source,
            external_id,
            fetched_at
        FROM content_external_source
    ')->fetchAll();

    // Grupper collections og sources pr. content_id, slik at hvert
    // content-objekt får sine egne "collections" og "sources"-lister.
    $collectionsByContent = [];
    foreach ($collectionRows as $row) {
        $collectionsByContent[$row['content_id']][] = [
            'collection_id'   => $row['collection_id'],
            'format'          => $row['format'],
            'barcode'         => $row['barcode'],
            'box_set_barcode' => $row['box_set_barcode'],
        ];
    }

    $sourcesByContent = [];
    foreach ($sourceRows as $row) {
        $sourcesByContent[$row['content_id']][] = [
            'source'      => $row['source'],
            'external_id' => $row['external_id'],
            'fetched_at'  => $row['fetched_at'] !== null
                ? substr((string) $row['fetched_at'], 0, 10)
                : null,
        ];
    }

    $result = [];
    foreach ($contentRows as $row) {
        $id = $row['content_id'];
        $sources = $sourcesByContent[$id] ?? [];

        // Plukk ut IMDb-id spesifikt til listevisningen (tabellen viser
        // kun tittel/original tittel/årstall/imdb-id, ingen cover-art).
        $imdbId = null;
        foreach ($sources as $s) {
            if ($s['source'] === 'imdb') {
                $imdbId = $s['external_id'];
                break;
            }
        }

        $result[] = [
            'content_id'      => $id,
            'title'           => $row['title'],
            'original_title'  => $row['original_title'],
            'first_release'   => $row['first_release'] !== null
                ? substr((string) $row['first_release'], 0, 10)
                : null,
            'runtime'         => $row['runtime'] !== null ? (int) $row['runtime'] : null,
            'age_restriction' => $row['age_restriction'],
            'watched_flag'    => (int) $row['watched_flag'],
            'temporary_flag'  => (int) $row['temporary_flag'],
            'content_type'    => $row['content_type'],
            'imdb_id'         => $imdbId,
            'collections'     => $collectionsByContent[$id] ?? [],
            'sources'         => $sources,
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kunne ikke hente data fra databasen: ' . $e->getMessage()]);
}
