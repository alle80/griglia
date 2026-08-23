# Roadmap

Cosa fa già Griglia, cosa si sta costruendo e cosa non farà mai. Scritta per capire in un minuto se il
package ti serve, senza aprire l'issue tracker.

**Niente date.** L'ordine qui sotto è una preferenza, non un calendario: la lista si lavora dall'alto verso
il basso, ma un rilascio esce quando un pezzo è finito. Le versioni sono `0.x`: cosa promettono è scritto in
[versioni e rilasci](contributing/releases.md).

## Dove siamo oggi

| Ambito | Stato |
|---|---|
| Il ciclo del task | Completo: in attesa → da fare → in lavorazione → fatto, con domande, pausa, stop e ripresa — vedi l'[architettura](architecture.md) |
| Il contratto con l'agente | `griglia:check` (take, progress, phase, ask, done, outcome, token) e `griglia:watch`, con più agenti e proprietà per task |
| Lavoro non presidiato | [Worker persistenti](agent/workers.md) con Codex, Claude Code o un driver tuo |
| Piani | Un obiettivo diventa una catena di task: chiudendone uno si apre il successivo |
| Notifiche | Campanella in-app, Web Push e mail, ognuna disattivabile |
| Aspetto | Dieci stili, [pacchetti tema](features/themes.md) installabili, inglese e italiano, una terza lingua pubblicando le traduzioni |
| AI opzionale | Piani da un prompt, descrizione delle immagini, dettatura vocale |
| Documentazione | Questo sito bilingue, con le reference di comandi, configurazione e impostazioni generate dal codice |

## Cosa arriva

Ogni riga è un pezzo di lavoro a sé, con la decisione già presa. Possono uscire in qualsiasi ordine, e
ciascuno nel proprio rilascio.

| In arrivo | Cosa aggiunge |
|---|---|
| **Asticella di qualità** | PHPStan livello 5 senza baseline, Pint, matrice CI su PHP 8.3/8.4 × Laravel 12/13, `--prefer-lowest`, un job MySQL, `composer audit`, le factory dei modelli |
| **Primo contatto** | Screenshot e una breve registrazione nel README, e un quickstart verificato su un `laravel new` appena creato |
| **`griglia:install`** | Un solo comando idempotente: configurazione, migrazioni, lista dell'agente, `storage:link`, il file di istruzioni solo se manca, e `--user=` per creare il primo amministratore |
| **Accesso zero-config** | La modalità `local` documentata come percorso consigliato per l'uso personale; la modalità `server` continua a usare la tua autenticazione — il package non porterà un login suo |
| **Cronologia del task** | Una timeline dei cambi di stato di un task, con l'attore (tu, un agente, la board), in un accordion nel modale |
| **Task bloccati e `griglia:doctor`** | Un task lasciato «in lavorazione» senza avanzamenti per troppo tempo viene segnalato e notificato una volta — mai riaperto da solo — più un controllo di salute su migrazioni, impostazioni, dischi, chiavi VAPID, broadcasting e file di istruzioni |
| **Export, import e `--json` versionato** | `griglia:export` in uno zip (JSON più allegati), `griglia:import` che crea liste nuove, export in Markdown di un task o di un piano, e un solo schema documentato condiviso da export e `--json` |
| **Allegati file e link** | Oltre alle immagini: una allow-list sicura di tipi di file (niente HTML, SVG o eseguibili) e link normali mostrati come chip |
| **Scorciatoie da tastiera** | Cerca, nuovo task, spostarsi e aprire, modificare, segnare fatto, cambiare lista e un pannello con `?` — nessun tasto singolo per un'azione distruttiva |
| **Verso la 1.0** | Una prima installazione fatta da chi non ha scritto il package, poi il rilascio 1.0 quando schema e opzioni dei comandi saranno stabili |

## Fuori perimetro per scelta

Queste non sono «non ancora»: sono decisioni. Se ti serve una di queste, Griglia è lo strumento sbagliato, e
va benissimo così.

| Non in programma | Perché |
|---|---|
| Team, condivisione, task assegnati a persone | Griglia è single-owner: una persona e i suoi agenti. Lo scoping resta «solo le tue liste» |
| Etichette, scadenze, priorità, stime, ricorrenze | La board è una coda: l'ordine in cui trascini le righe *è* la priorità |
| API HTTP, token macchina, webhook, server MCP | Il contratto è artisan più un worker. Chi esegue il comando ha già una shell e il database: una seconda porta, più debole, allargherebbe solo la superficie. Per reagire ai cambiamenti si ascolta [`TodoChanged`](reference/events.md) nella propria applicazione |
| Telegram, Slack o altri canali di chat | Campanella, Web Push e mail ti raggiungono già; ogni canale in più è un'integrazione da tenere viva |
| PWA e lettura offline | La board è Livewire: ha bisogno del server. Una copia offline sarebbe una seconda fonte di verità, che mente |
| Un login proprio o una demo pubblica | L'autenticazione è dell'applicazione che ospita il package ([accessi e modalità](configuration/access.md)) |
| Cestino, interruttore chiaro/scuro, formati di data localizzati | Piccole comodità che costano più di quello che danno: archivio, temi e date ISO le coprono già |

## Come si decide

La direzione la dà chi mantiene il package, allo scoperto: vedi la [governance](contributing/governance.md).
Una proposta è benvenuta come issue che racconti cosa stavi provando a fare e dove la board ti si è messa di
traverso — il resto è spiegato in [contribuire](contributing/contributing.md). Una funzione fuori perimetro
si può comunque costruire sopra: le cuciture sono documentate in
[Estendere Griglia](configuration/extending.md).

Quello che è già uscito sta nel [changelog](reference/changelog.md).
