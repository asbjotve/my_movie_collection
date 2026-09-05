<?php
declare(strict_types=1);

/**
 * lang.php – central i18n configuration for website_template_example/v19.
 *
 * Same pattern as custom_list_manager/v3/lang.php (proven in this
 * project already): ?lang=xx in the URL -> saved cookie -> default
 * language. Kept as its own self-contained copy per tool/version
 * folder (not shared/centralized) so each page can add/change
 * translation keys independently without risk of colliding with
 * other tools' i18n data.
 *
 * ============================================================
 *  WANT TO MOVE THE LANGUAGE FILES?
 *  Change ONLY the WTE_LANG_DIR constant below.
 *  All language files (no.php, en.php, ...) must live in that
 *  directory, and each file must return an array under the
 *  top-level key "wte" (website_template_example prefix).
 * ============================================================
 *
 *  ADDING A NEW LANGUAGE LATER
 *  1. Add its code to WTE_AVAILABLE_LANGS below.
 *  2. Copy lang/en.php to lang/<code>.php and translate every value
 *     (keep every key identical - only translate the strings).
 *  3. Add a flag/label for it in the language-switcher markup in
 *     each page (search for "lang-switch" in index.php).
 */
const WTE_LANG_DIR = __DIR__ . '/lang';

/** Default language if nothing else is selected/available. */
const WTE_DEFAULT_LANG = 'no';

/** Which languages actually exist/are supported in the UI. */
const WTE_AVAILABLE_LANGS = ['no', 'en'];

/**
 * Determine which language is active for this request.
 * Order: ?lang=xx in URL -> saved cookie -> default language.
 */
function wte_current_lang(): string
{
    $requested = $_GET['lang'] ?? null;

    if (is_string($requested) && in_array($requested, WTE_AVAILABLE_LANGS, true)) {
        setcookie('wte_lang', $requested, time() + 60 * 60 * 24 * 365, '/');
        return $requested;
    }

    $cookieLang = $_COOKIE['wte_lang'] ?? null;
    if (is_string($cookieLang) && in_array($cookieLang, WTE_AVAILABLE_LANGS, true)) {
        return $cookieLang;
    }

    return WTE_DEFAULT_LANG;
}

/**
 * Loads the translation file for a given language.
 * Falls back to the default language if the file doesn't exist.
 */
function wte_load_translations(string $lang): array
{
    $file = WTE_LANG_DIR . '/' . basename($lang) . '.php';

    if (!is_file($file)) {
        $file = WTE_LANG_DIR . '/' . WTE_DEFAULT_LANG . '.php';
    }

    /** @var array<string, mixed> $translations */
    $translations = require $file;
    return $translations;
}

$GLOBALS['__wte_lang'] = wte_current_lang();
$GLOBALS['__wte_translations'] = wte_load_translations($GLOBALS['__wte_lang']);

/**
 * Fetch a translated string via a dot-separated key, e.g.:
 *   t('wte.nav.mine_filmer')
 *
 * Extra arguments are used as sprintf parameters, e.g.:
 *   t('wte.login.welcome', $username)
 */
function t(string $key, mixed ...$args): string
{
    $parts = explode('.', $key);
    $value = $GLOBALS['__wte_translations'];

    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $key; // Missing key -> show the key itself, easy to spot in the UI.
        }
    }

    if (!is_string($value)) {
        return $key;
    }

    return $args === [] ? $value : vsprintf($value, $args);
}

/**
 * Fetches an entire branch of translations as an array (used to expose
 * JS-side texts via window.WTE_I18N, same pattern as CLM_I18N in
 * custom_list_manager).
 */
function wte_translations_branch(string $key): array
{
    $parts = explode('.', $key);
    $value = $GLOBALS['__wte_translations'];

    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return [];
        }
    }

    return is_array($value) ? $value : [];
}
