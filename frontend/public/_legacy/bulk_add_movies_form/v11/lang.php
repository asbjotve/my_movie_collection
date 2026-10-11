<?php
declare(strict_types=1);

/**
 * lang.php – sentral språk-konfigurasjon for bulk_add_movies_form.
 *
 * ============================================================
 *  VIL DU FLYTTE SPRÅKFILENE?
 *  Endre KUN konstanten BAMF_LANG_DIR nedenfor.
 *  Alle språkfiler (en.php, nb.php, ...) må ligge i den mappen,
 *  og hver fil returnerer et array under toppnivånøkkelen "bamf".
 * ============================================================
 */
const BAMF_LANG_DIR = __DIR__ . '/lang';

/** Standardspråk hvis ingenting annet er valgt/tilgjengelig. */
const BAMF_DEFAULT_LANG = 'en';

/** Hvilke språk som faktisk finnes/er støttet i grensesnittet. */
const BAMF_AVAILABLE_LANGS = ['en', 'nb'];

/**
 * Toppnivånøkkelen alle oversettelser ligger under i lang/*.php.
 * Egen prefiks for bulk_add_movies_form, slik at nøklene aldri kolliderer
 * med språktekster fra andre løsninger i prosjektet (f.eks. "clm" i
 * custom_list_manager), selv om språkfilene en dag skulle bli delt/sentralisert.
 */
const BAMF_LANG_ROOT = 'bamf';

/**
 * Finn hvilket språk som er aktivt for denne visningen.
 * Rekkefølge: ?lang=xx i URL -> lagret i session -> standardspråk.
 */
function bamf_current_lang(): string
{
    $requested = $_GET['lang'] ?? null;

    if (is_string($requested) && in_array($requested, BAMF_AVAILABLE_LANGS, true)) {
        $_SESSION['lang'] = $requested;
        return $requested;
    }

    $sessionLang = $_SESSION['lang'] ?? null;
    if (is_string($sessionLang) && in_array($sessionLang, BAMF_AVAILABLE_LANGS, true)) {
        return $sessionLang;
    }

    return BAMF_DEFAULT_LANG;
}

/**
 * Laster inn oversettelsesfilen for et gitt språk.
 * Faller tilbake til standardspråket dersom filen ikke finnes.
 */
function bamf_load_translations(string $lang): array
{
    $file = BAMF_LANG_DIR . '/' . basename($lang) . '.php';

    if (!is_file($file)) {
        $file = BAMF_LANG_DIR . '/' . BAMF_DEFAULT_LANG . '.php';
    }

    /** @var array<string, mixed> $translations */
    $translations = require $file;
    return $translations;
}

/**
 * Hent en oversatt tekst via punktum-separert nøkkel, f.eks:
 *   tr($t, 'btn.add')
 * Faller tilbake til selve nøkkelen dersom oversettelsen mangler,
 * slik at manglende nøkler er lett å oppdage i grensesnittet.
 */
function tr(array $t, string $key): string
{
    $value = $t[BAMF_LANG_ROOT] ?? [];

    foreach (explode('.', $key) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $key;
        }
    }

    return is_string($value) ? $value : $key;
}

/**
 * Flater ut den nøstede "bamf"-grenen til punktum-nøkler igjen, til bruk
 * for JS-siden (window/const I18N-broen), slik at fmt('btn.add') i
 * JavaScript fortsatt kan bruke korte, flate nøkler som objekt-egenskaper.
 */
function bamf_flatten_for_js(array $t): array
{
    $flatten = function (array $arr, string $prefix = '') use (&$flatten): array {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    };

    return $flatten($t[BAMF_LANG_ROOT] ?? []);
}
