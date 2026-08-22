# Domande frequenti

Risposte brevi alle domande che vengono prima di installare Griglia. Le versioni lunghe stanno nelle pagine
collegate da ogni risposta.

## Griglia funziona con il mio agente di coding?

Griglia non si integra con un agente specifico: il contratto sono due comandi artisan e un file di istruzioni.
Qualunque agente CLI capace di eseguire `php artisan griglia:check` e di leggere un file Markdown può usare la
board — Claude Code, Codex CLI, Gemini CLI e altri sono già stati usati così. `vendor:publish --tag=griglia-agents`
scrive le istruzioni che l'agente legge. Vedi [il lato agente](agent/index.md).

## È un'applicazione a parte?

No. Griglia è un package Composer che si installa in un'applicazione Laravel esistente: `composer require`,
`php artisan migrate`, e la board vive nella tua app, con i tuoi utenti e il tuo database. Vedi il
[tutorial di installazione](getting-started/installation.md).

## Servono una chiave AI o una connessione a internet?

No. La board, il flusso con l'agente, le notifiche e i temi funzionano senza alcun provider AI. Le descrizioni
delle immagini, i piani costruiti da un prompt e la trascrizione vocale sono opzionali e si attivano solo se
`laravel/ai` è configurato. Vedi [funzioni AI](features/ai.md).

## Servono Node, Vite o Tailwind?

Non nella configurazione predefinita: Griglia distribuisce CSS e JavaScript già compilati. Se preferisci
compilare il package con la tua build Tailwind, imposta `GRIGLIA_ASSETS=vite` e segui
[asset front-end](getting-started/assets.md).

## Possono lavorare più agenti insieme?

Sì. Ogni lista e ogni task possono essere assegnati a un agente, `griglia:check --agent=<chiave>` mostra solo
il lavoro di quell'agente e un task preso altrove viene segnalato come occupato. Vedi
[due agenti insieme](agent/concurrency.md).

## Posso usarla senza login?

`GRIGLIA_MODE=local` toglie l'autenticazione e rende le liste globali — per una board sulla tua macchina, in
ascolto su `127.0.0.1`. Non esporre mai la modalità local su una rete. Su un server l'accesso è deciso da
`canAccessGriglia()` o `GRIGLIA_ACCESS_GATE`, e le pagine amministrative da `canManageGriglia()`,
`GRIGLIA_ADMIN_GATE` o `GRIGLIA_ADMINS`. Vedi [accessi, amministratori e modalità](configuration/access.md).

## Dove finiscono i miei dati?

Nel database e nello storage della tua applicazione: task, note, allegati, domande, risultati, tempi di lavoro
e token. Griglia non manda niente da nessuna parte, salvo le notifiche o il provider AI che configuri tu.

## È abbastanza stabile da usarla?

La usa ogni giorno il suo autore, ha una suite di test che gira a ogni push e segue il versionamento semantico
— ma è ancora `0.x`: le versioni minori possono cambiare comportamento, e il changelog dice quando. Vedi il
[runbook di aggiornamento](operations/upgrading.md) e la [governance e politica di supporto](contributing/governance.md).
