<?php
declare(strict_types=1);

/**
 * Norsk (no) - språktekster for website_template_example/v19.
 *
 * Alt her ligger under toppnivånøkkelen "wte" (website_template_example-
 * prefiks), slik at nøklene aldri kolliderer med språktekster fra andre
 * verktøy i prosjektet.
 *
 * Denne filen er organisert i én gren per side (index, detail, login,
 * twofa, admin_tilganger) for å holde oversikten selv om filen vokser.
 */

return [
    'wte' => [
        'nav' => [
            'mine_filmer'    => 'Mine filmer',
            'onskeliste'     => 'Ønskeliste',
            'andre_lister'   => 'Andre lister',
            'administrering' => 'Administrering',
            'logged_in_as'   => 'Innlogget som',
            'logout'         => 'Logg ut',
            'not_logged_in'  => 'Ikke innlogget',
            'login'          => 'Logg inn',
            'login_cta'      => '🔒 Logg inn',
        ],

        'locked_hint' => 'Krever innlogging for å få tilgang til denne seksjonen.',

        'index' => [
            'meta_title' => 'Media-katalog – v19 (Bootstrap 5)',

            'mine_filmer' => [
                'title'               => 'Mine filmer',
                'search_placeholder'  => 'Søk tittel / original tittel…',
                'only_unwatched'      => 'Vis bare ikke-sett',
                'view_grid'           => '🖼️ Rutenett',
                'view_list'           => '📋 Liste',
                'loading'             => 'Laster data fra databasen…',
                'col_title'           => 'Tittel',
                'col_original_title'  => 'Original tittel',
                'col_year'            => 'Årstall',
                'col_imdb_id'         => 'IMDb-id',
                'chip_all'            => 'Alle',
                'watched'             => 'Sett',
                'unwatched'           => 'Ikke sett',
                'fetch_error_prefix'  => 'Klarte ikke å hente data: ',
            ],

            'onskeliste' => [
                'title'          => 'Ønskeliste',
                'placeholder'    => 'Plassholder-data – ingen databasekobling i denne versjonen.',
                'added_at'       => 'Lagt til: %s',
                'demo_items'     => ['Oppenheimer', 'Poor Things'],
            ],

            'andre_lister' => [
                'title'       => 'Andre lister',
                'placeholder' => 'Plassholder-data – ingen databasekobling i denne versjonen.',
                'item_count'  => '%d elementer',
                'demo_items'  => ['Julefilmer', 'Barnefilmer', 'Skal ses med kompiser'],
            ],

            'administrering' => [
                'title'           => 'Administrering',
                'logged_in_hint'  => 'Innlogget som %s. Handlingene under er fortsatt plassholdere i denne malen (selve funksjonene finnes andre steder i prosjektet ennå), men panelet er nå faktisk ulåst for deg.',
                'locked_badge'    => 'Låst',
                'card_add_movie_title'   => 'Legg til film',
                'card_add_movie_desc'    => 'Manuell registrering av nye filmer/serier i katalogen.',
                'card_edit_lists_title'  => 'Rediger lister',
                'card_edit_lists_desc'   => 'Opprett, endre eller slett egendefinerte lister.',
                'card_2fa_title'         => 'To-faktor autentisering (2FA)',
                'card_2fa_desc'          => 'Sett opp eller deaktiver 2FA for din bruker.',
                'card_access_title'      => 'Tilgangsstyring',
                'card_access_desc'       => 'Velg hvilke sider/seksjoner som krever innlogging.',
                'card_system_status_title' => 'Systemstatus',
                'card_system_status_desc'  => 'Enkel oversikt over database/API-tilkobling.',
                'note' => '🔒 <strong>Vurdering:</strong> "Administrering" bør nesten helt sikkert kreve innlogging – dette er stedet hvor data kan endres/slettes, i motsetning til "Mine filmer"/"Ønskeliste" som trolig bare viser data.',
            ],
        ],

        'detail' => [
            'meta_title'          => 'Media-katalog – detaljer (v19)',
            'meta_title_suffix'   => ' – Media-katalog (v19)',
            'back_link'           => '&larr; Tilbake til Mine filmer',
            'loading'             => 'Laster…',
            'missing_id'          => 'Mangler id i URL-en (?id=...).',
            'fetch_error_prefix'  => 'Klarte ikke å hente data: ',
            'choose_cover_btn'    => '🖼️ Bytt cover',
            'refresh_tmdb_btn'    => '🔄 TMDB',
            'refresh_tvdb_btn'    => '🔄 TVDB',
            'fact_imdb'           => 'IMDb',
            'fact_tmdb'           => 'TMDB',
            'fact_tvdb'           => 'TVDB',
            'fact_release'        => 'Utgitt',
            'fact_runtime'        => 'Spilletid',
            'fact_age'            => 'Aldersgrense',
            'fact_type'           => 'Type',
            'fact_prod_company'   => 'Produksjonsselskap',
            'summary_heading'     => 'Sammendrag',
            'no_overview'         => 'Ingen sammendrag registrert.',
            'tab_cast'            => 'Rollebesetning',
            'tab_collection'      => 'Samlingsopplysninger',
            'tab_purchase'        => 'Kjøpsinformasjon',
            'cast_empty_note'     => 'Ingen data registrert ennå. Dette krever en egen tabell for skuespillere/crew (rolle, navn, ev. bilde) koblet til filmen – finnes ikke i databasen i dag.',
            'purchase_empty_note' => 'Ingen kjøpsinformasjon registrert ennå. Dette krever egne felt/tabell for f.eks. pris, kjøpsdato og butikk – finnes ikke i databasen i dag.',
            'untitled'            => '(uten tittel)',
            'original_title_prefix' => 'Original tittel: %s',
            'runtime_suffix'      => ' min',
            'last_merged_from'    => 'Data sist hentet fra %s (%s).',
            'not_merged_yet'      => 'Data er ikke flettet inn fra TMDB/TVDB ennå.',
            'fetching_from'       => 'Henter fra %s…',
            'merging_from'        => 'Fletter inn fra %s…',
            'source_updated'      => '%s oppdatert (%s).',
            'now'                 => 'nå',
            'merged_fields_prefix' => ' Flettet inn: %s.',
            'no_fields_changed'   => ' Ingen felt ble endret.',
            'locked_fields_skipped' => ' Låst (hoppet over): %s.',
            'refresh_error_prefix' => 'Klarte ikke å oppdatere fra %s: %s',

            'not_in_collection'   => 'Ikke registrert i fysisk samling (ingen Blu-ray/DVD-eksemplar for denne tittelen).',
            'disc_count_plural'   => '%d plater',
            'disc_count_single'   => '1 plate',
            'disc_count_unknown'  => 'Ukjent antall plater',
            'box_set_tag'         => 'Box-sett',
            'barcode_label'       => 'Strekkode: %s',
            'box_barcode_label'   => 'Boks-strekkode: %s',
            'show_box_contents'   => 'Vis innhold i boksen &rarr;',
            'show_discs'          => 'Vis platene &rarr;',
            'box_contents_heading' => 'Innhold i boksen',
            'discs_in_copy_heading' => 'Plater i dette eksemplaret',
            'table_title'         => 'Tittel',
            'table_format'        => 'Format',
            'table_storage'       => 'Lagringsplass',
            'table_disc'          => 'Plate',
            'disc_default_label'  => 'Plate',

            'format_bd'           => 'Blu-ray',
            'format_dvd'          => 'DVD',
            'format_uhd'          => '4K UHD',
            'format_unknown'      => 'Ukjent format',
            'format_plex'         => 'Plex',
            'no_ownership'        => 'Ingen registrerte eksemplarer/kilder ennå.',

            'choose_cover_title'  => 'Velg cover',
            'loading_posters'     => 'Laster postere…',
            'no_alt_posters'      => 'Ingen alternative postere funnet fra TMDB for denne filmen.',
            'posters_available'   => '%d postere tilgjengelig - klikk for å velge.',
            'current_label'       => 'Gjeldende',
            'fetch_posters_error_prefix' => 'Klarte ikke å hente postere: ',
            'setting_cover'       => 'Setter nytt cover…',
            'cover_updated'       => 'Cover oppdatert.',
            'set_cover_error_prefix' => 'Klarte ikke å sette cover: ',
        ],
    ],
];
