# Lottery System

🌍 Idioma / Language / Sprache / **Idioma** / Langue:
[🇪🇸 Español](README.md) · [🇬🇧 English](README.en.md) · [🇩🇪 Deutsch](README.de.md) · **🇧🇷 Português** · [🇫🇷 Français](README.fr.md)

Sistema de gestão de lotaria desenvolvido com PHP, MySQL, CSS e JavaScript vanilla.
Sem dependências, sem build, sem framework: carregar, instalar e usar.

Criado com assistência de inteligência artificial (IA).

### Características

- **Interface em 5 idiomas** (English por defeito, Español, Deutsch, Português, Français), extensível (ver [🌍 Idiomas](#-idiomas))
- **Página pública** com grelha de números de 000 a 999, notícias e textos personalizáveis
- **Painel de administração** protegido por login com sessão
- **Registo de participantes** com validação automática (nomes duplicados, lista negra, números ocupados)
- **Atribuição automática** de um número aleatório se o número escolhido já estiver ocupado
- **Lista negra** para excluir participantes indesejados
- **Gestão de notícias, textos, prémios, patrocinadores e administradores**
- **Sorteio ao vivo** com pesquisa de participantes e seleção manual de vencedores (regra circular: mais próximo por cima, com volta a `000`)
- **Cópias de segurança** automáticas (BD + ficheiros) via cron, com página de monitorização no admin
- **Responsivo** e compatível com navegadores antigos (sem JavaScript na página pública)
- **Multi-admin** com autenticação por sessão e proteção contra força bruta

### 🌍 Idiomas

A interface (página pública + painel) está disponível em 5 idiomas:

| Código | Idioma | Ficheiro |
|--------|--------|----------|
| `en` | English (predefinido) | `includes/lang/en.php` |
| `es` | Español | `includes/lang/es.php` |
| `de` | Deutsch | `includes/lang/de.php` |
| `pt` | Português | `includes/lang/pt.php` |
| `fr` | Français | `includes/lang/fr.php` |

- **Página pública**: seletor de idioma visível (bandeiras) + `?lang=pt` + deteção do navegador. Guardado em sessão e cookie (1 ano).
- **Painel admin**: o painel permite alterar o **idioma predefinido do painel** (global, guardado na BD como `custom_texts.admin_lang_default`, afeta todos os admins) e **o seu idioma pessoal** (só a sua sessão/navegador, no painel ou na barra lateral).
- Só a **interface** é traduzida. O conteúdo criado pelo admin (notícias, textos personalizados, nomes de prémios) aparece tal como foi escrito.

#### Adicionar um idioma novo (2 minutos)

```bash
cp includes/lang/en.php includes/lang/it.php   # ou copia includes/lang/_template.php
```

1. Traduz os **valores** em `includes/lang/it.php` (mantém as chaves e os `{placeholders}` intactos).
2. Regista o idioma em `includes/lang.php` (uma linha):
```php
'it' => ['label' => 'Italiano', 'flag' => '🇮🇹'],
```
3. Verifica que não falta nenhuma chave e testa no navegador:
```bash
php -r '$en=require"includes/lang/en.php";$xx=require"includes/lang/it.php";$m=array_diff_key($en,$xx);$e=array_diff_key($xx,$en);echo"faltam: ".count($m).", sobram: ".count($e).PHP_EOL;'
# http://teu-servidor/?lang=it  (+ login, painel, participantes, sorteio)
```

Nada mais a tocar: seletores, validação e fallbacks (chave em falta → inglês) usam o registo automaticamente.

#### Contribuir com um idioma (Pull Request)

Falas outro idioma? Adiciona-o com um PR e incluí-lo-emos!

1. Faz um fork e cria um ramo `lang-xx` (p. ex. `lang-it`).
2. No teu ramo, toca **só** nestes ficheiros:
   - `includes/lang/xx.php` (novo, copiado de `en.php` e traduzido),
   - `includes/lang.php` (uma linha em `SUPPORTED_LANGS`),
   - opcional: `README.xx.md` com a tradução deste README.
3. Checklist do PR:
   - [ ] Todas as chaves de `en.php` existem (o comando acima diz `faltam: 0, sobram: 0`).
   - [ ] Os `{placeholders}` (`{name}`, `{min}`, `{pos}`…) estão intactos.
   - [ ] Testado com `?lang=xx` na página pública + login + painel.
4. Abre o PR contra `main` com o título `Add xx language (Italiano)` e uma captura da página pública no teu idioma. Os PRs de idiomas são revistos continuamente; mencionar-te-emos nas notas de versão. Obrigado! 🙏

### Requisitos

- Apache com mod_rewrite (ou equivalente Nginx)
- PHP 7.4 ou superior (extensão MySQLi)
- MySQL 5.6 / MariaDB 10.x ou superior
- `mysqldump` ou `mariadb-dump` no servidor (só para as cópias)

### Instalação

```bash
git clone https://github.com/leuros88/lottery_manual.git
cd lottery_manual
```

#### Opção A — Instalador automático (recomendado)

1. Acede a `http://teu-servidor/install.php`, preenche as credenciais
   MySQL (pré-carregadas de `includes/config.php`) e prime
   **"Run Installation"**.
   O instalador cria a base de dados, importa `sql/schema.sql`, cria o
   utilizador administrador indicado (por defeito `admin` / `admin2026`)
   e guarda as credenciais em `includes/config.php` automaticamente
   (se o ficheiro não tiver permissão de escrita, avisar-te-á para
   o editar à mão).
3. Acede a `http://teu-servidor/admin/` com esse utilizador (ou à tua pasta renomeada se já aplicaste `ADMIN_DIR`).
4. **Altera a palavra-passe** logo após entrar (menu *Change Password*).
5. **Elimina o `install.php`** ou descomenta a regra `install.php` em
   `.htaccess` para o bloquear.

#### Opção B — Instalação manual

Se preferires não usar o instalador:

```bash
mysql -u TEU_UTILIZADOR -p < sql/schema.sql
```

Ou importa `sql/schema.sql` do phpMyAdmin (separador *Importar*).
O esquema já inclui um utilizador administrador de teste
(`admin` / `admin2026`): entra com ele e **altera a palavra-passe**
de imediato (menu *Change Password* do painel).

Depois edita `includes/config.php` com as tuas credenciais.

### Configuração (`includes/config.php`)

Tudo o que é ajustável vive num único ficheiro:

| Constante       | Descrição |
|----------------|-------------|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | Ligação MySQL |
| `SITE_NAME`    | Nome mostrado no site |
| `PROJECT_ROOT` | Raiz do projeto (autodetetada, normalmente não tocar) |
| `BACKUP_DIR`   | Onde se guardam as cópias. Por defeito `cron/backups/` (dentro do projeto). **Em produção aponta para fora de `public_html`**, p. ex. `/home/utilizador/private/backups` |
| `MAX_BACKUPS`  | N.º máximo de cópias a conservar (por defeito 10, rotação automática) |
| `MYSQLDUMP_BIN`| Caminho para o binário de dump (`/usr/bin/mariadb-dump` ou a saída de `which mysqldump`) |
| `ADMIN_DIR`    | Nome da pasta do painel (por defeito `admin`). Altera-o para ocultar o painel (ver abaixo) |

### Ocultar o painel: renomear a pasta admin

O caminho `/admin/` é público por defeito. Como camada extra de
segurança (não substitui uma palavra-passe forte), renomeia-o para algo
imprevisível, p. ex. `panel-x7k9q2`:

```bash
mv admin panel-x7k9q2
```

e atualiza `includes/config.php`:

```php
define('ADMIN_DIR', 'panel-x7k9q2');
```

Todos os URLs, redirecionamentos e o botão do instalador usam `ADMIN_DIR`
(via `adminUrl()` em `includes/auth.php`), por isso nada mais precisa de ser tocado.
O JS do admin usa um caminho relativo (`ajax.php?...`), também
imune. Depois entra em `http://teu-servidor/panel-x7k9q2/`; o caminho antigo deixa de existir (404).

> Só letras, números, hífenes e underscores, sem barras.
> Após renomear, apaga `install.php` na mesma.

### Painel de administração (`/admin/` por defeito, renomeável via `ADMIN_DIR`)

| Página | Uso |
|--------|-----|
| `dashboard.php` | Resumo e acessos rápidos (também: idioma do painel) |
| `participants.php` | Adicionar/editar participantes (até 3 números por pessoa) |
| `draw.php` | Sorteio ao vivo: procurar número e atribuir vencedor |
| `winners.php` | Histórico de vencedores |
| `prizes.php` | Prémios e posições |
| `blacklist.php` | Nomes bloqueados |
| `news.php` | Notícias da página pública |
| `texts.php` | Textos personalizáveis |
| `sponsors.php` | Carrossel de patrocinadores |
| `backups.php` | Monitorização de cópias |
| `preview.php` | Pré-visualização pública |
| `admins.php` | Gestão de administradores |
| `change_password.php` | Alteração da própria palavra-passe |
| `reset.php` | Reinício completo (zona de perigo) |

### Registo de participantes — atribuição automática

Se ao registar um participante o número escolhido já estiver ocupado, o sistema
**não dá erro**: atribui automaticamente **outro número livre ao acaso** e
avisa num modal com o detalhe `pedido → atribuído`. Vale ao criar e ao editar;
só falha se não restar nenhum número livre.

A mensagem de sucesso é **configurável** e pensada para **enviar ao utilizador**
(p. ex. por WhatsApp): o modal traz o botão **📋 Copy** que copia o texto já
preenchido. Os textos editam-se no admin (*Custom Texts* → `texts.php`):

| Chave | Variáveis | Quando se usa |
|-------|-----------|---------------|
| `participant_create_success` | `{name}`, `{numbers}` | Registar participante |
| `participant_update_success` | `{name}`, `{numbers}` | Editar participante |
| `participant_duplicate` | `{name}` | Nome já registado |
| `participant_blacklisted` | `{name}` | Nome na lista negra |
| `participant_error` | `{reason}` | Outros erros |

### Sorteio ao vivo — regra do vencedor (circular)

Os vencedores **não precisam de coincidência exata**. O sistema
sorteia/introduz um número `drawn` (000–999) e ganha o **número livre mais
próximo por cima**, com volta a `000` se não houver nenhum igual ou superior
(distância circular `(candidato - sorteado + 1000) % 1000` mínima).

- Cada participante pode ter até 3 números; os três contam.
- Se o número sorteado estiver livre, ganha diretamente (distância 0).
- Só há erro quando não resta nenhum participante disponível.

#### Vencedor único ou repetido (painel → Winner Rules)

Por defeito **é permitido repetir**: o mesmo participante pode ganhar vários
prémios (`custom_texts.unique_winners = '0'`). Se ativares **Unique winner**
(`'1'`), cada participante só pode ganhar um prémio e os vencedores ficam
excluídos dos sorteios seguintes.

Exemplos:

| Sorteado | Números livres ocupados | Vencedor | Motivo |
|----------|-------------------------|---------|--------|
| `200` | `199`, `205`, `206` | `205` (utilizador b) | Mais próximo por cima de `200` |
| `205` | `199`, `205`, `206` | `205` | Coincidência exata |
| `998` | `005`, `150` | `005` | Nada `>= 998`, volta circular a `000` |

A regra vale para os 3 modos e está em `findClosestParticipant()`
(`includes/functions.php`), usada por `draw.php` e `ajax.php`. O ecrã mostra os
dois números: **Drawn** (sorteado) e **Winning number**, com aviso quando houve
volta a `000`. A auditoria (`draw_audits`) guarda ambos, o participante, o
modo/fornecedor e um `proof_hash`.

### Cópias de segurança

Agenda o script no cron do servidor:

```cron
0 3 * * * php /caminho/completo/para/cron/backup.php
```

Cada execução gera `backup_AAAA-MM-DD_HHMM.tar.gz` com o dump da BD + ficheiros,
e apaga as mais antigas além de `MAX_BACKUPS`. O estado vê-se no admin (*Backups*).

> Os caminhos configuram-se em `includes/config.php`, sem editar `cron/backup.php`.

### Estrutura

```
├── index.php                # Página pública
├── install.php              # Instalador (eliminar após usar)
├── sql/
│   └── schema.sql           #   Esquema completo (9 tabelas + textos por defeito)
├── admin/           # Painel de administração
│   ├── index.php            #   Login
│   ├── dashboard.php        #   Painel principal (também: idioma)
│   ├── participants.php     #   Gestão de participantes
│   ├── draw.php             #   Sorteio ao vivo
│   ├── winners.php          #   Histórico de vencedores
│   ├── prizes.php           #   Prémios
│   ├── blacklist.php        #   Lista negra
│   ├── news.php             #   Notícias
│   ├── texts.php            #   Textos personalizáveis
│   ├── sponsors.php         #   Patrocinadores
│   ├── backups.php          #   Monitorização de cópias
│   ├── preview.php          #   Pré-visualização pública
│   ├── admins.php           #   Administradores
│   ├── change_password.php  #   Alterar palavra-passe
│   ├── reset.php            #   Reinício completo (perigo)
│   └── ajax.php             #   Endpoint AJAX
├── includes/                # Núcleo PHP
│   ├── config.php           #   Ligação BD, constantes e backups
│   ├── functions.php        #   Funções auxiliares
│   ├── auth.php             #   Autenticação e anti força bruta
│   ├── lang.php             #   Sistema multidioma (registo + t())
│   └── lang/                #   Dicionários: en, es, de, pt, fr (+ _template.php)
├── cron/
│   └── backup.php           #   Script de cópia (lê includes/config.php)
└── assets/
    ├── css/                 #   Estilos (público + admin)
    ├── js/                  #   JavaScript (só admin)
    └── img/sponsors/        #   Imagens de patrocinadores
```

### Segurança

- Altera as credenciais por defeito (`admin` / `admin2026`) após instalar.
- **Elimina ou bloqueia `install.php`** após o usar.
- Aponta `BACKUP_DIR` para fora do diretório público em produção.
- As palavras-passe guardam-se com `password_hash()`; o login bloqueia o IP
  após 5 tentativas falhadas durante 48 h.
- `.htaccess` nega o acesso direto a `includes/`, `sql/` e `cron/`.

### Licença

GPL-3.0 — ver ficheiro `LICENSE`.
