# Lottery System

🌍 Language / Idioma / Sprache / Idioma / Langue:
[🇪🇸 Español](README.md) · **🇬🇧 English** · [🇩🇪 Deutsch](README.de.md) · [🇧🇷 Português](README.pt.md) · [🇫🇷 Français](README.fr.md)

Lottery management system built with PHP, MySQL, CSS and vanilla JavaScript.
No dependencies, no build, no framework: upload, install and use.

Created with artificial intelligence (AI) assistance.

### Features

- **Interface in 5 languages** (English by default, Español, Deutsch, Português, Français), extensible to more (see [🌍 Languages](#-languages))
- **Public page** with a number grid from 000 to 999, news and customizable texts
- **Admin panel** protected by session login
- **Participant registration** with automatic validation (duplicate names, blacklist, taken numbers)
- **Auto-assignment** of a random number if the chosen number is already taken
- **Blacklist** to exclude unwanted participants
- **News, texts, prizes, sponsors and administrators management**
- **Live draw** with participant search and manual winner selection (circular rule: closest above, wrapping to `000`)
- **Automatic backups** (DB + files) via cron, with a monitoring page in the admin
- **Responsive** and compatible with old browsers (no JavaScript on the public page)
- **Multi-admin** with session authentication and brute-force protection

### 🌍 Languages

The interface (public page + admin panel) is available in 5 languages:

| Code | Language | File |
|------|----------|------|
| `en` | English (default) | `includes/lang/en.php` |
| `es` | Español | `includes/lang/es.php` |
| `de` | Deutsch | `includes/lang/de.php` |
| `pt` | Português | `includes/lang/pt.php` |
| `fr` | Français | `includes/lang/fr.php` |

- **Public page**: visible language selector (flags) + `?lang=es` + browser detection. Stored in session and cookie (1 year).
- **Admin panel**: the dashboard lets you change the **panel default language** (global, stored in DB as `custom_texts.admin_lang_default`, affects all admins) and **your personal language** (only your session/browser, from the dashboard or the sidebar).
- Only the **interface** is translated. Admin-created content (news, custom texts, prize names) is shown exactly as written.

#### Adding a new language (2 minutes)

```bash
cp includes/lang/en.php includes/lang/it.php   # or copy includes/lang/_template.php
```

1. Translate the **values** in `includes/lang/it.php` (keep the keys and `{placeholders}` intact).
2. Register the language in `includes/lang.php` (one line):
```php
'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
```
3. Check that no key is missing and test it in the browser:
```bash
php -r '$en=require"includes/lang/en.php";$xx=require"includes/lang/it.php";$m=array_diff_key($en,$xx);$e=array_diff_key($xx,$en);echo"missing: ".count($m).", extra: ".count($e).PHP_EOL;'
# http://your-server/?lang=it  (+ login, dashboard, participants, draw)
```

Nothing else to touch: selectors, validation and fallbacks (missing key → English) iterate the registry automatically.

#### Contributing a language (Pull Request)

Speak another language? Add it with a PR and we will include it!

1. Fork and create a branch `lang-xx` (e.g. `lang-it`).
2. In your branch, touch **only** these files:
   - `includes/lang/xx.php` (new, copied from `en.php` and translated),
   - `includes/lang.php` (one line in `SUPPORTED_LANGS`),
   - optional: `README.xx.md` with the translation of this README.
3. PR checklist:
   - [ ] All keys from `en.php` exist (the `php -r` command above says `missing: 0, extra: 0`).
   - [ ] The `{placeholders}` (`{name}`, `{min}`, `{pos}`…) are intact.
   - [ ] Tested with `?lang=xx` on the public page + login + dashboard.
4. Open the PR against `main` titled `Add xx language (Italiano)` with a screenshot of the public page in your language. Language PRs are reviewed on an ongoing basis; we will credit you in the release notes. Thank you! 🙏

### Requirements

- Apache with mod_rewrite (or Nginx equivalent)
- PHP 7.4 or higher (MySQLi extension)
- MySQL 5.6 / MariaDB 10.x or higher
- `mysqldump` or `mariadb-dump` on the server (only for backups)

### Installation

```bash
git clone https://github.com/leuros88/lottery_manual.git
cd lottery_manual
```

#### Option A — Automatic installer (recommended)

1. Go to `http://your-server/install.php`, fill in the MySQL
   credentials (preloaded from `includes/config.php`) and press
   **"Run Installation"**.
   The installer creates the database, imports `sql/schema.sql`, creates the
   admin user you specify (default `admin` / `admin2026`)
   and saves the credentials into `includes/config.php` automatically
   (if the file is not writable, it will tell you to
   edit it by hand).
3. Go to `http://your-server/admin/` with that user (or your renamed folder if you already applied `ADMIN_DIR`).
4. **Change the password** right after logging in (*Change Password* menu).
5. **Delete `install.php`** or uncomment the `install.php` rule in
   `.htaccess` to block it.

#### Option B — Manual installation

If you prefer not to use the installer:

```bash
mysql -u YOUR_USER -p < sql/schema.sql
```

Or import `sql/schema.sql` from phpMyAdmin (*Import* tab).
The schema already includes a test admin user
(`admin` / `admin2026`): log in with it and **change the password**
right away (admin panel *Change Password* menu).

Then edit `includes/config.php` with your credentials.

### Configuration (`includes/config.php`)

Everything user-adjustable lives in a single file:

| Constant       | Description |
|----------------|-------------|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | MySQL connection |
| `SITE_NAME`    | Name shown on the site |
| `PROJECT_ROOT` | Project root (autodetected, usually don't touch) |
| `BACKUP_DIR`   | Where backups are stored. Default `cron/backups/` (inside the project). **In production point it outside `public_html`**, e.g. `/home/user/private/backups` |
| `MAX_BACKUPS`  | Max backups to keep (default 10, automatic rotation) |
| `MYSQLDUMP_BIN`| Path to the dump binary (`/usr/bin/mariadb-dump` or the output of `which mysqldump`) |
| `ADMIN_DIR`    | Admin panel folder name (default `admin`). Change it to hide the panel (see below) |

### Hiding the panel: renaming the admin folder

The `/admin/` path is public by default. As an extra layer of
security (not a replacement for a strong password), rename it to something
unpredictable, e.g. `panel-x7k9q2`:

```bash
mv admin panel-x7k9q2
```

and update `includes/config.php` to match:

```php
define('ADMIN_DIR', 'panel-x7k9q2');
```

All URLs, redirects and the installer button use `ADMIN_DIR`
(via `adminUrl()` in `includes/auth.php`), so nothing else needs touching.
The admin JS uses a relative path (`ajax.php?...`), also
immune to renaming. Then go to
`http://your-server/panel-x7k9q2/`; the old path stops existing
(404 error).

> Only letters, numbers, dashes and underscores, no slashes.
> After renaming, delete `install.php` and `migrate.php` anyway.

### Admin panel (`/admin/` by default, renameable via `ADMIN_DIR`)

| Page | Use |
|--------|-----|
| `dashboard.php` | Summary and quick actions (also: panel language) |
| `participants.php` | Participant add/edit (up to 3 numbers per person) |
| `draw.php` | Live draw: find a number and assign the winner to the prize |
| `winners.php` | Winner history |
| `prizes.php` | Prizes and positions |
| `blacklist.php` | Blocked names |
| `news.php` | Public page news |
| `texts.php` | Customizable texts (header, footer, status messages, etc.) |
| `sponsors.php` | Sponsor carousel |
| `backups.php` | Backup monitoring (reads `BACKUP_DIR` from config) |
| `preview.php` | Public page preview |
| `admins.php` | Administrator management |
| `change_password.php` | Own password change |
| `reset.php` | Full lottery reset (danger zone) |

### Participant registration — number auto-assignment

If, when registering a participant, the chosen number is already taken by
another user, the system **does not error**: it automatically assigns **another
free number at random** and warns you in a modal with the detail
`requested → assigned`. Applies both when creating and editing (each taken
number is replaced by a different free one); it only fails if no free number
remains.

The success message is **configurable** and designed to be **sent to the
user** (e.g. via WhatsApp): the modal has a **📋 Copy** button that copies the
text already filled with their name and final numbers. Texts are edited in
the admin (*Custom Texts* → `texts.php`):

| Key | Variables | When used |
|-------|-----------|---------------|
| `participant_create_success` | `{name}`, `{numbers}` | Participant registration |
| `participant_update_success` | `{name}`, `{numbers}` | Participant edit |
| `participant_duplicate` | `{name}` | Name already registered |
| `participant_blacklisted` | `{name}` | Name is blacklisted |
| `participant_error` | `{reason}` | Other errors |

### Live draw — winner rule (circular)

Winners **don't need an exact number match**. The system
draws/enters a number `drawn` (000–999) and the **closest free participant
number above** wins, wrapping to `000` if there is none equal or higher
(circular distance `(candidate - drawn + 1000) % 1000`, minimum).

- Each participant can have up to 3 numbers (`number`, `number2`,
  `number3`); all three count.
- If the drawn number is free, it wins directly (distance 0).
- Only errors when no participant remains available.

#### Unique or repeated winner (dashboard → Winner Rules)

By default **repeating is allowed**: the same participant can win several
prizes (`custom_texts.unique_winners = '0'`). If you enable **Unique winner**
(`'1'`), each participant can only win one prize and winners are
excluded from subsequent draws.

- With few participants and unique mode on, after assigning 1 winner the
  next prize shows an explanatory message (everyone already won / no
  participants). With repeat mode this never happens: the draw always
  finds a candidate while participants are registered.
- The setting lives in the dashboard (`dashboard.php` → Winner Rules) and is applied by
  `findClosestParticipant()`, `draw.php` and `ajax.php`.

#### Existing installs: migrate.php

If the DB was already created before this change, log in to the admin and open
`/migrate.php` in the browser: it creates `draw_audits` if missing and inserts
`draw_mode` (`manual`), `unique_winners` (`0`, repeat allowed),
`site_title` and `admin_lang_default` (`en`, panel language) with
`INSERT IGNORE` (idempotent, can be re-run). **Delete it afterwards**,
just like `install.php`.

Examples:

| Drawn | Free taken numbers | Winner | Reason |
|----------|-------------------------|---------|--------|
| `200` | `199`, `205`, `206` | `205` (user b) | Closest above `200` |
| `205` | `199`, `205`, `206` | `205` | Exact match |
| `998` | `005`, `150` | `005` | Nothing `>= 998`, circular wrap to `000` |

The rule applies to all 3 modes (`Manual`, `Random.org`, `Local random`) and
is implemented in `findClosestParticipant()` (`includes/functions.php`),
used by `admin/draw.php` and `admin/ajax.php`
(`verifiable_random`). The draw screen shows both numbers:
**Drawn** and **Winning number**, with a
notice when wrapping to `000` occurred. The audit (`draw_audits`) stores both
(`drawn_number` / `winning_number`), the participant, the mode/provider and a
`proof_hash`.

### Backups

Schedule the script in the server cron:

```cron
0 3 * * * php /full/path/to/cron/backup.php
```

Each run generates a `backup_YYYY-MM-DD_HHMM.tar.gz` with the DB dump +
project files, and deletes older ones beyond
`MAX_BACKUPS`. Status can be checked in the admin (*Backups*).

> Paths are configured in `includes/config.php`, no need to edit
> `cron/backup.php`.

### Structure

```
├── index.php                # Public page
├── install.php              # Installer (delete after use)
├── migrate.php              # Migration for already-installed DBs (delete after use)
├── sql/
│   └── schema.sql           #   Full schema (9 tables + default texts)
├── admin/           # Admin panel
│   ├── index.php            #   Login
│   ├── dashboard.php        #   Main panel (also: panel language)
│   ├── participants.php     #   Participant management
│   ├── draw.php             #   Live draw
│   ├── winners.php          #   Winner history
│   ├── prizes.php           #   Prizes
│   ├── blacklist.php        #   Blacklist
│   ├── news.php             #   News
│   ├── texts.php            #   Customizable texts
│   ├── sponsors.php         #   Sponsors
│   ├── backups.php          #   Backup monitoring
│   ├── preview.php          #   Public preview
│   ├── admins.php           #   Administrators
│   ├── change_password.php  #   Password change
│   ├── reset.php            #   Full reset (danger)
│   └── ajax.php             #   AJAX endpoint (random number)
├── includes/                # PHP core
│   ├── config.php           #   DB connection, constants and backup paths
│   ├── functions.php        #   Helper functions
│   ├── auth.php             #   Authentication and brute-force protection
│   ├── lang.php             #   Multilanguage system (registry + t())
│   └── lang/                #   Dictionaries: en, es, de, pt, fr (+ _template.php)
├── cron/
│   └── backup.php           #   Backup script (reads includes/config.php)
└── assets/
    ├── css/                 #   Styles (public + admin)
    ├── js/                  #   JavaScript (admin only)
    └── img/sponsors/        #   Sponsor images
```

### Security

- Change the default credentials (`admin` / `admin2026`) after installing.
- **Delete or block `install.php` and `migrate.php`** after using them.
- Point `BACKUP_DIR` outside the public directory in production.
- Passwords are stored with `password_hash()`; login blocks the IP
  after 5 failed attempts for 48 h.
- `.htaccess` denies direct access to `includes/`, `sql/` and `cron/`.

### License

GPL-3.0 — see `LICENSE` file.
