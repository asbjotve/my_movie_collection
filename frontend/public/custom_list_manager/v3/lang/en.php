<?php
declare(strict_types=1);

/**
 * English (en) translations for custom_list_manager.
 *
 * Everything lives under the top-level "clm" key (Custom List Manager
 * prefix), so these keys never collide with translations from other
 * solutions in the project, even if language files become shared/central
 * across the whole app in the future.
 */

return [
    'clm' => [
        'meta_title' => 'Custom lists',

        'header' => [
            'title' => 'Custom lists',
            'tag'   => 'Create list &amp; add item',
        ],

        'tabs' => [
            'create' => 'Create list',
            'add'    => 'Add item',
        ],

        'upload_errors' => [
            'size'        => 'The image file is too large.',
            'partial'     => 'The upload was interrupted before the image was fully sent.',
            'no_file'     => 'You must choose an image before submitting.',
            'no_tmp_dir'  => 'The server is missing a temporary folder for uploads.',
            'cant_write'  => 'The server could not write the uploaded file.',
            'extension'   => 'The upload was stopped by a server extension.',
            'unknown'     => 'Unknown error during image upload.',
        ],

        'messages' => [
            'list_name_required'   => 'You must enter a list name.',
            'api_unreachable'      => 'Could not reach the API: %s',
            'list_created'         => 'The list "%s" was created.',
            'api_unknown_error'    => 'Unknown error from the API.',
            'api_invalid_response' => 'The API returned an invalid response.',
            'list_required'        => 'You must select a list.',
            'title_required'       => 'You must enter a title.',
            'item_added'           => 'The item was added to the list.',
            'lists_fetch_failed'   => 'Could not fetch the lists: %s',
            'lists_fetch_http_err' => 'The API returned HTTP %d while fetching lists.',
        ],

        'create_tab' => [
            'heading'              => 'Create a new list',
            'lead'                 => 'Give the list a name. This is a separate list – not the wishlist.',
            'list_name_label'      => 'List name',
            'list_name_placeholder' => 'e.g. Christmas movies',
            'submit'               => 'Create list',
        ],

        'add_tab' => [
            'heading'  => 'Add item to list',
            'lead'     => 'Choose which list the item should be added to, fill in the information and upload a cover image. Feel free to use the TMDB or TVDB search to auto-fill the fields.',
            'empty_hint' => 'No lists exist yet. Create a list under the "Create list" tab to get started.',

            'list_label'          => 'List',
            'list_placeholder'    => 'Select list…',

            'title_label'         => 'Title',
            'title_placeholder'   => 'e.g. Dune',

            'btn_tmdb'            => '🔍 Fetch from TMDB',
            'btn_tvdb'            => '🔎 Fetch from TVDB',

            'original_title_label' => 'Original title',

            'year_label'          => 'Release year',
            'year_placeholder'    => 'e.g. 2021',

            'external_ids_legend' => 'External IDs (optional)',
            'imdb_label'          => 'IMDb ID',
            'tmdb_label'          => 'TMDB ID',
            'tvdb_label'          => 'TVDB ID',

            'season_label'        => 'Season (optional)',
            'season_placeholder'  => 'e.g. Season 3, 1-3 or All seasons',
            'season_hint'         => 'A purely personal note – independent of TVDB/TMDB data, which always covers the whole show/movie.',

            'cover_label'         => 'Cover image (optional)',
            'cover_hint'          => 'Supports JPEG, PNG, WEBP, HEIC and HEIF. You can add a cover image later if you want.',

            'submit'              => 'Add to list',

            'result_heading'      => 'Added to the list',
            'result_list'         => 'List',
            'result_title'        => 'Title',
            'result_item_id'      => 'List item ID',
            'result_original_title' => 'Original title',
            'result_year'         => 'Release year',
            'result_imdb'         => 'IMDb ID',
            'result_tmdb'         => 'TMDB ID',
            'result_tvdb'         => 'TVDB ID',
            'result_season'       => 'Season',
            'result_cover'        => 'Cover',
        ],

        'modal_tmdb' => [
            'title'              => '🔍 Search movies and TV shows (TMDB)',
            'close_label'        => 'Close',
            'search_placeholder' => 'Search movies or TV shows...',
            'tip'                => '💡 Tip: Search with a year, e.g. "Titanic 1997"',
        ],

        'modal_tvdb' => [
            'title'              => '🔎 Search movies and TV shows (TVDB)',
            'close_label'        => 'Close',
            'type_series'        => 'TV shows',
            'type_movies'        => 'Movies',
            'search_placeholder' => 'Search movies or TV shows...',
            'tip'                => '💡 At least 2 characters. Switch between TV shows and movies above.',
        ],

        // Texts used from JavaScript (script.js / tvdb-search.js).
        // Exposed to the browser via window.CLM_I18N.
        'js' => [
            'common' => [
                'min_chars_hint'        => 'Type at least 2 characters to search...',
                'searching'             => 'Searching...',
                'no_results'            => 'No results found.',
                'found_results'         => 'Found %d result(s)',
                'error_prefix'          => 'An error occurred: %s',
                'no_poster_small'       => 'No image',
                'no_poster_large'       => 'No poster',
                'media_movie'           => 'Movie',
                'media_tv'              => 'TV show',
                'unknown_year'          => 'Unknown',
                'fetching_details'      => 'Fetching details...',
                'details_error_prefix'  => 'Could not fetch details: %s',
                'imdb_label'            => 'IMDB ID:',
                'imdb_not_available'    => 'Not available',
                'imdb_open_link'        => 'Open on IMDB',
                'no_overview'           => 'No description available',
                'apply_button'          => 'Use this data in the form',
                'applied_message'       => 'Data has been transferred to the form.',
                'no_title_fallback'     => '(untitled – id %s)',
            ],
            'tmdb' => [
                'search_error_generic'  => 'Something went wrong with the API call',
                'details_error_generic' => 'Could not fetch details',
                'found_results_for_year' => ' for year %s',
                'id_label'              => 'TMDB ID:',
            ],
            'tvdb' => [
                'search_error_generic'  => 'Something went wrong with the TVDB API',
                'details_error_generic' => 'Could not fetch details from TVDB',
                'status_prefix'         => 'TVDB responded: %s',
                'unknown_status'        => 'unknown status',
                'id_label'              => 'TVDB ID:',
            ],
        ],
    ],
];
