# Lottery System

🌍 Langue / Idioma / Language / Sprache / Idioma :
[🇪🇸 Español](README.md) · [🇬🇧 English](README.en.md) · [🇩🇪 Deutsch](README.de.md) · [🇧🇷 Português](README.pt.md) · **🇫🇷 Français**

Système de gestion de loterie développé en PHP, MySQL, CSS et JavaScript vanilla.
Sans dépendances, sans build, sans framework : téléverser, installer, utiliser.

Créé avec l'assistance de l'intelligence artificielle (IA).

### Fonctionnalités

- **Interface en 5 langues** (English par défaut, Español, Deutsch, Português, Français), extensible (voir [🌍 Langues](#-langues))
- **Page publique** avec grille de numéros de 000 à 999, actualités et textes personnalisables
- **Panneau d’administration** protégé par connexion avec session
- **Inscription des participants** avec validation automatique (noms en double, liste noire, numéros pris)
- **Attribution automatique** d’un numéro au hasard si le numéro choisi est déjà pris
- **Liste noire** pour exclure les participants indésirables
- **Gestion des actus, textes, lots, sponsors et administrateurs**
- **Tirage en direct** avec recherche de participants et désignation manuelle (règle circulaire : le plus proche au-dessus, retour à `000`)
- **Sauvegardes automatiques** (BD + fichiers) via cron, avec page de suivi dans l’admin
- **Responsive** et compatible avec les anciens navigateurs (sans JavaScript côté public)
- **Multi-admin** avec authentification par session et protection anti force brute

### 🌍 Langues

L’interface (page publique + panneau admin) est disponible en 5 langues :

| Code | Langue | Fichier |
|------|--------|---------|
| `en` | English (défaut) | `includes/lang/en.php` |
| `es` | Español | `includes/lang/es.php` |
| `de` | Deutsch | `includes/lang/de.php` |
| `pt` | Português | `includes/lang/pt.php` |
| `fr` | Français | `includes/lang/fr.php` |

- **Page publique** : sélecteur de langue visible (drapeaux) + `?lang=fr` + détection du navigateur. Stocké en session et cookie (1 an).
- **Panneau admin** : le tableau de bord permet de changer la **langue par défaut du panneau** (globale, stockée en BD comme `custom_texts.admin_lang_default`, vaut pour tous les admins) et **votre langue personnelle** (votre session/navigateur uniquement, depuis le tableau de bord ou la barre latérale).
- Seule l’**interface** est traduite. Le contenu créé par l’admin (actus, textes personnalisés, noms des lots) s’affiche tel qu’il a été saisi.

#### Ajouter une langue (2 minutes)

```bash
cp includes/lang/en.php includes/lang/it.php   # ou copier includes/lang/_template.php
```

1. Traduisez les **valeurs** dans `includes/lang/it.php` (gardez les clés et les `{placeholders}` intacts).
2. Enregistrez la langue dans `includes/lang.php` (une ligne) :
```php
'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
```
3. Vérifiez qu’aucune clé ne manque et testez dans le navigateur :
```bash
php -r '$en=require"includes/lang/en.php";$xx=require"includes/lang/it.php";$m=array_diff_key($en,$xx);$e=array_diff_key($xx,$en);echo"manquantes: ".count($m).", en trop: ".count($e).PHP_EOL;'
# http://votre-serveur/?lang=it  (+ connexion, tableau de bord, participants, tirage)
```

Rien d’autre à toucher : sélecteurs, validation et replis (clé manquante → anglais) utilisent le registre automatiquement.

#### Contribuer une langue (Pull Request)

Vous parlez une autre langue ? Ajoutez-la avec une PR et nous l’inclurons !

1. Forkez et créez une branche `lang-xx` (p. ex. `lang-it`).
2. Dans votre branche, touchez **uniquement** ces fichiers :
   - `includes/lang/xx.php` (nouveau, copié de `en.php` et traduit),
   - `includes/lang.php` (une ligne dans `SUPPORTED_LANGS`),
   - optionnel : `README.xx.md` avec la traduction de ce README.
3. Checklist de la PR :
   - [ ] Toutes les clés de `en.php` existent (la commande ci-dessus dit `manquantes: 0, en trop: 0`).
   - [ ] Les `{placeholders}` (`{name}`, `{min}`, `{pos}`…) sont intacts.
   - [ ] Testé avec `?lang=xx` (page publique + connexion + tableau de bord).
4. Ouvrez la PR vers `main` intitulée `Add xx language (Italiano)` avec une capture de la page publique dans votre langue. Les PRs de langues sont revues en continu ; nous vous citerons dans les notes de version. Merci ! 🙏

### Prérequis

- Apache avec mod_rewrite (ou équivalent Nginx)
- PHP 7.4 ou supérieur (extension MySQLi)
- MySQL 5.6 / MariaDB 10.x ou supérieur
- `mysqldump` ou `mariadb-dump` sur le serveur (sauvegardes uniquement)

### Installation

```bash
git clone https://github.com/leuros88/lottery_manual.git
cd lottery_manual
```

#### Option A — Installateur automatique (recommandé)

1. Allez à `http://votre-serveur/install.php`, remplissez les identifiants
   MySQL (pré-remplis depuis `includes/config.php`) et cliquez
   **« Run Installation »**.
   L’installateur crée la base, importe `sql/schema.sql`, crée l’utilisateur
   admin indiqué (défaut `admin` / `admin2026`)
   et enregistre les identifiants dans `includes/config.php` automatiquement
   (si le fichier n’est pas inscriptible, il vous dira de
   l’éditer à la main).
3. Allez à `http://votre-serveur/admin/` avec cet utilisateur (ou votre dossier renommé si `ADMIN_DIR` est défini).
4. **Changez le mot de passe** dès la connexion (menu *Change Password*).
5. **Supprimez `install.php`** ou décommentez la règle `install.php` dans
   `.htaccess` pour le bloquer.

#### Option B — Installation manuelle

Sans l’installateur :

```bash
mysql -u VOTRE_UTILISATEUR -p < sql/schema.sql
```

Ou importez `sql/schema.sql` depuis phpMyAdmin (onglet *Importer*).
Le schéma inclut déjà un admin de test (`admin` / `admin2026`) :
connectez-vous avec et **changez le mot de passe** aussitôt
(menu *Change Password* du panneau).

Puis éditez `includes/config.php` avec vos identifiants.

### Configuration (`includes/config.php`)

Tout ce qui est réglable tient dans un seul fichier :

| Constante       | Description |
|----------------|-------------|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | Connexion MySQL |
| `SITE_NAME`    | Nom affiché sur le site |
| `PROJECT_ROOT` | Racine du projet (autodétectée, ne pas toucher en général) |
| `BACKUP_DIR`   | Destination des sauvegardes. Défaut `cron/backups/` (dans le projet). **En production, pointez hors de `public_html`**, p. ex. `/home/user/private/backups` |
| `MAX_BACKUPS`  | Nbre max de sauvegardes (défaut 10, rotation auto) |
| `MYSQLDUMP_BIN`| Chemin du binaire de dump (`/usr/bin/mariadb-dump` ou sortie de `which mysqldump`) |
| `ADMIN_DIR`    | Nom du dossier admin (défaut `admin`). Changez-le pour masquer le panneau (ci-dessous) |

### Masquer le panneau : renommer le dossier admin

Le chemin `/admin/` est public par défaut. Comme couche de sécurité
supplémentaire (ne remplace pas un mot de passe fort), renommez-le, p. ex. `panel-x7k9q2` :

```bash
mv admin panel-x7k9q2
```

et mettez `includes/config.php` à jour :

```php
define('ADMIN_DIR', 'panel-x7k9q2');
```

Toutes les URLs, redirections et le bouton de l’installateur utilisent `ADMIN_DIR`
(via `adminUrl()` dans `includes/auth.php`), rien d’autre à toucher.
Le JS admin utilise un chemin relatif (`ajax.php?...`), insensible aussi.
Allez ensuite à `http://votre-serveur/panel-x7k9q2/` ; l’ancien chemin n’existe plus (404).

> Lettres, chiffres, tirets et underscores uniquement, sans barres.
> Après renommage, supprimez quand même `install.php` et `migrate.php`.

### Panneau d’administration (`/admin/` par défaut, renommable via `ADMIN_DIR`)

| Page | Usage |
|--------|-----|
| `dashboard.php` | Résumé et accès rapides (aussi : langue du panneau) |
| `participants.php` | Ajout/édition des participants (jusqu’à 3 numéros par personne) |
| `draw.php` | Tirage en direct : chercher un numéro et désigner le gagnant |
| `winners.php` | Historique des gagnants |
| `prizes.php` | Lots et positions |
| `blacklist.php` | Noms bloqués |
| `news.php` | Actus de la page publique |
| `texts.php` | Textes personnalisables |
| `sponsors.php` | Carrousel de sponsors |
| `backups.php` | Suivi des sauvegardes |
| `preview.php` | Aperçu public |
| `admins.php` | Gestion des administrateurs |
| `change_password.php` | Changement de son mot de passe |
| `reset.php` | Réinitialisation complète (zone dangereuse) |

### Inscription des participants — attribution auto

Si à l’inscription le numéro choisi est déjà pris, le système **ne renvoie pas
d’erreur** : il attribue automatiquement **un autre numéro libre au hasard** et
prévient dans un modal (`demandé → attribué`). Vaut à la création comme à
l’édition ; n’échoue que s’il ne reste aucun numéro libre.

Le message de succès est **configurable** et pensé pour être **envoyé à
l’utilisateur** (p. ex. WhatsApp) : le modal a un bouton **📋 Copier** qui copie
le texte déjà rempli. Textes modifiables dans l’admin (*Custom Texts* → `texts.php`) :

| Clé | Variables | Quand utilisé |
|-------|-----------|---------------|
| `participant_create_success` | `{name}`, `{numbers}` | Inscription |
| `participant_update_success` | `{name}`, `{numbers}` | Édition |
| `participant_duplicate` | `{name}` | Nom déjà inscrit |
| `participant_blacklisted` | `{name}` | Nom en liste noire |
| `participant_error` | `{reason}` | Autres erreurs |

### Tirage en direct — règle du gagnant (circulaire)

Les gagnants **n’ont pas besoin d’une correspondance exacte**. Le système
tire/saisit un numéro `drawn` (000–999) et c’est le **numéro libre le plus proche
au-dessus** qui gagne, avec retour à `000` s’il n’y en a aucun (distance
circulaire `(candidat - tiré + 1000) % 1000` minimale).

- Chaque participant peut avoir jusqu’à 3 numéros ; les trois comptent.
- Si le numéro tiré est libre, il gagne directement (distance 0).
- Erreur uniquement s’il ne reste aucun participant disponible.

#### Gagnant unique ou répété (tableau de bord → Winner Rules)

Par défaut **la répétition est autorisée** : le même participant peut gagner
plusieurs lots (`custom_texts.unique_winners = '0'`). Avec **Gagnant unique**
(`'1'`), chacun ne peut gagner qu’un lot et les gagnants sont exclus des
tirages suivants.

#### Installations existantes : migrate.php

Si la BD existait déjà, connectez-vous à l’admin et ouvrez `/migrate.php` dans
le navigateur : crée `draw_audits` si manquante et insère `draw_mode`
(`manual`), `unique_winners` (`0`), `site_title` et `admin_lang_default`
(`en`) avec `INSERT IGNORE` (idempotent). **Supprimez-le ensuite**,
comme `install.php`.

Exemples :

| Tiré | Numéros libres occupés | Gagnant | Motif |
|----------|-------------------------|---------|--------|
| `200` | `199`, `205`, `206` | `205` (utilisateur b) | Le plus proche au-dessus de `200` |
| `205` | `199`, `205`, `206` | `205` | Correspondance exacte |
| `998` | `005`, `150` | `005` | Rien `>= 998`, retour circulaire à `000` |

La règle vaut pour les 3 modes et se trouve dans `findClosestParticipant()`
(`includes/functions.php`), utilisée par `draw.php` et `ajax.php`. L’écran
affiche les deux numéros : **Drawn** (tiré) et **Winning number**, avec un avis
en cas de retour à `000`. L’audit (`draw_audits`) stocke les deux, le
participant, le mode/fournisseur et un `proof_hash`.

### Sauvegardes

Planifiez le script dans le cron du serveur :

```cron
0 3 * * * php /chemin/complet/vers/cron/backup.php
```

Chaque exécution génère `backup_AAAA-MM-JJ_HHMM.tar.gz` (dump BD + fichiers) et
supprime les plus anciennes au-delà de `MAX_BACKUPS`. État visible dans l’admin (*Backups*).

> Chemins configurés dans `includes/config.php`, sans éditer `cron/backup.php`.

### Structure

```
├── index.php                # Page publique
├── install.php              # Installateur (supprimer après usage)
├── migrate.php              # Migration pour BD existantes (supprimer après usage)
├── sql/
│   └── schema.sql           #   Schéma complet (9 tables + textes par défaut)
├── admin/           # Panneau d’administration
│   ├── index.php            #   Connexion
│   ├── dashboard.php        #   Panneau principal (aussi : langue)
│   ├── participants.php     #   Gestion des participants
│   ├── draw.php             #   Tirage en direct
│   ├── winners.php          #   Historique des gagnants
│   ├── prizes.php           #   Lots
│   ├── blacklist.php        #   Liste noire
│   ├── news.php             #   Actus
│   ├── texts.php            #   Textes personnalisables
│   ├── sponsors.php         #   Sponsors
│   ├── backups.php          #   Suivi des sauvegardes
│   ├── preview.php          #   Aperçu public
│   ├── admins.php           #   Administrateurs
│   ├── change_password.php  #   Changer le mot de passe
│   ├── reset.php            #   Réinitialisation complète (danger)
│   └── ajax.php             #   Endpoint AJAX
├── includes/                # Cœur PHP
│   ├── config.php           #   Connexion BD, constantes, sauvegardes
│   ├── functions.php        #   Fonctions utilitaires
│   ├── auth.php             #   Authentification et anti force brute
│   ├── lang.php             #   Système multilingue (registre + t())
│   └── lang/                #   Dictionnaires : en, es, de, pt, fr (+ _template.php)
├── cron/
│   └── backup.php           #   Script de sauvegarde (lit includes/config.php)
└── assets/
    ├── css/                 #   Styles (public + admin)
    ├── js/                  #   JavaScript (admin uniquement)
    └── img/sponsors/        #   Images des sponsors
```

### Sécurité

- Changez les identifiants par défaut (`admin` / `admin2026`) après installation.
- **Supprimez ou bloquez `install.php` et `migrate.php`** après usage.
- Pointez `BACKUP_DIR` hors du répertoire public en production.
- Mots de passe stockés avec `password_hash()` ; la connexion bloque l’IP
  après 5 échecs pendant 48 h.
- `.htaccess` refuse l’accès direct à `includes/`, `sql/` et `cron/`.

### Licence

GPL-3.0 — voir le fichier `LICENSE`.
