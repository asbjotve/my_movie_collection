<?php
declare(strict_types=1);

/**
 * English (en) translations for website_template_example/v19.
 *
 * Keep this file's key structure IDENTICAL to lang/no.php - only the
 * string values should differ. See lang.php for how these keys are
 * resolved (t('wte.xxx.yyy')).
 */

return [
    'wte' => [
        'nav' => [
            'mine_filmer'    => 'My movies',
            'onskeliste'     => 'Wishlist',
            'andre_lister'   => 'Other lists',
            'administrering' => 'Administration',
            'logged_in_as'   => 'Logged in as',
            'logout'         => 'Log out',
            'not_logged_in'  => 'Not logged in',
            'login'          => 'Log in',
            'login_cta'      => '🔒 Log in',
        ],

        'locked_hint' => 'Requires login to access this section.',

        'index' => [
            'meta_title' => 'Media catalog – v19 (Bootstrap 5)',

            'mine_filmer' => [
                'title'               => 'My movies',
                'search_placeholder'  => 'Search title / original title…',
                'only_unwatched'      => 'Show unwatched only',
                'view_grid'           => '🖼️ Grid',
                'view_list'           => '📋 List',
                'loading'             => 'Loading data from the database…',
                'col_title'           => 'Title',
                'col_original_title'  => 'Original title',
                'col_year'            => 'Year',
                'col_imdb_id'         => 'IMDb ID',
                'chip_all'            => 'All',
                'watched'             => 'Watched',
                'unwatched'           => 'Unwatched',
                'fetch_error_prefix'  => 'Failed to fetch data: ',
            ],

            'onskeliste' => [
                'title'          => 'Wishlist',
                'placeholder'    => 'Placeholder data – no database connection in this version.',
                'added_at'       => 'Added: %s',
                'demo_items'     => ['Oppenheimer', 'Poor Things'],
            ],

            'andre_lister' => [
                'title'       => 'Other lists',
                'placeholder' => 'Placeholder data – no database connection in this version.',
                'item_count'  => '%d items',
                'demo_items'  => ['Christmas movies', 'Kids movies', 'To watch with friends'],
            ],

            'administrering' => [
                'title'           => 'Administration',
                'logged_in_hint'  => 'Logged in as %s. The actions below are still placeholders in this template (the actual features live elsewhere in the project for now), but the panel is now genuinely unlocked for you.',
                'locked_badge'    => 'Locked',
                'card_add_movie_title'   => 'Add movie',
                'card_add_movie_desc'    => 'Manually register new movies/shows in the catalog.',
                'card_edit_lists_title'  => 'Edit lists',
                'card_edit_lists_desc'   => 'Create, edit or delete custom lists.',
                'card_2fa_title'         => 'Two-factor authentication (2FA)',
                'card_2fa_desc'          => 'Set up or disable 2FA for your account.',
                'card_access_title'      => 'Access control',
                'card_access_desc'       => 'Choose which pages/sections require login.',
                'card_system_status_title' => 'System status',
                'card_system_status_desc'  => 'Simple overview of database/API connectivity.',
                'note' => '🔒 <strong>Note:</strong> "Administration" should almost certainly require login – this is where data can be changed/deleted, unlike "My movies"/"Wishlist" which likely only display data.',
            ],
        ],

        'detail' => [
            'meta_title'          => 'Media catalog – details (v19)',
            'meta_title_suffix'   => ' – Media catalog (v19)',
            'back_link'           => '&larr; Back to My movies',
            'loading'             => 'Loading…',
            'missing_id'          => 'Missing id in the URL (?id=...).',
            'fetch_error_prefix'  => 'Failed to fetch data: ',
            'choose_cover_btn'    => '🖼️ Change cover',
            'refresh_tmdb_btn'    => '🔄 TMDB',
            'refresh_tvdb_btn'    => '🔄 TVDB',
            'fact_imdb'           => 'IMDb',
            'fact_tmdb'           => 'TMDB',
            'fact_tvdb'           => 'TVDB',
            'fact_release'        => 'Released',
            'fact_runtime'        => 'Runtime',
            'fact_age'            => 'Age rating',
            'fact_type'           => 'Type',
            'fact_prod_company'   => 'Production company',
            'summary_heading'     => 'Summary',
            'no_overview'         => 'No summary registered.',
            'tab_cast'            => 'Cast',
            'tab_collection'      => 'Collection details',
            'tab_purchase'        => 'Purchase information',
            'cast_empty_note'     => 'No data registered yet. This requires a separate table for cast/crew (role, name, image) linked to the movie – does not exist in the database today.',
            'purchase_empty_note' => 'No purchase information registered yet. This requires separate fields/a table for e.g. price, purchase date and store – does not exist in the database today.',
            'untitled'            => '(untitled)',
            'original_title_prefix' => 'Original title: %s',
            'runtime_suffix'      => ' min',
            'last_merged_from'    => 'Data last fetched from %s (%s).',
            'not_merged_yet'      => 'Data has not been merged in from TMDB/TVDB yet.',
            'fetching_from'       => 'Fetching from %s…',
            'merging_from'        => 'Merging in from %s…',
            'source_updated'      => '%s updated (%s).',
            'now'                 => 'now',
            'merged_fields_prefix' => ' Merged in: %s.',
            'no_fields_changed'   => ' No fields were changed.',
            'locked_fields_skipped' => ' Locked (skipped): %s.',
            'refresh_error_prefix' => 'Failed to update from %s: %s',

            'not_in_collection'   => 'Not registered in the physical collection (no Blu-ray/DVD copy for this title).',
            'disc_count_plural'   => '%d discs',
            'disc_count_single'   => '1 disc',
            'disc_count_unknown'  => 'Unknown number of discs',
            'box_set_tag'         => 'Box set',
            'barcode_label'       => 'Barcode: %s',
            'box_barcode_label'   => 'Box barcode: %s',
            'show_box_contents'   => 'Show contents of the box &rarr;',
            'show_discs'          => 'Show discs &rarr;',
            'box_contents_heading' => 'Contents of the box',
            'discs_in_copy_heading' => 'Discs in this copy',
            'table_title'         => 'Title',
            'table_format'        => 'Format',
            'table_storage'       => 'Storage location',
            'table_disc'          => 'Disc',
            'disc_default_label'  => 'Disc',

            'format_bd'           => 'Blu-ray',
            'format_dvd'          => 'DVD',
            'format_uhd'          => '4K UHD',
            'format_unknown'      => 'Unknown format',
            'format_plex'         => 'Plex',
            'no_ownership'        => 'No registered copies/sources yet.',

            'choose_cover_title'  => 'Choose cover',
            'loading_posters'     => 'Loading posters…',
            'no_alt_posters'      => 'No alternative posters found from TMDB for this movie.',
            'posters_available'   => '%d posters available - click to choose.',
            'current_label'       => 'Current',
            'fetch_posters_error_prefix' => 'Failed to fetch posters: ',
            'setting_cover'       => 'Setting new cover…',
            'cover_updated'       => 'Cover updated.',
            'set_cover_error_prefix' => 'Failed to set cover: ',
        ],
    ],
];
