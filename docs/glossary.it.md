# Glossario

Le parole usate in questa documentazione, nell'interfaccia e nei comandi artisan. Dove il codice usa un nome
diverso lo si segnala: sono nomi storici e non cambieranno. Per le domande dietro queste parole, vedi le
[domande frequenti](faq.md).

## La board

**Board** — la pagina che Griglia serve su `/`: una lista per volta, i suoi task e i controlli per filtrare,
cercare e cambiare lista.

**Lista** — un insieme di task con un nome, di un utente (`Checklist` nel codice). La lista corrente sta in
sessione, così ogni query è limitata a un utente e a una lista.

**Lista dell'agente** — la lista che legge l'agente di coding, `agent_list` nella configurazione (`dev` di
default). Le altre liste sono tue: l'agente le ignora.

**Task** — una riga della board (`Todo` nel codice): un titolo, una nota facoltativa, sotto-task, immagini,
uno stato e, a lavoro finito, il risultato dell'agente.

**Sotto-task** — una voce di elenco dentro un task, elencata sotto la nota nel modale: l'agente le spunta
mentre lavora, e può spuntarle tutte quando chiude il task.

**Ingredient** — il sotto-task visto dal codice: il modello `Ingredient`, la tabella `ingredients` e il
componente `IngredientModal`. È un nome storico, eredità dell'app da cui Griglia è nata, tenuto perché
rinominarlo romperebbe ogni database già installato: dove la documentazione dice *sotto-task*, il codice dice
*ingredient*.

**Archivio** — dove finiscono i task chiusi invece di essere cancellati: fuori dalla lista, ancora
ricercabili, e in automatico dopo `auto_archive_days`.

## Il flusso con l'agente

**Agente** — l'assistente di coding da riga di comando che legge la board con `griglia:check`. Griglia è
neutrale rispetto all'agente: ognuno ha una **chiave agente** (`--agent=claude`, `--agent=codex`, …) che
decide che cosa vede, quali task può prendere e il nome mostrato sulla board.

**Stato** — il pallino a inizio riga. Dice di chi è il task in questo momento:

| Pallino | Stato | Chi lo imposta |
|---------|-------|----------------|
| ![in attesa](images/state-waiting.svg){ width="18" } | in attesa | tu — l'agente non deve toccarlo |
| ![da lavorare](images/state-open.svg){ width="18" } | da lavorare | tu — passato all'agente, che può prenderlo |
| ![in lavorazione](images/state-working.svg){ width="18" } | in lavorazione | l'agente (`--take`), con avanzamento e fase |
| ![in pausa](images/state-paused.svg){ width="18" } | in pausa | l'agente (`--pause`) — l'avanzamento resta |
| ![domanda](images/state-question.svg){ width="18" } | domanda | l'agente (`--ask`) — aspetta la tua risposta |
| ![fermato](images/state-stop.svg){ width="18" } | fermato | tu, toccando il badge di lavorazione — l'agente lo molla |
| ![fatto](images/state-done.svg){ width="18" } | fatto | l'agente (`--done`) o tu (casella) |

Vedi [usare la board](board/usage.md).

**Presa in carico** — l'agente che prende un task, `griglia:check --take=ID`: lo stato diventa *in
lavorazione* e la board lo mostra dal vivo.

**Avanzamento e fase** — la percentuale e l'etichetta breve che l'agente aggiorna mentre lavora
(`--progress=60 --phase="scrivendo codice"`), mostrate come barra sulla riga.

**Domanda** — l'agente che mette in pausa un task per chiedere qualcosa, `--ask=ID --q="…"`. Rispondi nel
modale del task e lo fai ripartire; domanda e risposta restano visibili.

**Risultato** — quello che l'agente scrive chiudendo il task, `--done=ID --comment="…"`, mostrato in un
riquadro in sola lettura sotto la tua nota. Non tocca mai la nota, che è tua.

**Statistiche** — il tempo di lavoro che la board misura da sola (gli intervalli *in lavorazione*) più i
token che l'agente riporta alla chiusura, valorizzati con i prezzi in Impostazioni → App. Vedi
[statistiche](agent/stats.md).

**Revisione** — un secondo giro facoltativo: un task può essere assegnato a un agente revisore, che approva
il risultato o lo rimanda indietro con delle osservazioni. Vedi [il lato agente](agent/index.md).

**Worker** — un processo sull'host che tiene l'agente al lavoro senza sorveglianza: interroga la board e apre
una nuova sessione non interattiva dell'agente per ciò che è da lavorare. Vedi
[worker persistenti](agent/workers.md).

## Intorno alla board

**Piano** — una lista generata da un obiettivo e spezzata in una catena di task: chiuderne uno apre il
successivo. Vedi [piani](features/plans.md).

**Skill** — una capacità dell'agente, con un nome, che puoi attaccare a un task perché l'agente la attivi
mentre lavora. Vedi [skill](agent/skills.md).

**Blocchi di contesto** — i pezzi che la pagina `/context` assembla nel file di istruzioni che legge
l'agente (`CLAUDE.md`, `AGENTS.md`, …), ognuno attivabile a parte. Vedi
[contesto dell'agente](agent/context.md).

**Tema** — l'aspetto della board: colori, spaziature e forme, attraverso variabili CSS. Si installa come
package o come zip, e cambiarlo non cambia il markup. Vedi [temi](features/themes.md).

**Stile** — un passo oltre il tema: componenti Livewire e viste Blade proprie, così la board può essere
disposta diversamente invece che solo ricolorata. Vedi
[estendere Griglia](configuration/extending.md#uno-stile-dedicato-componenti-tuoi).

**Modalità** — `server` (con autenticazione, una board per utente, il default) o `local` (senza
autenticazione, liste globali, per la tua macchina). Vedi
[accessi, amministratori e modalità](configuration/access.md).
