# Script sull'host

Una parte della board vive fuori dal container: le skill che l'agente ha installato, le sue credenziali, il
transcript della sessione. Gli script per l'host e il worker persistente se ne occupano **sulla macchina dove
gira l'agente** e spingono il risultato dentro la board attraverso i comandi artisan. Sono distribuiti con il
package:

```bash
php artisan vendor:publish --tag=griglia-scripts   # → scripts/ nel tuo progetto
```

| Script | Cosa fa | Comando che alimenta |
| --- | --- | --- |
| `sync-skills.py` | legge le cartelle delle skill di Claude Code, Codex CLI e Gemini CLI (più le skill integrate elencate in `builtin-skills.json`) e marca ogni skill con gli agenti che possono invocarla | `griglia:skills-import` |
| `sync-context.py` | riscrive i blocchi di contesto attivi dentro `CLAUDE.md` / `AGENTS.md`, conserva gli originali, `--check` dice se sono allineati, `--import` carica un file scritto a mano | `griglia:context` |
| `claude-tokens.py` | somma i token della sessione spesi su un task (`--todo=ID --args` li stampa pronti per `griglia:check --done`) | `griglia:check` |
| `agent-status.py` | legge le credenziali OAuth dell'agente e manda **solo percentuali** delle finestre del piano | `griglia:agent-status-import` |
| `griglia-agent-worker.py` | controlla il lavoro assegnato e lancia Codex, Claude Code o una CLI a scelta; il template systemd lo tiene vivo | `griglia:check` |

A tutti gli script sull'host serve `python3`, e raggiungono Artisan attraverso un **trasporto**, scelto da
`GRIGLIA_TRANSPORT`: `docker` (`docker exec <container> php artisan`, container da `GRIGLIA_CONTAINER`, di
default `laravel-dev-app`), `local` (`php artisan` dalla radice del progetto, con `GRIGLIA_PHP` a dire qual è
l'eseguibile quando non è semplicemente `php`) oppure — il default — `auto`, che usa il container quando è in
esecuzione e altrimenti PHP su questa macchina. Un host senza Docker fa girare tutta la catena senza nessuna
configurazione; fissa la scelta dove la verifica è solo peso o dove i due ambienti convivono:

```dotenv
GRIGLIA_TRANSPORT=local
GRIGLIA_PHP=/usr/bin/php8.4
```

I dettagli e tutto il resto che cambia fuori da un container stanno in
[Usare Griglia senza Docker](../getting-started/without-docker.md). Gli script di sincronizzazione hanno
modalità «stampa» e «controlla»; al worker invece serve accesso al trasporto scelto **e** alla CLI dell'agente
che lancia. Legge le stesse variabili e accetta valori diversi per ogni istanza — vedi
[Worker persistenti](workers.md).

## Dove pensano di essere

Agli script di sincronizzazione serve la radice del progetto (i file di istruzioni e le cartelle dei
transcript). La leggono da `GRIGLIA_PROJECT_ROOT` quando c'è; altrimenti la ricavano dalla propria posizione —
la cartella che contiene `scripts/`, oppure quella che contiene `vendor/` quando li lanci direttamente da
`vendor/alle80/griglia/scripts/`. Quindi funzionano tutte e due queste forme:

```bash
python3 scripts/sync-skills.py                              # copia pubblicata
python3 vendor/alle80/griglia/scripts/sync-skills.py        # direttamente dal package
GRIGLIA_PROJECT_ROOT=/srv/app python3 scripts/sync-skills.py  # da qualunque altro posto
```

Un cron tipico sull'host:

```cron
* * * * * cd /srv/app && python3 scripts/sync-context.py >/dev/null 2>&1
*/5 * * * * cd /srv/app && python3 scripts/agent-status.py >/dev/null 2>&1
```

## Vedi anche

- [Skill](skills.md) · [Contesto dell'agente](context.md) · [Statistiche](stats.md)
- [Comandi artisan](../reference/commands.md) — i comandi che questi script alimentano.
