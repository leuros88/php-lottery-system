<?php
// Lottery System - Internationalization (i18n)
// Supported UI languages: English (default), Español, Deutsch, Português, Français.
//
// TO ADD A NEW LANGUAGE (2 minutes):
//   1. cp includes/lang/en.php includes/lang/xx.php  (or copy _template.php)
//      and translate the values (keep the keys and {placeholders} intact).
//   2. Add one line to SUPPORTED_LANGS below:
//      'xx' => ['label' => 'Native name', 'flag' => '🏳️'],
// That's it: the public switcher, admin sidebar/login/dashboard selectors,
// validation and fallbacks all iterate this registry — no other code changes.

if (!defined('DEFAULT_LANG')) {
    define('DEFAULT_LANG', 'en');
}

if (!defined('SUPPORTED_LANGS')) {
    define('SUPPORTED_LANGS', [
        'en' => ['label' => 'English',   'flag' => '🇬🇧'],
        'es' => ['label' => 'Español',   'flag' => '🇪🇸'],
        'de' => ['label' => 'Deutsch',   'flag' => '🇩🇪'],
        'pt' => ['label' => 'Português', 'flag' => '🇧🇷'],
        'fr' => ['label' => 'Français',  'flag' => '🇫🇷'],
    ]);
}

function supportedLangs() {
    return defined('SUPPORTED_LANGS') ? SUPPORTED_LANGS : ['en' => ['label' => 'English', 'flag' => '🇬🇧']];
}

function isSupportedLang($code) {
    $langs = supportedLangs();
    return is_string($code) && isset($langs[strtolower(trim((string)$code))]);
}

function normalizeLang($code) {
    $code = strtolower(trim((string)$code));
    return isSupportedLang($code) ? $code : DEFAULT_LANG;
}

// Global default language for the admin panel, stored in
// custom_texts.admin_lang_default. Falls back to DEFAULT_LANG when the
// DB/table/row is missing (e.g. fresh checkout, cron, installer).
function getDefaultAdminLang($conn = null) {
    if (!($conn instanceof mysqli)) {
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $conn = $GLOBALS['conn'];
        } else {
            return DEFAULT_LANG;
        }
    }
    try {
        $result = @$conn->query("SELECT content FROM custom_texts WHERE `key` = 'admin_lang_default' LIMIT 1");
    } catch (Throwable $e) {
        return DEFAULT_LANG;
    }
    if (!$result) {
        return DEFAULT_LANG;
    }
    $row = $result->fetch_assoc();
    if (method_exists($result, 'free')) {
        $result->free();
    }
    $code = strtolower(trim((string)($row['content'] ?? '')));
    return isSupportedLang($code) ? $code : DEFAULT_LANG;
}

// Best match from Accept-Language header against the registry (public only).
function detectBrowserLang() {
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (!is_string($header) || $header === '') {
        return null;
    }
    foreach (explode(',', $header) as $part) {
        $part = strtolower(trim(explode(';', trim($part))[0]));
        if ($part === '') {
            continue;
        }
        $short = substr($part, 0, 2);
        if (isSupportedLang($short)) {
            return $short;
        }
    }
    return null;
}

function isAdminRequest() {
    $dir = defined('ADMIN_DIR') ? trim(ADMIN_DIR, '/') : 'admin';
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    if (is_string($script) && $script !== '' && strpos($script, '/' . $dir . '/') !== false) {
        return true;
    }
    return false;
}

// Persist explicitly: session + 1-year cookie on '/'.
function setAppLang($code) {
    $code = normalizeLang($code);
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['lang'] = $code;
    }
    if (!headers_sent()) {
        setcookie('site_lang', $code, time() + 365 * 24 * 3600, '/');
    }
    $GLOBALS['APP_LANG'] = $code;
    return $code;
}

// Resolve current language. Priority:
// ?lang= (valid → persist) > session > cookie > browser (public only)
// > admin global default (DB) > DEFAULT_LANG.
function resolveAppLang($conn = null) {
    if (isset($GLOBALS['APP_LANG']) && isSupportedLang($GLOBALS['APP_LANG'])) {
        // Still honour an explicit ?lang= override.
        if (isset($_GET['lang']) && isSupportedLang($_GET['lang'])) {
            return setAppLang($_GET['lang']);
        }
        return $GLOBALS['APP_LANG'];
    }
    if (isset($_GET['lang']) && isSupportedLang($_GET['lang'])) {
        return setAppLang($_GET['lang']);
    }
    if (isset($_SESSION['lang']) && isSupportedLang($_SESSION['lang'])) {
        $GLOBALS['APP_LANG'] = strtolower($_SESSION['lang']);
        return $GLOBALS['APP_LANG'];
    }
    if (isset($_COOKIE['site_lang']) && isSupportedLang($_COOKIE['site_lang'])) {
        $GLOBALS['APP_LANG'] = strtolower($_COOKIE['site_lang']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $GLOBALS['APP_LANG'];
        }
        return $GLOBALS['APP_LANG'];
    }
    $is_admin = isAdminRequest();
    if (!$is_admin) {
        $browser = detectBrowserLang();
        if ($browser !== null) {
            $GLOBALS['APP_LANG'] = $browser;
            return $browser;
        }
    }
    $fallback = $is_admin ? getDefaultAdminLang($conn) : DEFAULT_LANG;
    if (!$is_admin && $fallback === DEFAULT_LANG) {
        // Public visitors without any preference: still honour the admin's
        // configured global default if it differs from English.
        $fallback = getDefaultAdminLang($conn);
    }
    $GLOBALS['APP_LANG'] = $fallback;
    return $fallback;
}

function currentLang($conn = null) {
    if (isset($GLOBALS['APP_LANG']) && isSupportedLang($GLOBALS['APP_LANG'])) {
        return $GLOBALS['APP_LANG'];
    }
    return resolveAppLang($conn);
}

function loadLangDict($code) {
    static $cache = [];
    $code = normalizeLang($code);
    if (isset($cache[$code])) {
        return $cache[$code];
    }
    $file = __DIR__ . '/lang/' . $code . '.php';
    if (is_readable($file)) {
        $dict = require $file;
        $cache[$code] = is_array($dict) ? $dict : [];
    } else {
        $cache[$code] = [];
    }
    return $cache[$code];
}

// Translate a key. Falls back to English, then to the key itself.
// Placeholders: t('key', ['name' => $name]) replaces {name}.
function t($key, $params = []) {
    $lang = isset($GLOBALS['APP_LANG']) && isSupportedLang($GLOBALS['APP_LANG'])
        ? $GLOBALS['APP_LANG']
        : DEFAULT_LANG;
    $dict = loadLangDict($lang);
    if (array_key_exists($key, $dict)) {
        $text = $dict[$key];
    } else {
        $en = ($lang === 'en') ? $dict : loadLangDict('en');
        $text = array_key_exists($key, $en) ? $en[$key] : $key;
    }
    if (!empty($params) && is_array($params)) {
        foreach ($params as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, $text);
        }
    }
    return $text;
}

// Echo a translated string escaped for HTML.
function e($key, $params = []) {
    echo htmlspecialchars(t($key, $params), ENT_QUOTES, 'UTF-8');
}
