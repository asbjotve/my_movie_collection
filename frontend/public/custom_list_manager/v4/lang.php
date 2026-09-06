<?php
declare(strict_types=1);

/**
 * lang.php – sentral språk-konfigurasjon for custom_list_manager (v3).
 *
 * ============================================================
 *  VIL DU FLYTTE SPRÅKFILENE?
 *  Endre KUN konstanten CLM_LANG_DIR nedenfor.
 *  Alle språkfiler (no.php, en.php, ...) må ligge i den mappen,
 *  og hver fil returnerer et array under toppnivånøkkelen "clm".
 * ============================================================
 */
const CLM_LANG_DIR = __DIR__ . '/lang';

/** Standardspråk hvis ingenting annet er valgt/tilgjengelig. */
const CLM_DEFAULT_LANG = 'no';

/** Hvilke språk som faktisk finnes/er støttet i grensesnittet. */
const CLM_AVAILABLE_LANGS = ['no', 'en'];

/**
 * Finn hvilket språk som er aktivt for denne visningen.
 * Rekkefølge: ?lang=xx i URL -> lagret cookie -> standardspråk.
 */
function clm_current_lang(): string
{
    $requested = $_GET['lang'] ?? null;

    if (is_string($requested) && in_array($requested, CLM_AVAILABLE_LANGS, true)) {
        setcookie('clm_lang', $requested, time() + 60 * 60 * 24 * 365, '/');
        return $requested;
    }

    $cookieLang = $_COOKIE['clm_lang'] ?? null;
    if (is_string($cookieLang) && in_array($cookieLang, CLM_AVAILABLE_LANGS, true)) {
        return $cookieLang;
    }

    return CLM_DEFAULT_LANG;
}

/**
 * Laster inn oversettelsesfilen for et gitt språk.
 * Faller tilbake til standardspråket dersom filen ikke finnes.
 */
function clm_load_translations(string $lang): array
{
    $file = CLM_LANG_DIR . '/' . basename($lang) . '.php';

    if (!is_file($file)) {
        $file = CLM_LANG_DIR . '/' . CLM_DEFAULT_LANG . '.php';
    }

    /** @var array<string, mixed> $translations */
    $translations = require $file;
    return $translations;
}

$GLOBALS['__clm_lang'] = clm_current_lang();
$GLOBALS['__clm_translations'] = clm_load_translations($GLOBALS['__clm_lang']);

/**
 * Hent en oversatt tekst via punktum-separert nøkkel, f.eks:
 *   t('clm.add_tab.title_label')
 *
 * Ekstra argumenter brukes som sprintf-parametere, f.eks:
 *   t('clm.messages.list_created', $listName)
 */
function t(string $key, mixed ...$args): string
{
    $parts = explode('.', $key);
    $value = $GLOBALS['__clm_translations'];

    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $key; // Manglende nøkkel -> vis selve nøkkelen, lett å oppdage i UI.
        }
    }

    if (!is_string($value)) {
        return $key;
    }

    return $args === [] ? $value : vsprintf($value, $args);
}

/**
 * Henter en hel gren av oversettelser som array (brukes til å eksponere
 * JS-tekster via window.CLM_I18N).
 */
function clm_translations_branch(string $key): array
{
    $parts = explode('.', $key);
    $value = $GLOBALS['__clm_translations'];

    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return [];
        }
    }

    return is_array($value) ? $value : [];
}
