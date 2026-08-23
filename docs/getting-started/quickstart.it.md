# Primi cinque minuti

Porta una richiesta da **disponibile** a **completata** in pochi minuti. Questa guida presuppone che Griglia sia
[installata](installation.md), che `AGENTS.md` sia stato pubblicato e che esista la lista dell'agente
(`dev` per impostazione predefinita).

## 1. Aggiungi una richiesta

Apri la lista dell'agente nella board e aggiungi un task, per esempio:

> Aggiungi un endpoint di health check che restituisce `{"status":"ok"}`.

Fai clic una volta sul controllo di stato del task. Passa da **in attesa** a **disponibile**; solo a quel punto
un agente può prenderlo in carico.

## 2. Esegui il ciclo

Dalla root dell'applicazione, elenca il lavoro disponibile:

```bash
php artisan griglia:check
```

Copia l'ID del task dall'output e usalo nei comandi successivi (nell'esempio e `12`):

```bash
php artisan griglia:check --take=12
php artisan griglia:check --take=12 --progress=60 --phase="scrittura codice"
php artisan griglia:check --done=12 --comment="Aggiunti endpoint di health check e relativo test."
```

Sono i comandi eseguiti dall'agente di coding mentre segue `AGENTS.md`. La board porta il task **in
lavorazione**, ne mostra l'avanzamento e infine lo segna come **completato**.

## 3. Verifica il risultato

Apri il task. Il commento di chiusura compare sotto la richiesta originale. Verifica la modifica
nell'applicazione e controlla i test o il commit dell'agente come fai abitualmente.

Hai completato l'intero ciclo di una richiesta Griglia.

## Passi successivi

- [Il lato agente](../agent/index.md) - collega il ciclo a Codex, Claude Code o un altro agente CLI.
- [Usare la board](../board/usage.md) - aggiungi note, sotto-task e immagini; poni domande; archivia il lavoro.
- [Worker persistenti](../agent/workers.md) - lascia un agente in attesa di nuovo lavoro e richieste di stop.
- [Configurazione e impostazioni](../configuration/index.md) - cambia nome della lista e comportamento dell'agente.
- [Domande frequenti](../faq.md) · [Glossario](../glossary.md) - risposte brevi e le parole usate in questa documentazione.
