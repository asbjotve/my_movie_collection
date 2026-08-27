<?php
declare(strict_types=1);

const BAMF_LANG_DIR = __DIR__ . '/lang';
const BAMF_DEFAULT_LANG = 'nb';
const BAMF_AVAILABLE_LANGS = ['en', 'nb'];
const BAMF_LANG_ROOT = 'bamf';

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
