# Il lato agente

Griglia funziona con qualsiasi agente di coding capace di eseguire comandi shell. Fornisci all'agente le
istruzioni del repository (`AGENTS.md` per Codex, `CLAUDE.md` per Claude Code o `GEMINI.md` per Gemini CLI),
poi lascia che usi la board tramite Artisan.

## Lavorare su un task

```bash
# 1. Elenca il lavoro disponibile
php artisan griglia:check --agent=codex

# 2. Segna un task come in lavorazione
php artisan griglia:check --agent=codex --take=42

# 3. Registra l’avanzamento
php artisan griglia:check --agent=codex --take=42 --progress=60 --phase="testando"

# 4. Segna il task come completato e salva il risultato
php artisan griglia:check --agent=codex --done=42 --comment="Implementato e testato."
```

Sostituisci `codex` con la chiave configurata per il tuo agente. `check` mostra soltanto i task disponibili e
attivi di quell’agente: prima la lista dell’agente, poi i piani avviati, poi — sotto `📋 List «…»` — i task che
hai messo *da lavorare* in un’altra delle tue liste, che l’agente lavora per ultimi. Stampa anche le impostazioni che regolano il flusso
dell’agente; le istruzioni generate del repository spiegano come applicarle.

Lo stesso comando può collegare una domanda al task:

```bash
php artisan griglia:check --agent=codex --ask=42 \
  --q="Quale layout devo aggiornare?" --choices="Board|Impostazioni"
```

L’utente risponde nel modale e riapre il task. `--pause=42` registra una pausa temporanea dal lato agente,
per esempio quando raggiunge un limite di utilizzo.

Non hai ancora un agente in funzione? [Avviare l'agente](running.it.md) mostra i tre modi di lanciarne uno: un
terminale nella directory del progetto, un singolo comando non interattivo o un worker persistente.
Per lavorare senza supervisione, avvia un [worker persistente](workers.it.md). Per reagire direttamente agli
eventi della board usa `griglia:watch --agent=codex`; aggiungi `--once` per il polling da cron.

## Per approfondire

- [Architettura](../architecture.it.md) — la macchina a stati che questi comandi muovono, e le tabelle sotto.
- [Primi cinque minuti](../getting-started/quickstart.it.md) — completa il primo task passo per passo.
- [Reference dei comandi Artisan](../reference/commands.it.md) — tutti i comandi e le opzioni, comprese review ed esiti.
- [Contesto dell'agente](context.it.md) — genera e mantieni i file di istruzioni.
- [Avviare l'agente](running.it.md) — i tre modi di lanciare una sessione.
- [Worker persistenti](workers.it.md) — avvia gli agenti automaticamente.
- [Più agenti](concurrency.it.md) — assegnazioni e coordinamento delle risorse condivise.
- [Skill](skills.it.md) · [Statistiche](stats.it.md) · [Script sull'host](scripts.it.md)
