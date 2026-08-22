# Installare Griglia in un'applicazione Laravel

Questo tutorial aggiunge Griglia a un'applicazione Laravel esistente e termina quando un utente autenticato apre
una board funzionante. Il percorso richiesto dura circa dieci minuti; le integrazioni opzionali possono seguire.

## Prima di iniziare

- PHP 8.3+, Laravel 12 o 13, Livewire 4.4+, Composer e un database configurato
- `ext-gd`, `ext-fileinfo` ed `ext-zip`
- un flusso di autenticazione funzionante e almeno un utente per la modalità `server` predefinita
- la root dell'applicazione Laravel ospite come directory corrente

Creare un backup prima di cambiare dipendenze o applicare migrazioni a un'applicazione esistente.

## 1. Installare il package

```bash
composer require alle80/griglia -W
```

`-W` permette a Composer di aggiornare le dipendenze transitive richieste da Web Push. Composer pubblica anche
gli asset precompilati tramite il tag Laravel `laravel-assets`. Un'esecuzione riuscita aggiunge
`alle80/griglia` a `composer.json` e termina senza conflitti.

## 2. Creare tabelle e impostazioni

```bash
php artisan migrate
```

Le migrazioni sono idempotenti e creano, quando mancanti, dati della board, impostazioni, notifiche e
sottoscrizioni push.

## 3. Aprire la board

Accedere all'applicazione ospite e aprire `/`. Griglia deve mostrare una prima lista. Le rotte usano il middleware
`web` e il middleware di accesso di Griglia; in modalità `server` una richiesta non autenticata viene reindirizzata
al login dell'applicazione.

Se `/` appartiene già all'applicazione ospite, pubblicare `griglia.php`, disattivare `home_route` e usare la rotta
dashboard configurata. Consultare [accessi e modalità](../configuration/access.md) per gate, amministratori e
modalità locale.

## 4. Collegare un agente

```bash
php artisan vendor:publish --tag=griglia-agents
```

Il comando scrive il workflow portabile `AGENTS.md` nella root del progetto. Creare o rinominare una lista in
modo che corrisponda a `GRIGLIA_AGENT_LIST` (`dev` per default), avviare l'agente in quella directory, poi eseguire:

```bash
php artisan griglia:check
```

Risultato atteso: il comando stampa le impostazioni di comportamento e gli elementi aperti o in lavorazione.

## Verificare l'installazione

```bash
php artisan route:list --name=griglia
php artisan griglia:check --all
```

Verificare che le rotte siano presenti, che la board si apra per l'utente previsto e che la CLI legga la stessa
lista. Completare il [quickstart](quickstart.md) per provare l'intero ciclo di una richiesta.

## Integrazioni opzionali

- [Asset front-end](assets.md): passare dai file precompilati alla build Vite dell'applicazione.
- [Aggiornamenti live e notifiche](../features/notifications.md): configurare broadcaster e Web Push.
- [Funzioni AI](../features/ai.md): attivare piani, trascrizione e descrizione immagini.
- [Temi](../features/themes.md): scegliere o installare un tema grafico.

### Aggiornamenti dal vivo (facoltativo)

Configurare un broadcaster solo dopo che l'installazione richiesta funziona. Setup e verifica canonici sono in
[notifiche](../features/notifications.md).

### Web Push (facoltativo)

Web Push richiede HTTPS, chiavi VAPID e il trait di sottoscrizione sul modello utente. Seguire la stessa
[guida alle notifiche](../features/notifications.md) senza duplicare qui la procedura.

## Problemi comuni

| Sintomo | Causa probabile | Azione |
|---|---|---|
| Composer segnala un conflitto su `brick/math` | dipendenze transitive bloccate | ripetere il comando con `-W` |
| `/` reindirizza al login | protezione normale della modalità `server` | autenticarsi o configurare l'accesso intenzionalmente |
| `/` restituisce 404 dopo l'installazione | cache delle rotte vecchia | eseguire `php artisan route:cache` o pulire la cache durante il setup |
| CSS o JavaScript mancano | asset non pubblicati o modalità incoerente | ripubblicare `laravel-assets` o seguire la guida Vite |
| La lista dell'agente è vuota | il nome non coincide con `GRIGLIA_AGENT_LIST` | rinominare la lista o aggiornare la configurazione |
