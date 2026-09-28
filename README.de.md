# Lottery System

🌍 Sprache / Idioma / Language / Idioma / Langue:
[🇪🇸 Español](README.md) · [🇬🇧 English](README.en.md) · **🇩🇪 Deutsch** · [🇧🇷 Português](README.pt.md) · [🇫🇷 Français](README.fr.md)

Lotterie-Verwaltungssystem, entwickelt mit PHP, MySQL, CSS und Vanilla-JavaScript.
Keine Abhängigkeiten, kein Build, kein Framework: hochladen, installieren, nutzen.

### Funktionen

- **Oberfläche in 5 Sprachen** (Standard English, Español, Deutsch, Português, Français), erweiterbar (siehe [🌍 Sprachen](#-sprachen))
- **Öffentliche Seite** mit Nummernübersicht von 000 bis 999, Neuigkeiten und anpassbaren Texten
- **Admin-Bereich** mit Session-Login geschützt
- **Teilnehmerregistrierung** mit automatischer Validierung (doppelte Namen, Blacklist, belegte Nummern)
- **Auto-Zuweisung** einer Zufallsnummer, falls die gewählte Nummer bereits belegt ist
- **Blacklist** zum Ausschließen unerwünschter Teilnehmer
- **Verwaltung von Neuigkeiten, Texten, Preisen, Sponsoren und Administratoren**
- **Live-Ziehung** mit Teilnehmersuche und manueller Gewinnerzuordnung (kreisförmige Regel: nächsthöhere, Umbruch zu `000`)
- **Automatische Backups** (DB + Dateien) per Cron, mit Monitoring-Seite im Admin
- **Responsiv** und kompatibel mit alten Browsern (kein JavaScript auf der öffentlichen Seite)
- **Multi-Admin** mit Session-Authentifizierung und Brute-Force-Schutz

### 🌍 Sprachen

Die Oberfläche (öffentliche Seite + Admin-Panel) ist in 5 Sprachen verfügbar:

| Code | Sprache | Datei |
|------|---------|-------|
| `en` | English (Standard) | `includes/lang/en.php` |
| `es` | Español | `includes/lang/es.php` |
| `de` | Deutsch | `includes/lang/de.php` |
| `pt` | Português | `includes/lang/pt.php` |
| `fr` | Français | `includes/lang/fr.php` |

- **Öffentliche Seite**: sichtbarer Sprachwähler (Flaggen) + `?lang=de` + Browser-Erkennung. Gespeichert in Session und Cookie (1 Jahr).
- **Admin-Panel**: Im Dashboard lassen sich die **Standardsprache des Panels** (global, in DB als `custom_texts.admin_lang_default`, gilt für alle Admins) und **Ihre persönliche Sprache** (nur Ihre Sitzung/Ihr Browser, über Dashboard oder Sidebar) ändern.
- Nur die **Oberfläche** wird übersetzt. Vom Admin erstellte Inhalte (Neuigkeiten, eigene Texte, Preisnamen) werden so angezeigt, wie sie geschrieben wurden.

#### Neue Sprache hinzufügen (2 Minuten)

```bash
cp includes/lang/en.php includes/lang/it.php   # oder includes/lang/_template.php kopieren
```

1. Die **Werte** in `includes/lang/it.php` übersetzen (Schlüssel und `{Platzhalter}` unverändert lassen).
2. Sprache in `includes/lang.php` registrieren (eine Zeile):
```php
'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
```
3. Prüfen, dass kein Schlüssel fehlt, und im Browser testen:
```bash
php -r '$en=require"includes/lang/en.php";$xx=require"includes/lang/it.php";$m=array_diff_key($en,$xx);$e=array_diff_key($xx,$en);echo"fehlend: ".count($m).", extra: ".count($e).PHP_EOL;'
# http://dein-server/?lang=it  (+ Login, Dashboard, Teilnehmer, Ziehung)
```

Sonst nichts zu ändern: Wähler, Validierung und Fallbacks (fehlender Schlüssel → Englisch) nutzen automatisch das Register.

#### Sprache beisteuern (Pull Request)

Sprichst du eine weitere Sprache? Füge sie per PR hinzu und wir übernehmen sie!

1. Fork erstellen und Branch `lang-xx` anlegen (z. B. `lang-it`).
2. In deinem Branch **nur** diese Dateien anfassen:
   - `includes/lang/xx.php` (neu, von `en.php` kopiert und übersetzt),
   - `includes/lang.php` (eine Zeile in `SUPPORTED_LANGS`),
   - optional: `README.xx.md` mit der Übersetzung dieser README.
3. PR-Checkliste:
   - [ ] Alle Schlüssel aus `en.php` vorhanden (Befehl oben meldet `fehlend: 0, extra: 0`).
   - [ ] Die `{Platzhalter}` (`{name}`, `{min}`, `{pos}`…) sind intakt.
   - [ ] Mit `?lang=xx` getestet (öffentliche Seite + Login + Dashboard).
4. PR gegen `main` öffnen mit Titel `Add xx language (Italiano)` und Screenshot der öffentlichen Seite in deiner Sprache. Sprach-PRs werden fortlaufend geprüft; wir erwähnen dich in den Release-Notes. Danke! 🙏

### Voraussetzungen

- Apache mit mod_rewrite (oder Nginx-Äquivalent)
- PHP 7.4 oder höher (MySQLi-Erweiterung)
- MySQL 5.6 / MariaDB 10.x oder höher
- `mysqldump` oder `mariadb-dump` auf dem Server (nur für Backups)

### Installation

```bash
git clone https://github.com/leuros88/lottery_manual.git
cd lottery_manual
```

#### Option A — Automatischer Installer (empfohlen)

1. `http://dein-server/install.php` aufrufen, MySQL-Zugangsdaten
   ausfüllen (aus `includes/config.php` vorausgefüllt) und
   **"Run Installation"** drücken.
   Der Installer erstellt die Datenbank, importiert `sql/schema.sql`, erstellt den
   angegebenen Admin-Benutzer (Standard `admin` / `admin2026`)
   und speichert die Zugangsdaten automatisch in `includes/config.php`
   (falls die Datei nicht beschreibbar ist, wirst du aufgefordert,
   sie von Hand zu bearbeiten).
3. `http://dein-server/admin/` mit diesem Benutzer aufrufen (oder dein umbenannter Ordner, falls `ADMIN_DIR` gesetzt).
4. **Passwort** direkt nach dem Einloggen ändern (Menü *Change Password*).
5. **`install.php` löschen** oder die `install.php`-Regel in
   `.htaccess` einkommentieren, um sie zu sperren.

#### Option B — Manuelle Installation

Ohne Installer:

```bash
mysql -u DEIN_BENUTZER -p < sql/schema.sql
```

Oder `sql/schema.sql` per phpMyAdmin importieren (Reiter *Importieren*).
Das Schema enthält bereits einen Test-Admin (`admin` / `admin2026`):
damit einloggen und **sofort das Passwort ändern**
(Menü *Change Password* im Admin-Panel).

Danach `includes/config.php` mit deinen Zugangsdaten bearbeiten.

### Konfiguration (`includes/config.php`)

Alles Benutzereinstellbare liegt in einer einzigen Datei:

| Konstante       | Beschreibung |
|----------------|-------------|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | MySQL-Verbindung |
| `SITE_NAME`    | Angezeigter Website-Name |
| `PROJECT_ROOT` | Projektwurzel (auto-erkannt, meist nicht anfassen) |
| `BACKUP_DIR`   | Backup-Ziel. Standard `cron/backups/` (im Projekt). **In Produktion außerhalb von `public_html` legen**, z. B. `/home/user/private/backups` |
| `MAX_BACKUPS`  | Max. aufbewahrte Backups (Standard 10, automatische Rotation) |
| `MYSQLDUMP_BIN`| Pfad zum Dump-Binary (`/usr/bin/mariadb-dump` oder Ausgabe von `which mysqldump`) |
| `ADMIN_DIR`    | Ordnername des Admin-Panels (Standard `admin`). Zum Verstecken ändern (s. unten) |

### Panel verstecken: Admin-Ordner umbenennen

Der Pfad `/admin/` ist standardmäßig öffentlich. Als zusätzliche
Sicherheitsebene (kein Ersatz für ein starkes Passwort) benenne ihn um, z. B. `panel-x7k9q2`:

```bash
mv admin panel-x7k9q2
```

und in `includes/config.php` anpassen:

```php
define('ADMIN_DIR', 'panel-x7k9q2');
```

Alle URLs, Weiterleitungen und der Installer-Button nutzen `ADMIN_DIR`
(über `adminUrl()` in `includes/auth.php`), sonst ist nichts zu ändern.
Das Admin-JS nutzt einen relativen Pfad (`ajax.php?...`), ebenfalls
unempfindlich. Danach `http://dein-server/panel-x7k9q2/` aufrufen;
der alte Pfad existiert nicht mehr (404).

> Nur Buchstaben, Zahlen, Bindestriche und Unterstriche, keine Slashes.
> Nach dem Umbenennen trotzdem `install.php` und `migrate.php` löschen.

### Admin-Panel (`/admin/` Standard, umbenennbar via `ADMIN_DIR`)

| Seite | Verwendung |
|--------|-----|
| `dashboard.php` | Übersicht und Schnellzugriff (auch: Panel-Sprache) |
| `participants.php` | Teilnehmer anlegen/bearbeiten (bis 3 Nummern pro Person) |
| `draw.php` | Live-Ziehung: Nummer suchen und Gewinner zuordnen |
| `winners.php` | Gewinner-Historie |
| `prizes.php` | Preise und Positionen |
| `blacklist.php` | Gesperrte Namen |
| `news.php` | Neuigkeiten der öffentlichen Seite |
| `texts.php` | Anpassbare Texte (Kopf, Fuß, Statusmeldungen usw.) |
| `sponsors.php` | Sponsoren-Karussell |
| `backups.php` | Backup-Monitoring (liest `BACKUP_DIR` aus der Config) |
| `preview.php` | Vorschau der öffentlichen Seite |
| `admins.php` | Administratoren-Verwaltung |
| `change_password.php` | Eigenes Passwort ändern |
| `reset.php` | Komplettes Zurücksetzen (Gefahrenzone) |

### Teilnehmer anlegen — Nummern-Auto-Zuweisung

Ist beim Registrieren die gewählte Nummer bereits belegt, gibt das System
**keinen Fehler**: Es weist automatisch **eine andere freie Nummer per Zufall**
zu und meldet es in einem Modal (`gewünscht → zugewiesen`). Gilt beim Anlegen
wie beim Bearbeiten (jede belegte Nummer wird durch eine andere freie ersetzt);
schlägt nur fehl, wenn keine Nummer mehr frei ist.

Die Erfolgsmeldung ist **konfigurierbar** und zum **Weiterschicken an den
Nutzer** gedacht (z. B. per WhatsApp): Das Modal hat einen **📋 Kopieren**-Button,
der den ausgefüllten Text mit Name und finalen Nummern kopiert. Texte im
Admin bearbeiten (*Custom Texts* → `texts.php`):

| Schlüssel | Variablen | Wann verwendet |
|-------|-----------|---------------|
| `participant_create_success` | `{name}`, `{numbers}` | Teilnehmer anlegen |
| `participant_update_success` | `{name}`, `{numbers}` | Teilnehmer bearbeiten |
| `participant_duplicate` | `{name}` | Name bereits registriert |
| `participant_blacklisted` | `{name}` | Name auf der Blacklist |
| `participant_error` | `{reason}` | Sonstige Fehler |

### Live-Ziehung — Gewinnerregel (kreisförmig)

Gewinner brauchen **keine exakte Übereinstimmung**. Das System
zieht/gibt eine Nummer `drawn` (000–999) ein, und die **nächste freie
Teilnehmernummer darüber** gewinnt; Umbruch zu `000`, falls keine gleiche oder
höhere existiert (kreisförmige Distanz `(Kandidat - gezogen + 1000) % 1000`, Minimum).

- Jeder Teilnehmer kann bis zu 3 Nummern haben (`number`, `number2`,
  `number3`); alle drei zählen.
- Ist die gezogene Nummer frei, gewinnt sie direkt (Distanz 0).
- Nur Fehler, wenn kein Teilnehmer mehr verfügbar ist.

#### Einmaliger oder wiederholter Gewinner (Dashboard → Winner Rules)

Standard ist **Wiederholung erlaubt**: Derselbe Teilnehmer kann mehrere
Preise gewinnen (`custom_texts.unique_winners = '0'`). Bei **Einmaliger Gewinner**
(`'1'`) kann jeder Teilnehmer nur einen Preis gewinnen, Gewinner sind
von weiteren Ziehungen ausgeschlossen.

#### Bestehende Installationen: migrate.php

War die DB schon vor dieser Änderung angelegt, im Admin
`/migrate.php` im Browser öffnen: erstellt `draw_audits` falls fehlend und fügt
`draw_mode` (`manual`), `unique_winners` (`0`), `site_title` und
`admin_lang_default` (`en`) per `INSERT IGNORE` ein (idempotent, wiederholbar).
**Danach löschen**, wie `install.php`.

Beispiele:

| Gezogen | Freie belegte Nummern | Gewinner | Grund |
|----------|-------------------------|---------|--------|
| `200` | `199`, `205`, `206` | `205` (Nutzer b) | Nächsthöhere zu `200` |
| `205` | `199`, `205`, `206` | `205` | Exakter Treffer |
| `998` | `005`, `150` | `005` | Nichts `>= 998`, kreisförmiger Umbruch zu `000` |

Die Regel gilt für alle 3 Modi (`Manuell`, `Random.org`, `Lokaler Zufall`) und
ist in `findClosestParticipant()` (`includes/functions.php`) implementiert,
genutzt von `admin/draw.php` und `admin/ajax.php`.
Die Ziehungsansicht zeigt beide Nummern: **Gezogen** und **Gewinnnummer**, mit
Hinweis bei Umbruch zu `000`. Das Audit (`draw_audits`) speichert beide
(`drawn_number` / `winning_number`), Teilnehmer, Modus/Provider und
`proof_hash`.

### Backups

Skript im Server-Cron einplanen:

```cron
0 3 * * * php /voller/pfad/zu/cron/backup.php
```

Jeder Lauf erzeugt `backup_JJJJ-MM-TT_HHMM.tar.gz` mit DB-Dump +
Projektdateien und löscht ältere jenseits von `MAX_BACKUPS`. Status im Admin (*Backups*) prüfbar.

> Pfade stehen in `includes/config.php`, `cron/backup.php` muss nicht bearbeitet werden.

### Struktur

```
├── index.php                # Öffentliche Seite
├── install.php              # Installer (nach Gebrauch löschen)
├── migrate.php              # Migration für bestehende DBs (nach Gebrauch löschen)
├── sql/
│   └── schema.sql           #   Komplettes Schema (9 Tabellen + Standardtexte)
├── admin/           # Admin-Panel
│   ├── index.php            #   Login
│   ├── dashboard.php        #   Hauptpanel (auch: Panel-Sprache)
│   ├── participants.php     #   Teilnehmer-Verwaltung
│   ├── draw.php             #   Live-Ziehung
│   ├── winners.php          #   Gewinner-Historie
│   ├── prizes.php           #   Preise
│   ├── blacklist.php        #   Blacklist
│   ├── news.php             #   Neuigkeiten
│   ├── texts.php            #   Anpassbare Texte
│   ├── sponsors.php         #   Sponsoren
│   ├── backups.php          #   Backup-Monitoring
│   ├── preview.php          #   Öffentliche Vorschau
│   ├── admins.php           #   Administratoren
│   ├── change_password.php  #   Passwort ändern
│   ├── reset.php            #   Komplettes Zurücksetzen (Gefahr)
│   └── ajax.php             #   AJAX-Endpunkt (Zufallsnummer)
├── includes/                # PHP-Kern
│   ├── config.php           #   DB-Verbindung, Konstanten, Backup-Pfade
│   ├── functions.php        #   Hilfsfunktionen
│   ├── auth.php             #   Authentifizierung und Brute-Force-Schutz
│   ├── lang.php             #   Mehrsprachensystem (Register + t())
│   └── lang/                #   Wörterbücher: en, es, de, pt, fr (+ _template.php)
├── cron/
│   └── backup.php           #   Backup-Skript (liest includes/config.php)
└── assets/
    ├── css/                 #   Styles (öffentlich + admin)
    ├── js/                  #   JavaScript (nur admin)
    └── img/sponsors/        #   Sponsoren-Bilder
```

### Sicherheit

- Standardzugang (`admin` / `admin2026`) nach der Installation ändern.
- **`install.php` und `migrate.php` danach löschen oder sperren**.
- `BACKUP_DIR` in Produktion außerhalb des öffentlichen Verzeichnisses legen.
- Passwörter mit `password_hash()` gespeichert; Login sperrt die IP
  nach 5 Fehlversuchen für 48 h.
- `.htaccess` verweigert direkten Zugriff auf `includes/`, `sql/` und `cron/`.

### Lizenz

MIT
