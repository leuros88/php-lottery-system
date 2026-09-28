# Lottery System

🌍 Idioma / Language / Sprache / Idioma / Langue:
**🇪🇸 Español** · [🇬🇧 English](README.en.md) · [🇩🇪 Deutsch](README.de.md) · [🇧🇷 Português](README.pt.md) · [🇫🇷 Français](README.fr.md)

Sistema de gestión de lotería desarrollado con PHP, MySQL, CSS y JavaScript vanilla.
Sin dependencias, sin build, sin framework: subir, instalar y usar.

### Características

- **Interfaz en 5 idiomas** (English por defecto, Español, Deutsch, Português, Français), extensible a más (ver [🌍 Idiomas](#-idiomas))
- **Página pública** con cuadrícula de números del 000 al 999, noticias y textos personalizables
- **Panel de administración** protegido por login con sesión
- **Registro de participantes** con validación automática (nombres duplicados, blacklist, números ocupados)
- **Auto-asignación** de número aleatorio si el número elegido ya está ocupado
- **Blacklist** para excluir participantes no deseados
- **Gestión de noticias, textos, premios, sponsors y administradores**
- **Sorteo en directo** con búsqueda de participantes y selección manual de ganadores (regla circular: superior más cercano, con vuelta a `000`)
- **Copias de seguridad** automáticas (BD + archivos) vía cron, con página de monitoreo en el admin
- **Responsive** y compatible con navegadores antiguos (sin JavaScript en la página pública)
- **Multi-admin** con autenticación por sesión y protección contra fuerza bruta

### 🌍 Idiomas

La interfaz (página pública + panel admin) está disponible en 5 idiomas:

| Código | Idioma | Fichero |
|--------|--------|---------|
| `en` | English (por defecto) | `includes/lang/en.php` |
| `es` | Español | `includes/lang/es.php` |
| `de` | Deutsch | `includes/lang/de.php` |
| `pt` | Português | `includes/lang/pt.php` |
| `fr` | Français | `includes/lang/fr.php` |

- **Página pública**: selector de idioma visible (banderas) + `?lang=es` + detección del navegador. Se guarda en sesión y cookie (1 año).
- **Panel admin**: el dashboard permite cambiar el **idioma por defecto del panel** (global, guardado en BD como `custom_texts.admin_lang_default`, afecta a todos los admins) y **tu idioma personal** (solo tu sesión/navegador, desde el dashboard o el sidebar).
- Solo se traduce la **interfaz**. El contenido creado por el admin (noticias, textos personalizados, nombres de premios) se muestra tal cual se escribe.

#### Añadir un idioma nuevo (2 minutos)

```bash
cp includes/lang/en.php includes/lang/it.php   # o copia includes/lang/_template.php
```

1. Traduce los **valores** en `includes/lang/it.php` (mantén las claves y los `{placeholders}` intactos).
2. Registra el idioma en `includes/lang.php` (una línea):
```php
'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
```
3. Comprueba que no falte ninguna clave y pruébalo en el navegador:
```bash
php -r '$en=require"includes/lang/en.php";$xx=require"includes/lang/it.php";$m=array_diff_key($en,$xx);$e=array_diff_key($xx,$en);echo"faltan: ".count($m).", sobran: ".count($e).PHP_EOL;'
# http://tu-servidor/?lang=it  (+ login, dashboard, participantes, sorteo)
```

No hay que tocar nada más: selectores, validación y fallbacks (clave ausente → inglés) iteran el registro automáticamente.

#### Contribuir con un idioma (Pull Request)

¿Hablas otro idioma? ¡Añádelo con un PR y lo incluiremos!

1. Haz un fork y crea una rama `lang-xx` (p. ej. `lang-it`).
2. En tu rama, toca **solo** estos ficheros:
   - `includes/lang/xx.php` (nuevo, copiado de `en.php` y traducido),
   - `includes/lang.php` (una línea en `SUPPORTED_LANGS`),
   - opcional: `README.xx.md` con la traducción de este README.
3. Checklist del PR:
   - [ ] Todas las claves de `en.php` existen (el comando `php -r` de arriba dice `faltan: 0, sobran: 0`).
   - [ ] Los `{placeholders}` (`{name}`, `{min}`, `{pos}`…) están intactos.
   - [ ] Probado con `?lang=xx` en la página pública + login + dashboard.
4. Abre el PR contra `main` con título `Add xx language (Italiano)` y una captura de la página pública en tu idioma. Los PRs de idiomas se revisan de forma continua; te mencionaremos en las notas de la versión. ¡Gracias! 🙏

### Requisitos

- Apache con mod_rewrite (o Nginx equivalente)
- PHP 7.4 o superior (extensión MySQLi)
- MySQL 5.6 / MariaDB 10.x o superior
- `mysqldump` o `mariadb-dump` en el servidor (solo para los backups)

### Instalación

```bash
git clone https://github.com/leuros88/lottery_manual.git
cd lottery_manual
```

#### Opción A — Instalador automático (recomendado)

1. Accede a `http://tu-servidor/install.php`, rellena las credenciales
   MySQL (vienen precargadas desde `includes/config.php`) y pulsa
   **"Run Installation"**.
   El instalador crea la base de datos, importa `sql/schema.sql`, crea el
   usuario administrador que indiques (por defecto `admin` / `admin2026`)
   y guarda las credenciales en `includes/config.php` automáticamente
   (si el fichero no tiene permiso de escritura, te avisará para
   editarlo a mano).
3. Accede a `http://tu-servidor/admin/` con ese usuario (o a tu carpeta renombrada si ya aplicaste `ADMIN_DIR`).
4. **Cambia la contraseña** nada más entrar (menú *Change Password*).
5. **Elimina `install.php`** o descomenta la regla `install.php` en
   `.htaccess` para bloquearlo.

#### Opción B — Instalación manual

Si prefieres no usar el instalador:

```bash
mysql -u TU_USUARIO -p < sql/schema.sql
```

O importa `sql/schema.sql` desde phpMyAdmin (pestaña *Importar*).
El esquema ya incluye un usuario administrador de prueba
(`admin` / `admin2026`): entra con él y **cambia la contraseña**
nada más acceder (menú *Change Password* del panel admin).

Después edita `includes/config.php` con tus credenciales.

### Configuración (`includes/config.php`)

Todo lo ajustable por el usuario vive en un único fichero:

| Constante       | Descripción |
|----------------|-------------|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | Conexión MySQL |
| `SITE_NAME`    | Nombre mostrado en la web |
| `PROJECT_ROOT` | Raíz del proyecto (autodetectada, normalmente no tocar) |
| `BACKUP_DIR`   | Dónde se guardan las copias. Por defecto `cron/backups/` (dentro del proyecto). **En producción apúntalo fuera de `public_html`**, p. ej. `/home/usuario/private/backups` |
| `MAX_BACKUPS`  | Nº máximo de copias a conservar (por defecto 10, rotación automática) |
| `MYSQLDUMP_BIN`| Ruta al binario de volcado (`/usr/bin/mariadb-dump` o la salida de `which mysqldump`) |
| `ADMIN_DIR`    | Nombre de la carpeta del panel admin (por defecto `admin`). Cámbiala para ocultar el panel (ver abajo) |

### Ocultar el panel: renombrar la carpeta admin

La ruta `/admin/` es pública por defecto. Como capa extra de
seguridad (no sustituye a una contraseña fuerte), renómbrala a algo
impredecible, p. ej. `panel-x7k9q2`:

```bash
mv admin panel-x7k9q2
```

y actualiza `includes/config.php` para que coincida:

```php
define('ADMIN_DIR', 'panel-x7k9q2');
```

Todas las URLs, redirecciones y el botón del instalador usan `ADMIN_DIR`
(vía `adminUrl()` en `includes/auth.php`), así que no hay que tocar nada
más. El JS del admin usa una ruta relativa (`ajax.php?...`), también
inmune al renombrado. Entra después en
`http://tu-servidor/panel-x7k9q2/`; la ruta antigua deja de existir
(error 404).

> Solo letras, números, guiones y guiones bajos, sin barras.
> Tras renombrar, borra `install.php` y `migrate.php` igualmente.

### Panel de administración (`/admin/` por defecto, renombrable vía `ADMIN_DIR`)

| Página | Uso |
|--------|-----|
| `dashboard.php` | Resumen y accesos rápidos |
| `participants.php` | Alta/edición de participantes (hasta 3 números por persona) |
| `draw.php` | Sorteo en directo: buscar número y asignar ganador al premio |
| `winners.php` | Historial de ganadores |
| `prizes.php` | Premios y posiciones |
| `blacklist.php` | Nombres bloqueados |
| `news.php` | Noticias de la página pública |
| `texts.php` | Textos personalizables (cabecera, pie, mensajes de estado, etc.) |
| `sponsors.php` | Carrusel de patrocinadores |
| `backups.php` | Monitoreo de copias (lee `BACKUP_DIR` de la config) |
| `preview.php` | Vista previa de la página pública |
| `admins.php` | Gestión de administradores |
| `change_password.php` | Cambio de contraseña propia |
| `reset.php` | Reseteo completo de la lotería (zona de peligro) |

### Alta de participantes — auto-asignación de número

Si al registrar un participante el número elegido ya está ocupado por otro
usuario, el sistema **no da error**: le asigna automáticamente **otro número
libre al azar** y te avisa en un modal con el detalle
`solicitado → asignado`. Vale tanto al crear como al editar (cada número
ocupado se sustituye por uno libre distinto); solo falla si no queda ningún
número libre.

El mensaje de éxito es **configurable** y está pensado para **enviárselo al
usuario** (p. ej. por WhatsApp): el modal trae botón **📋 Copy** que copia el
texto ya relleno con su nombre y sus números finales. Los textos se editan en
el admin (*Custom Texts* → `texts.php`):

| Clave | Variables | Cuándo se usa |
|-------|-----------|---------------|
| `participant_create_success` | `{name}`, `{numbers}` | Alta de participante |
| `participant_update_success` | `{name}`, `{numbers}` | Edición de participante |
| `participant_duplicate` | `{name}` | El nombre ya está registrado |
| `participant_blacklisted` | `{name}` | El nombre está en la blacklist |
| `participant_error` | `{reason}` | Otros errores |

### Sorteo en directo — regla del ganador (circular)

Los ganadores **no tienen que coincidir con un número exacto**. El sistema
sortea/introduce un número `drawn` (000–999) y gana el **número de
participante libre más cercano por arriba**, dando la vuelta a `000` si no
hay ninguno igual o superior (distancia circular
`(candidato - sorteado + 1000) % 1000` mínima).

- Cada participante puede tener hasta 3 números (`number`, `number2`,
  `number3`); los tres entran en el cálculo.
- Si el número sorteado está libre, gana directamente (distancia 0).
- Solo hay error cuando no queda ningún participante disponible.

#### Ganador único o repetido (dashboard → Winner Rules)

Por defecto **se permite repetir**: el mismo participante puede ganar varios
premios (`custom_texts.unique_winners = '0'`). Si activas **Unique winner**
(`'1'`), cada participante solo puede ganar un premio y los ganadores quedan
excluidos de los siguientes sorteos.

- Con pocos participantes y modo único activo, tras asignar 1 ganador el
  siguiente premio muestra un mensaje explicativo (todos ya ganaron / no hay
  participantes). Con modo repetir activo no ocurre: el sorteo siempre
  encuentra candidato mientras haya participantes registrados.
- El ajuste vive en el dashboard (`dashboard.php` → Winner Rules) y lo aplican
  `findClosestParticipant()`, `draw.php` y `ajax.php`.

#### Instalaciones existentes: migrate.php

Si la BD ya estaba creada antes de este cambio, entra al admin y abre
`/migrate.php` en el navegador: crea `draw_audits` si falta e inserta
`draw_mode` (`manual`), `unique_winners` (`0`, repetir permitido),
`site_title` y `admin_lang_default` (`en`, idioma del panel) con
`INSERT IGNORE` (idempotente, se puede re-ejecutar). **Bórralo después**,
igual que `install.php`.

Ejemplos:

| Sorteado | Números ocupados libres | Ganador | Motivo |
|----------|-------------------------|---------|--------|
| `200` | `199`, `205`, `206` | `205` (usuario b) | Superior más cercano a `200` |
| `205` | `199`, `205`, `206` | `205` | Coincidencia exacta |
| `998` | `005`, `150` | `005` | No hay nada `>= 998`, vuelta circular a `000` |

La regla vale para los 3 modos (`Manual`, `Random.org`, `Local random`) y
está implementada en `findClosestParticipant()` (`includes/functions.php`),
usada por `admin/draw.php` y `admin/ajax.php`
(`verifiable_random`). En la pantalla de sorteo se muestran los dos números:
**Drawn** (sorteado) y **Winning number** (el que realmente gana), con un
aviso cuando hubo vuelta a `000`. La auditoría (`draw_audits`) guarda ambos
(`drawn_number` / `winning_number`), el participante, el modo/proveedor y un
`proof_hash`.

### Copias de seguridad

Programa el script en el cron del servidor:

```cron
0 3 * * * php /ruta/completa/hacia/cron/backup.php
```

Cada ejecución genera un `backup_AAAA-MM-DD_HHMM.tar.gz` con el volcado de
la BD + los archivos del proyecto, y borra las más antiguas superando
`MAX_BACKUPS`. El estado puede consultarse en el admin (*Backups*).

> Las rutas se configuran en `includes/config.php`, no hay que editar
> `cron/backup.php`.

### Estructura

```
├── index.php                # Página pública
├── install.php              # Instalador (eliminar tras usar)
├── migrate.php              # Migración para BD ya instaladas (eliminar tras usar)
├── sql/
│   └── schema.sql           #   Esquema completo (9 tablas + textos por defecto)
├── admin/           # Panel de administración
│   ├── index.php            #   Login
│   ├── dashboard.php        #   Panel principal
│   ├── participants.php     #   Gestión de participantes
│   ├── draw.php             #   Sorteo en directo
│   ├── winners.php          #   Historial de ganadores
│   ├── prizes.php           #   Premios
│   ├── blacklist.php        #   Lista negra
│   ├── news.php             #   Noticias
│   ├── texts.php            #   Textos personalizables
│   ├── sponsors.php         #   Patrocinadores
│   ├── backups.php          #   Monitoreo de copias
│   ├── preview.php          #   Vista previa pública
│   ├── admins.php           #   Administradores
│   ├── change_password.php  #   Cambio de contraseña
│   ├── reset.php            #   Reseteo completo (peligro)
│   └── ajax.php             #   Endpoint AJAX (número aleatorio)
├── includes/                # Núcleo PHP
│   ├── config.php           #   Conexión BD, constantes y rutas de backup
│   ├── functions.php        #   Funciones auxiliares
│   ├── auth.php             #   Autenticación y protección brute-force
│   ├── lang.php             #   Sistema multidioma (registro + t())
│   └── lang/                #   Diccionarios: en, es, de, pt, fr (+ _template.php)
├── cron/
│   └── backup.php           #   Script de backup (lee includes/config.php)
└── assets/
    ├── css/                 #   Estilos (público + admin)
    ├── js/                  #   JavaScript (solo admin)
    └── img/sponsors/        #   Imágenes de patrocinadores
```

### Seguridad

- Cambia las credenciales por defecto (`admin` / `admin2026`) tras instalar.
- **Elimina o bloquea `install.php` y `migrate.php`** después de usarlos.
- Apunta `BACKUP_DIR` fuera del directorio público en producción.
- Las contraseñas se guardan con `password_hash()`; el login bloquea la IP
  tras 5 intentos fallidos durante 48 h.
- `.htaccess` deniega el acceso directo a `includes/`, `sql/` y `cron/`.

### Licencia

MIT
