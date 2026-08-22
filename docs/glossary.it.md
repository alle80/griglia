# Glossario

Le parole usate in questa documentazione, nell'interfaccia e nei comandi artisan. Dove il codice usa un nome
diverso lo si segnala: sono nomi storici e non cambieranno.

## La board

**Board** — la pagina che Griglia serve su `/`: una lista per volta, i suoi task e i controlli per filtrare,
cercare e cambiare lista.

**Lista** — un insieme di task con un nome, di un utente (`Checklist` nel codice). La lista corrente sta in
sessione, così ogni query è limitata a un utente e a una lista.

**Lista dell'agente** — la lista che legge l'agente di coding, `agent_list` nella configurazione (`dev` di
default). Le altre liste sono tue: l'agente le ignora.

**Task** — una riga della board (`Todo` nel codice): un titolo, una nota facoltativa, sotto-task, immagini,
uno stato e, a lavoro finito, il risultato dell'agente.

**Sotto-task** — una voce di elenco dentro un task (`Ingredient` nel codice e nel database: nome storico,
eredità dell'app da cui Griglia è nata).

**Archivio** — dove finiscono i task chiusi invece di essere cancellati: fuori dalla lista, ancora
ricercabili, e in automatico dopo `auto_archive_days`.

## Il flusso con l'agente

**Stato** — il pallino a inizio riga: *in attesa* (tuo, l'agente non lo tocca), *da lavorare* (pronto per
l'agente), *in lavorazione*, *domanda*, *in pausa*, *fermato*, *fatto*. Vedi
[usare la board](board/usage.md).

**Presa in carico** — l'agente che prende un task, `griglia:check --take=ID`: lo stato diventa *in
lavorazione* e la board lo mostra dal vivo.

**Avanzamento e fase** — la percentuale e l'etichetta breve che l'agente aggiorna mentre lavora
(`--progress=60 --phase="scrivendo codice"`), mostrate come barra sulla riga.

**Domanda** — l'agente che mette in pausa un task per chiedere qualcosa, `--ask=ID --q="…"`. Rispondi nel
modale del task e lo fai ripartire; domanda e risposta restano visibili.

**Risultato** — quello che l'agente scrive chiudendo il task, `--done=ID --comment="…"`, mostrato in un
riquadro in sola lettura sotto la tua nota. Non tocca mai la nota, che è tua.

**Statistiche** — il tempo di lavoro che la board misura da sola (gli intervalli *in lavorazione*) più i
token che l'agente riporta alla chiusura. Vedi [statistiche](agent/stats.md).

**Revisione** — un secondo giro facoltativo: un task può essere assegnato a un agente revisore, che approva
il risultato o lo rimanda indietro con delle osservazioni. Vedi [il lato agente](agent/index.md).

**Worker** — un processo che tiene viva una sessione dell'agente senza sorveglianza, prendendo ciò che è da
lavorare. Vedi [worker persistenti](agent/workers.md).

## Intorno alla board

**Piano** — una lista generata da un obiettivo e spezzata in una catena di task: chiuderne uno apre il
successivo. Vedi [piani](features/plans.md).

**Skill** — una capacità dell'agente, con un nome, che puoi attaccare a un task perché l'agente la attivi
mentre lavora. Vedi [skill](agent/skills.md).

**Blocchi di contesto** — i pezzi che la pagina `/context` assembla nel file di istruzioni che legge
l'agente (`CLAUDE.md`, `AGENTS.md`, …), ognuno attivabile a parte. Vedi
[contesto dell'agente](agent/context.md).

**Tema** — l'aspetto della board, installabile come package o come zip. Vedi [temi](features/themes.md).

**Modalità** — `server` (con autenticazione, una board per utente, il default) o `local` (senza
autenticazione, liste globali, per la tua macchina). Vedi
[accessi, amministratori e modalità](configuration/access.md).
