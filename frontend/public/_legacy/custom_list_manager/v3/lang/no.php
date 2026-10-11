<?php
declare(strict_types=1);

/**
 * Norsk (no) - språktekster for custom_list_manager.
 *
 * Alt her ligger under toppnivånøkkelen "clm" (Custom List Manager-prefiks),
 * slik at nøklene aldri kolliderer med språktekster fra andre løsninger i
 * prosjektet, selv om språkfilene en dag skulle bli delt/sentralisert.
 */

return [
    'clm' => [
        'meta_title' => 'Egendefinerte lister',

        'header' => [
            'title' => 'Egendefinerte lister',
            'tag'   => 'Opprett liste &amp; legg til element',
        ],

        'tabs' => [
            'create' => 'Opprett liste',
            'add'    => 'Legg til element',
        ],

        'upload_errors' => [
            'size'        => 'Bildefilen er for stor.',
            'partial'     => 'Opplastingen ble avbrutt før bildet var ferdig sendt.',
            'no_file'     => 'Du må velge et bilde før du sender inn.',
            'no_tmp_dir'  => 'Serveren mangler midlertidig mappe for opplasting.',
            'cant_write'  => 'Serveren klarte ikke å skrive den opplastede filen.',
            'extension'   => 'Opplastingen ble stoppet av en serverutvidelse.',
            'unknown'     => 'Ukjent feil under bildeopplasting.',
        ],

        'messages' => [
            'list_name_required'   => 'Du må skrive inn et listenavn.',
            'api_unreachable'      => 'Kunne ikke kontakte API-et: %s',
            'list_created'         => 'Listen "%s" ble opprettet.',
            'api_unknown_error'    => 'Ukjent feil fra API-et.',
            'api_invalid_response' => 'API-et svarte med en ugyldig respons.',
            'list_required'        => 'Du må velge en liste.',
            'title_required'       => 'Du må skrive inn en tittel.',
            'item_added'           => 'Elementet ble lagt til i listen.',
            'lists_fetch_failed'   => 'Kunne ikke hente listene: %s',
            'lists_fetch_http_err' => 'API-et returnerte HTTP %d ved henting av lister.',
        ],

        'create_tab' => [
            'heading'              => 'Opprett ny liste',
            'lead'                 => 'Gi listen et navn. Dette er en egen liste – ikke ønskelisten.',
            'list_name_label'      => 'Listenavn',
            'list_name_placeholder' => 'F.eks. Julefilmer',
            'submit'               => 'Opprett liste',
        ],

        'add_tab' => [
            'heading'  => 'Legg til element i liste',
            'lead'     => 'Velg hvilken liste elementet skal legges til i, fyll ut informasjon og last opp et coverbilde. Bruk gjerne TMDB- eller TVDB-søket for å fylle ut feltene automatisk.',
            'empty_hint' => 'Det finnes ingen lister enda. Opprett en liste under fanen "Opprett liste" for å komme i gang.',

            'list_label'          => 'Liste',
            'list_placeholder'    => 'Velg liste…',

            'title_label'         => 'Tittel',
            'title_placeholder'   => 'F.eks. Dune',

            'btn_tmdb'            => '🔍 Hent fra TMDB',
            'btn_tvdb'            => '🔎 Hent fra TVDB',

            'original_title_label' => 'Original tittel',

            'year_label'          => 'Utgivelsesår',
            'year_placeholder'    => 'F.eks. 2021',

            'external_ids_legend' => 'Eksterne ID-er (valgfritt)',
            'imdb_label'          => 'IMDb ID',
            'tmdb_label'          => 'TMDB ID',
            'tvdb_label'          => 'TVDB ID',

            'season_label'        => 'Sesong (valgfritt)',
            'season_placeholder'  => 'F.eks. Sesong 3, 1-3 eller Alle sesonger',
            'season_hint'         => 'Rent personlig notat – uavhengig av TVDB/TMDB-data, som alltid gjelder hele serien/filmen.',

            'cover_label'         => 'Coverbilde (valgfritt)',
            'cover_hint'          => 'Støtter JPEG, PNG, WEBP, HEIC og HEIF. Du kan legge til et coverbilde senere hvis du vil.',

            'submit'              => 'Legg til i listen',

            'result_heading'      => 'Lagt til i listen',
            'result_list'         => 'Liste',
            'result_title'        => 'Tittel',
            'result_item_id'      => 'List item ID',
            'result_original_title' => 'Original tittel',
            'result_year'         => 'Utgivelsesår',
            'result_imdb'         => 'IMDb ID',
            'result_tmdb'         => 'TMDB ID',
            'result_tvdb'         => 'TVDB ID',
            'result_season'       => 'Sesong',
            'result_cover'        => 'Cover',
        ],

        'modal_tmdb' => [
            'title'              => '🔍 Søk etter filmer og TV-serier (TMDB)',
            'close_label'        => 'Lukk',
            'search_placeholder' => 'Søk etter filmer eller TV-serier...',
            'tip'                => '💡 Tips: Søk med årstall, f.eks. "Titanic 1997"',
        ],

        'modal_tvdb' => [
            'title'              => '🔎 Søk etter filmer og TV-serier (TVDB)',
            'close_label'        => 'Lukk',
            'type_series'        => 'TV-serier',
            'type_movies'        => 'Filmer',
            'search_placeholder' => 'Søk etter filmer eller TV-serier...',
            'tip'                => '💡 Minst 2 tegn. Bytt mellom TV-serier og filmer over.',
        ],

        // Tekster brukt fra JavaScript (script.js / tvdb-search.js).
        // Disse blir eksponert til nettleseren via window.CLM_I18N.
        'js' => [
            'common' => [
                'min_chars_hint'        => 'Skriv minst 2 tegn for å søke...',
                'searching'             => 'Søker...',
                'no_results'            => 'Ingen resultater funnet.',
                'found_results'         => 'Fant %d resultat(er)',
                'error_prefix'          => 'En feil oppstod: %s',
                'no_poster_small'       => 'Ingen bilde',
                'no_poster_large'       => 'Ingen plakat',
                'media_movie'           => 'Film',
                'media_tv'              => 'TV-serie',
                'unknown_year'          => 'Ukjent',
                'fetching_details'      => 'Henter detaljer...',
                'details_error_prefix'  => 'Kunne ikke hente detaljer: %s',
                'imdb_label'            => 'IMDB ID:',
                'imdb_not_available'    => 'Ikke tilgjengelig',
                'imdb_open_link'        => 'Åpne på IMDB',
                'no_overview'           => 'Ingen beskrivelse tilgjengelig',
                'apply_button'          => 'Bruk disse dataene i skjemaet',
                'applied_message'       => 'Data er overført til skjemaet.',
                'no_title_fallback'     => '(uten tittel – id %s)',
            ],
            'tmdb' => [
                'search_error_generic'  => 'Noe gikk galt med API-kallet',
                'details_error_generic' => 'Kunne ikke hente detaljer',
                'found_results_for_year' => ' for år %s',
                'id_label'              => 'TMDB ID:',
            ],
            'tvdb' => [
                'search_error_generic'  => 'Noe gikk galt med TVDB-APIet',
                'details_error_generic' => 'Kunne ikke hente detaljer fra TVDB',
                'status_prefix'         => 'TVDB svarte: %s',
                'unknown_status'        => 'ukjent status',
                'id_label'              => 'TVDB ID:',
            ],
        ],
    ],
];
