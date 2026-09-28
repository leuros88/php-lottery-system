# Lottery System — AGENTS.md

## Stack

- PHP 7.4+ (procedural, MySQLi) + MySQL, CSS3, vanilla JS (admin only)
- No build tooling, no package manager, no test framework, no autoloader

## Setup

1. Edit `includes/config.php` with DB credentials and `BACKUP_DIR`.
2. Hit `/install.php` in browser → creates DB + imports `sql/schema.sql` + creates default admin (user: `admin`, pass: `admin2026`).
   Manual alternative: import `sql/schema.sql` directly (see instructions at the end of that file).
3. **Delete or block `install.php`** after install (`.htaccess` has a commented-out RewriteRule).

## Entrypoints

| Path | Purpose |
|---|---|
| `/index.php` | Public page — number grid, news, custom texts. No JS required. |
| `/<ADMIN_DIR>/index.php` | Admin login (default folder `admin`, renameable via `ADMIN_DIR`) |
| `/<ADMIN_DIR>/dashboard.php` | Admin panel (stats + quick actions) |

## Architecture

### Database (9 tables, InnoDB, utf8)

`administrators` ← `winners` → `participants` (CASCADE delete). `winners` also FK to `prizes`. `blacklist`, `news`, `custom_texts`, `login_attempts` standalone.

Numbers: `CHAR(4)`, zero-padded. Digit mode via `custom_texts.number_digits` (`3` = 000–999 default, `4` = 0000–9999, switchable in dashboard with automatic migration). Helpers in `functions.php`: `getNumberDigits()`, `setNumberDigits()`, `getNumberRangeMax()`, `formatLotteryNumber()`. Sorted via `CAST(number AS UNSIGNED)`. Structural settings (`number_digits`, `draw_mode`) are locked while the lottery holds data — see `isLotteryInProgress()` / `lotteryLockedMessage()`; only a full reset unlocks them.

### includes/

- `config.php` — defines DB/SITE constants, starts session, creates `$conn`. Also defines `ADMIN_DIR` (default `admin`): rename the folder + update the constant to hide the panel URL.
- `auth.php` — `adminUrl($path)` builds URLs from `ADMIN_DIR`; `requireLogin()` redirects to `adminUrl('index.php')` if not logged in. Brute force protection via `isIpBlocked()`, `recordFailedAttempt()`, `getFailedAttemptCount()`, `clearFailedAttempts()` — blocks IP after 5 failed attempts for 48 hours. `csrfToken()`/`verifyCsrfToken()` defined but **not used** in any form.
- `functions.php` — all DB query helpers take `$conn` as first param.

### Admin pages (under /<ADMIN_DIR>/)

All admin pages follow the same pattern:
1. `require config.php`, `require functions.php` (if needed), `require auth.php`
2. Handle GET/POST actions before any output
3. `require header.php` (calls `requireLogin()`, renders sidebar nav)
4. Page content (form cards + data tables)
5. `require footer.php` (closes HTML)

`header.php` highlights the current page in the sidebar by comparing `basename($_SERVER['PHP_SELF'])`.

### Participant validation (participants.php)

When adding:
1. Name already registered? → Error
2. Name in blacklist? → Error
3. Number taken? → **Auto-assign a random available number** (warning msg)
4. All clear → Insert

Editing checks name/num uniqueness excluding the current record. Blacklist check still applies.

### Live Draw (draw.php)

- Winner assignment is via **GET** flow: enter a number → lookup participant → confirm → `?assign=N&prize_id=M`.
- Verifies prize not yet won and participant hasn't already won.
- Random number button uses `ajax.php?action=random_number` to pick an available number.

### CSRF

`auth.php` defines `csrfToken()`/`verifyCsrfToken()` but no forms use them. The admin path is renameable via `ADMIN_DIR` (default `/admin/`) — renaming hides the panel URL.

### Public page (index.php)

- Tooltips use CSS `:target` — clicking a taken number opens a fixed-position div with participant name. No JS needed.
- Number grid uses `float: left` for old-browser compatibility (not flexbox/grid).
- Responsive via `@media` breakpoints (768px, 480px, 360px).

### AJAX

`/admin/ajax.php?action=random_number` returns `{"number":"042"}` — used by the "Random" button in `participants.php`.

### i18n (multilanguage UI: en default, es, de, pt, fr)

- `includes/lang.php` — `SUPPORTED_LANGS` registry, `resolveAppLang()` (`?lang=` > session > cookie > browser (public only) > `custom_texts.admin_lang_default` > `'en'`), `t($key, $params)` (falls back to English, then to the key), `e($key)` (escaped echo). Loaded at the end of `config.php`.
- Dictionaries in `includes/lang/{en,es,de,pt,fr}.php` (same keys everywhere) + `_template.php` for new languages. **To add a language: copy `en.php` → `xx.php`, translate values, add one line to `SUPPORTED_LANGS`.** Nothing else needed (switchers, validation, fallbacks iterate the registry).
- All UI strings go through `t()`; user-authored DB content (`news`, `custom_texts`, prize names) is intentionally NOT translated.
- Dashboard has two language forms: global default (`custom_texts.admin_lang_default`, all admins) + personal session override (`$_SESSION['lang']` + `site_lang` cookie). Keep all POST handling above the `header.php` include.
- `functions.php` dynamic messages (`lotteryLockedMessage()`, `getDrawExhaustedMessage()`, `drawModeLabel()`, `numberDigitsLabel()`, `setNumberDigits()`) use `t()` with `{placeholders}`.

### Security notes

- Passwords hashed with `password_hash()`/`password_verify()`.
- SQL mostly uses prepared statements. Exception: `draw.php` lines 16, 22 (inline `$prize_id`/`$participant_id` — both cast to `int` so safe).
- `.htaccess` blocks `/includes/` and `/sql/` directly. `install.php` should be blocked post-install.
- XSS: all user output goes through `htmlspecialchars()` except `custom_texts` and `news` content (intentionally allow HTML).

## Style conventions

- No classes/autoloading — procedural PHP with strict require_once paths.
- Variables: snake_case. Functions: camelCase.
- CSS: two files (`style.css` public, `admin.css` admin) — no preprocessor.
- All admin pages place PHP logic *above* the header include (redirects/messages must happen before HTML output).
