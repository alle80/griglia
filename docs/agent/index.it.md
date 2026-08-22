# Il lato agente

Griglia funziona con qualsiasi agente di coding capace di eseguire comandi shell. Fornisci all'agente le
istruzioni del repository (`AGENTS.md` per Codex, `CLAUDE.md` per Claude Code o `GEMINI.md` per Gemini CLI),
poi lascia che usi la board tramite Artisan.

## Lavorare su un task

```bash
# 1. Trova il lavoro e leggi le regole correnti
php artisan griglia:check --agent=codex

# 2. Prendi il task prima di analizzarlo
php artisan griglia:check --agent=codex --take=42

# 3. Tieni informata la board
php artisan griglia:check --agent=codex --take=42 --progress=60 --phase="testando"

# 4. Chiudilo con una risposta utile
php artisan griglia:check --agent=codex --done=42 --comment="Implementato e testato."
```

Sostituisci `codex` con la chiave configurata per il tuo agente. `check` mostra soltanto i task disponibili e
attivi di quell'agente, compresi quelli dei piani avviati. Stampa anche le regole di lavoro correnti: vanno
seguite.

Se la richiesta non è chiara, fai una domanda dalla board invece di indovinare:

```bash
php artisan griglia:check --agent=codex --ask=42 \
  --q="Quale layout devo aggiornare?" --choices="Board|Impostazioni"
```

L'utente risponde nel modale e riapre il task. Usa `--pause=42` soltanto per una pausa temporanea dal lato
agente, per esempio quando raggiunge un limite di utilizzo.

## Le regole importanti

- Prendi il task prima di leggerne o analizzarne i dettagli.
- Lavora soltanto sui task aperti assegnati all'agente. Non toccare quelli in attesa o fermati.
- Segui l'ordine e la politica di concorrenza stampati da `check`.
- Mantieni aggiornate percentuale e fase durante il lavoro.
- Includi il conteggio dei token in `--done` quando le impostazioni lo richiedono.
- Usa `--outcome=alert` o `--outcome=blocked` se un task chiuso richiede ancora attenzione.
- Coordina checkout, build, migrazioni e rilasci condivisi quando sono attivi più agenti.

Per lavorare senza supervisione, avvia un [worker persistente](workers.it.md). Per reagire direttamente agli
eventi della board usa `griglia:watch --agent=codex`; aggiungi `--once` per il polling da cron.

## Per approfondire

- [Primi cinque minuti](../getting-started/quickstart.it.md) — completa il primo task passo per passo.
- [Reference dei comandi Artisan](../reference/commands.it.md) — tutti i comandi e le opzioni, comprese review ed esiti.
- [Contesto dell'agente](context.it.md) — genera e mantieni i file di istruzioni.
- [Worker persistenti](workers.it.md) — avvia gli agenti automaticamente.
- [Più agenti](concurrency.it.md) — assegnazioni e coordinamento delle risorse condivise.
- [Skill](skills.it.md) · [Statistiche](stats.it.md) · [Script sull'host](scripts.it.md)
