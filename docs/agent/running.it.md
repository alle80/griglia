# Avviare l'agente

Installare Griglia non avvia un agente di coding. I modi per farlo partire sono tre, in ordine di quanto resti
davanti alla tastiera. Tutti e tre usano lo stesso contratto — il file di istruzioni nella radice del progetto e
`griglia:check --agent=<chiave>` — quindi puoi scendere lungo l'elenco appena il modo precedente diventa noioso.

| Modo | Cosa fa | Sceglilo quando |
|---|---|---|
| [1. Un terminale nel progetto](#1-un-terminale-nella-directory-del-progetto) | avvii l'agente e segui la sessione | la prima volta, sempre |
| [2. Un comando per sessione](#2-un-comando-per-sessione-cli) | una sessione non interattiva che finisce da sola | la lanci da uno script, da cron o dalla CI |
| [3. Un worker persistente](#3-un-worker-persistente) | un servizio avvia una sessione quando c'è lavoro | la board deve lavorare senza supervisione |

## Cosa serve a tutti e tre

- il CLI dell'agente installato sulla macchina che ospita il progetto — `claude`, `codex`, `gemini`, …
- un file di istruzioni nella radice del progetto: `AGENTS.md`, `CLAUDE.md` o `GEMINI.md`. Pubblica quello del
  package con `php artisan vendor:publish --tag=griglia-agents`, oppure generalo da `/context` (vedi
  [contesto dell'agente](context.it.md))
- una lista che si chiami come `agent_list` (`dev` di default) con almeno un task in stato **open to work**
- la chiave dell'agente che la board conosce (`agent_key`, negli esempi `claude`)

## 1. Un terminale nella directory del progetto

Apri un terminale, entra nella radice del progetto — la directory con `artisan` e il file di istruzioni — e
avvia **lì** il CLI dell'agente:

```bash
cd /srv/mio-progetto
claude            # Claude Code · `codex` per Codex CLI · `gemini` per Gemini CLI
```

La directory è tutto il trucco: l'agente legge il file di istruzioni dalla directory corrente ed esegue
`php artisan` da lì. Un agente avviato altrove è il motivo più comune per cui «non vede» la board.

Poi mandagli il primo messaggio:

```{ .text .agent-prompt title="Primo messaggio — copialo" }
Leggi AGENTS.md e lavora sulla board Griglia come agente claude: esegui php artisan griglia:check --agent=claude, prendi in carico il primo task open to work e segui il workflow fino alla chiusura.
```

L'agente esegue `griglia:check` e il pallino del task diventa **working** sulla board sotto i tuoi occhi. Da lì
il ciclo è affar suo — avanzamento, domande, commento di chiusura — e il tuo è rispondere quando ti interpella.
La stessa sessione vista dal lato dei comandi è [il lato agente](index.it.md).

Se l'applicazione gira in un container, i comandi Artisan sono `docker exec <container> php artisan …`: scrivilo
una volta nel file di istruzioni e l'agente userà quella forma ovunque.

## 2. Un comando per sessione (CLI)

Stessa directory, un solo comando che arriva in fondo ed esce. È la forma da mettere in uno script, in una riga
di cron o in un job di CI:

```bash
cd /srv/mio-progetto
claude -p --permission-mode bypassPermissions \
  "Work on Griglia as agent claude. Read AGENTS.md first and obey it. Take the first task that is open to work
   with php artisan griglia:check --agent=claude, complete it, and close it with --done."
```

Codex CLI riceve il prompt allo stesso modo, con i suoi flag e la directory del progetto esplicita:

```bash
codex exec --approve-for-me -C /srv/mio-progetto \
  "Work on Griglia as agent codex. Read AGENTS.md first and obey it. …"
```

Quei flag permettono alla sessione di agire senza chiedere conferma a ogni passo: usali solo su un progetto di
cui ti fidi e preferisci il permesso più stretto che il tuo CLI offre. Qui nessuno sorveglia la board: ogni
sessione parte perché l'hai avviata tu (o il tuo scheduler) e lavora un task.

## 3. Un worker persistente

Il worker ripete il modo 2 da solo: interroga la board e avvia una sessione nuova appena un task diventa open to
work per la sua chiave agente, una sessione alla volta per progetto e chiave, e rispetta le richieste di stop.

```bash
php artisan vendor:publish --tag=griglia-scripts     # scripts/ e il template dell'unit systemd
# copia scripts/systemd/griglia-agent-worker@.service.example, mettici il path del progetto, poi:
systemctl --user enable --now griglia-agent-worker@claude.service
```

La configurazione completa — template dell'unit, un worker per progetto e agente, modello ed effort, limiti di
utilizzo, log — è in [worker persistenti](workers.it.md).

## Poi

- [Il lato agente](index.it.md) — i comandi che una sessione esegue, da `--take` a `--done`.
- [Due agenti insieme](concurrency.it.md) — assegnazioni e risorse condivise con più CLI in funzione.
- [Primi cinque minuti](../getting-started/quickstart.it.md) — lo stesso ciclo fatto a mano, per sapere cosa aspettarti.
