# Installare Griglia in un'applicazione Laravel

Questo tutorial aggiunge Griglia a un'applicazione Laravel esistente e termina quando un utente autenticato apre
una board funzionante, l'agente legge le sue istruzioni e `griglia:check` elenca la coda. Serve una decina di
minuti; le integrazioni opzionali possono seguire.

## La versione corta

Dalla root dell'applicazione Laravel ospite:

```bash
composer require alle80/griglia -W
php artisan vendor:publish --tag=griglia-config     # config/griglia.php — decidi le chiavi qui sotto
php artisan migrate                                 # dopo aver scelto GRIGLIA_TABLE_PREFIX
php artisan vendor:publish --tag=griglia-agents     # AGENTS.md, il workflow che legge l'agente
php artisan vendor:publish --tag=griglia-scripts    # scripts/ sull'host (contesto, token, skill)
php artisan griglia:check                           # la board vista dall'agente
```

Poi autenticarsi e aprire `/`. Ogni passo è spiegato sotto — leggilo se qualcosa in quel blocco non è ovvio, e
attenzione all'[avviso sui backup](#5-i-file-che-legge-lagente) prima degli ultimi due comandi.

## Prima di iniziare

- PHP 8.3+, Laravel 12 o 13, Livewire 4.4+, Composer e un database configurato
- `ext-gd`, `ext-fileinfo` ed `ext-zip`
- un flusso di autenticazione funzionante e almeno un utente per la modalità `server` predefinita
- `python3` sulla macchina dove gira l'agente, per gli script sull'host
- la root dell'applicazione Laravel ospite come directory corrente

Creare un backup prima di cambiare dipendenze o applicare migrazioni a un'applicazione esistente.

## 1. Installare il package

```bash
composer require alle80/griglia -W
```

`-W` permette a Composer di aggiornare le dipendenze transitive richieste da Web Push. Composer pubblica anche
gli asset precompilati tramite il tag Laravel `laravel-assets`. Un'esecuzione riuscita aggiunge
`alle80/griglia` a `composer.json` e termina senza conflitti.

## 2. Pubblicare la configurazione

```bash
php artisan vendor:publish --tag=griglia-config
```

Il comando scrive `config/griglia.php`, un file commentato dove ogni chiave legge una variabile d'ambiente. Si
può saltarlo e impostare solo le variabili nel `.env` — ma pubblicalo se ti servono le chiavi che non hanno una
variabile (`middleware`, `themes`, `register_routes`, `home_route`, `push_allowed_hosts`).

### Le chiavi che contano dal primo giorno

| Chiave | Variabile | Default | Da impostare quando |
|---|---|---|---|
| `table_prefix` | `GRIGLIA_TABLE_PREFIX` | `griglia_` | vuoi tabelle con un altro nome — **decidilo prima del primo `migrate`** |
| `user_model` | `GRIGLIA_USER_MODEL` | `App\Models\User` | il tuo modello utente sta altrove |
| `mode` | `GRIGLIA_MODE` | `server` | la board gira sulla tua macchina: `local` toglie l'autenticazione e rende globali le liste |
| `route_prefix` | `GRIGLIA_ROUTE_PREFIX` | `''` (root del sito) | `/` è già dell'applicazione: `board` serve `/board`, `/board/settings`, … |
| `dashboard_route` | `GRIGLIA_DASHBOARD_ROUTE` | `/dashboard` | vuoi la board su un solo percorso, o su nessuno (`null`) |
| `agent_list` | `GRIGLIA_AGENT_LIST` | `dev` | la lista dove metti il lavoro si chiama diversamente |
| `agent_name` | `GRIGLIA_AGENT_NAME` | `Agent` | l'interfaccia deve dire «Claude», «Codex», … |
| `agents` / `agent_key` | `GRIGLIA_AGENTS`, `GRIGLIA_AGENT_KEY` | un agente | due o più agenti CLI condividono la board |
| `attachments_disk` | `GRIGLIA_ATTACHMENTS_DISK` | `local` | le immagini allegate vanno su un altro disco |

Un `.env` che funziona nel caso comune — board alla root del sito, un agente Claude Code, lista `dev`:

```dotenv
GRIGLIA_MODE=server
GRIGLIA_AGENT_LIST=dev
GRIGLIA_AGENT_NAME=Claude
```

E per una board sulla propria macchina, con due agenti sopra:

```dotenv
GRIGLIA_MODE=local
GRIGLIA_AGENTS="claude:Claude Code,codex:Codex CLI"
GRIGLIA_AGENT_KEY=claude
```

`GRIGLIA_MODE=local` toglie l'autenticazione: usarlo solo su una macchina fidata legata a `127.0.0.1`.

!!! note "Configurazione in cache"
    Un'applicazione che esegue `config:cache` (qualsiasi deploy di produzione) non vede il `.env` cambiato finché
    non rilanci `php artisan config:cache` — e `php artisan route:cache` dopo aver cambiato `route_prefix`,
    `home_route` o `dashboard_route`. Un 404 su una rotta appena attivata è quasi sempre una cache vecchia.

Tutto il resto sta nel [riferimento della configurazione](../reference/config.md), generato dal codice; chi può
aprire e amministrare la board è in [accessi e modalità](../configuration/access.md).

## 3. Creare tabelle e impostazioni

```bash
php artisan migrate
```

Le migrazioni sono idempotenti e creano, quando mancanti, dati della board, impostazioni, notifiche e
sottoscrizioni push. Rispettano `table_prefix`, così con il default `griglia_` tutte le tabelle del package
restano insieme. Cambiare il prefisso dopo significa rinominare quelle tabelle a mano.

## 4. Aprire la board

Accedere all'applicazione ospite e aprire `/`. Griglia deve mostrare una prima lista. Le rotte usano il middleware
`web` e il middleware di accesso di Griglia; in modalità `server` una richiesta non autenticata viene reindirizzata
al login dell'applicazione — un'applicazione senza nessuna autenticazione risponde `Route [login] not defined`,
quindi aggiungere prima un flusso di login oppure, su una macchina fidata, usare `GRIGLIA_MODE=local`.

Se `/` appartiene già all'applicazione ospite, impostare `route_prefix` oppure disattivare `home_route` e usare
la rotta dashboard configurata.

## 5. I file che legge l'agente

Un agente CLI legge a ogni turno un file Markdown nella root del progetto: `AGENTS.md` (Codex CLI, Cursor, Amp,
Zed…), `CLAUDE.md` (Claude Code), `GEMINI.md` (Gemini CLI). Griglia può **generare** quei file dal contesto che
gestisci su `/context`, così le regole che l'agente segue diventano blocchi attivabili invece di un file che
nessuno osa toccare.

!!! warning "Fai il backup dei file di istruzioni prima di questo passo"
    La generazione **sovrascrive** `CLAUDE.md` e `AGENTS.md` nella root del progetto con il contenuto della board.
    Se ne hai già di scritti a mano, salvali prima — è un comando solo, e puoi tornare indietro in qualsiasi
    momento:

    ```bash
    php artisan vendor:publish --tag=griglia-scripts   # mette gli script host in scripts/
    scripts/sync-context.py --backup                   # li copia in docs/context-originals/
    cp AGENTS.md CLAUDE.md ~/backup/                   # doppia sicurezza; va bene anche committarli su git
    ```

    `--backup` salva un file solo se non è generato e non è già stato salvato, quindi lanciarlo **prima** della
    prima sincronizzazione. Per tornare indietro: `scripts/sync-context.py --restore` rimette gli originali, e
    spegnere «Genera i file di istruzioni dalla board» su `/context` li ripristina e ferma la generazione per
    sempre. `vendor:publish` non sovrascrive mai un file esistente a meno di aggiungere `--force` — che è
    esattamente l'opzione capace di mangiarsi un `AGENTS.md` tuo.

### Partire dal workflow del package

Se non hai ancora un file di istruzioni, pubblica quello che arriva con Griglia:

```bash
php artisan vendor:publish --tag=griglia-agents
```

Scrive nella root del progetto l'`AGENTS.md` portabile: stati della board, ciclo di vita di `griglia:check`, le
regole che l'agente deve seguire.

### Oppure partire dal file che hai già

```bash
php artisan griglia:context import --file=CLAUDE.md
```

Il Markdown diventa gruppi (uno per titolo `##`) e blocchi su `/context`, ognuno con il suo interruttore e una
stima dei token. Con `--replace` il contesto attuale viene azzerato e reimportato da capo.

### Generare i file

Lo script sull'host trasforma i blocchi attivi nei file di istruzioni:

```bash
scripts/sync-context.py            # scrive CLAUDE.md e AGENTS.md se il contenuto è cambiato
scripts/sync-context.py --check    # esce con 1 se non sono aggiornati (utile in CI)
```

Un file generato si apre con `<!-- Generated by Griglia (/context) … -->`: si modificano i blocchi sulla pagina,
non il file. Per tenerlo aggiornato senza pensarci:

```cron
* * * * * cd /srv/app && scripts/sync-context.py -q
```

`GRIGLIA_CONTEXT_TARGETS="CLAUDE.md,AGENTS.md,GEMINI.md"` aggiunge un file di destinazione. Gli script host
raggiungono Artisan via `docker exec` per default; se Laravel gira direttamente sulla macchina, impostare
`GRIGLIA_TRANSPORT=local` — vedi [script sull'host](../agent/scripts.md) e [contesto dell'agente](../agent/context.md).

## 6. Collegare l'agente

Creare o rinominare una lista in modo che corrisponda a `GRIGLIA_AGENT_LIST` (`dev` per default), avviare
l'agente in quella directory, poi eseguire:

```bash
php artisan griglia:check
```

Risultato atteso: il comando stampa le impostazioni di comportamento e gli elementi aperti o in lavorazione.

Installare Griglia **non** avvia un agente di coding: dopo averlo collegato, scegliere come eseguirlo. Per una
sessione interattiva, avviare manualmente l'agente nella directory del progetto e seguire il [workflow lato
agente](../agent/index.md). Per lavorare senza supervisione, installare un [worker
persistente](../agent/workers.md), che sorveglia la board e avvia una nuova sessione dell'agente quando un task
è pronto.

## Verificare l'installazione

```bash
php artisan route:list --name=griglia
php artisan griglia:check --all
scripts/sync-context.py --check
```

Verificare che le rotte siano presenti, che la board si apra per l'utente previsto, che la CLI legga la stessa
lista e che i file di istruzioni corrispondano alla board. Completare il [quickstart](quickstart.md) per provare
l'intero ciclo di una richiesta.

## Integrazioni opzionali

- [Asset front-end](assets.md): passare dai file precompilati alla build Vite dell'applicazione.
- [Aggiornamenti live e notifiche](../features/notifications.md): configurare broadcaster e Web Push.
- [Funzioni AI](../features/ai.md): attivare piani, trascrizione e descrizione immagini.
- [Temi](../features/themes.md): scegliere o installare un tema grafico.

Aggiornamenti live e Web Push conviene configurarli solo dopo che l'installazione richiesta funziona: setup
canonico — HTTPS, chiavi VAPID e trait di sottoscrizione sul modello utente — nella [guida alle
notifiche](../features/notifications.md).

## Problemi comuni

| Sintomo | Causa probabile | Azione |
|---|---|---|
| Composer segnala un conflitto su `brick/math` | dipendenze transitive bloccate | ripetere il comando con `-W` |
| `/` reindirizza al login | protezione normale della modalità `server` | autenticarsi o configurare l'accesso intenzionalmente |
| `/` risponde 500 con `Route [login] not defined` | l'applicazione ospite non ha nessuna autenticazione | aggiungere uno starter kit o una rotta chiamata `login`, oppure usare `GRIGLIA_MODE=local` su una macchina fidata |
| `/` restituisce 404 dopo l'installazione | cache delle rotte vecchia | eseguire `php artisan route:cache` o pulire la cache durante il setup |
| Il `.env` modificato non ha effetto | configurazione in cache | eseguire `php artisan config:cache` |
| CSS o JavaScript mancano | asset non pubblicati o modalità incoerente | ripubblicare `laravel-assets` o seguire la guida Vite |
| La lista dell'agente è vuota | il nome non coincide con `GRIGLIA_AGENT_LIST` | rinominare la lista o aggiornare la configurazione |
| `CLAUDE.md` è tornato diverso | lo genera la board | modificare i blocchi su `/context`, oppure `scripts/sync-context.py --restore` |
| Dopo un aggiornamento le tabelle della board non ci sono | è cambiato il prefisso | tenere `GRIGLIA_TABLE_PREFIX` com'era, o rinominare le tabelle |
