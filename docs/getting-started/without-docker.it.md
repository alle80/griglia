# Usare Griglia senza Docker

Griglia è un package Laravel: qualunque cosa serva la tua applicazione — `composer dev`, `php artisan serve`,
Apache, nginx con PHP-FPM, un container — serve anche la board. Solo gli **script sull'host** avevano bisogno
di sapere dove sta Artisan, e dalla v0.94.0 se lo scoprono da soli.

Questa pagina è per una macchina dove PHP gira direttamente sull'host, ed elenca cosa cambia rispetto a un
ambiente containerizzato.

## Il trasporto verso Artisan

`sync-context.py`, `sync-skills.py`, `claude-tokens.py`, `agent-status.py` e il worker persistente raggiungono
la board attraverso un **trasporto**:

| `GRIGLIA_TRANSPORT` | Cosa esegue | Quando sceglierlo |
| --- | --- | --- |
| `auto` (default) | `docker exec` se `$GRIGLIA_CONTAINER` è in esecuzione, altrimenti `php artisan` | vuoi che funzioni e basta |
| `docker` | `docker exec <container> php artisan` | l'applicazione vive in un container |
| `local` | `php artisan` dalla radice del progetto | PHP gira su questa macchina |

Con `auto` ogni esecuzione chiede una volta a Docker se il container è su; se manca il binario `docker`, se il
demone non risponde o se il container è fermo il significato è lo stesso — esegui `php artisan` qui, nella
radice del progetto. Su una macchina che non ha mai visto Docker non c'è niente da configurare.

Fissa il trasporto dove la verifica è solo peso (cron, systemd) o dove i due mondi convivono sulla stessa
macchina e vuoi essere sicuro di chi risponde:

```dotenv
GRIGLIA_TRANSPORT=local
GRIGLIA_PHP=/usr/bin/php8.4        # quando `php` non è il binario giusto
GRIGLIA_PROJECT_ROOT=/srv/my-app   # quando gli script non stanno in <progetto>/scripts
```

Quando Artisan non è raggiungibile gli script stampano il trasporto che hanno usato e la variabile che lo
cambia:

```text
Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?
artisan ran through `docker exec laravel-dev-app`: set GRIGLIA_CONTAINER, or GRIGLIA_TRANSPORT=local to use PHP on this machine
```

Il [worker persistente](../agent/workers.md) legge le stesse variabili e accetta
`--transport auto|docker|local` per singola istanza; all'avvio stampa il trasporto che ha risolto.

## Cron e systemd partono da un ambiente vuoto

Gli script sono fatti per girare da soli, e né cron né systemd ereditano la shell dove hai esportato le tue
variabili. Dai a entrambi i percorsi assoluti e le poche variabili che servono:

```cron
* * * * * cd /srv/my-app && GRIGLIA_TRANSPORT=local /usr/bin/python3 scripts/sync-context.py -q
*/5 * * * * cd /srv/my-app && GRIGLIA_TRANSPORT=local /usr/bin/python3 scripts/agent-status.py -q
```

Per l'unità del worker le stesse variabili vanno nel suo `EnvironmentFile`
(`~/.config/griglia-worker/<chiave-agente>.env`).

## Cos'altro cambia

**Proprietà dei file.** Gli script eseguono Artisan come il *tuo* utente e scrivono dentro
`storage/app/griglia` (`skills.json`, `agent-status.json`, il marcatore dell'ultimo check); il processo web
scrive gli stessi file come utente proprio. Con Docker il `-u www-data` nascondeva il problema. Qui: o esegui
gli script come utente del web (`sudo -u www-data python3 scripts/sync-skills.py`), oppure metti i due utenti
nello stesso gruppo e tieni la cartella scrivibile dal gruppo:

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R g+rwXs storage bootstrap/cache
```

**Lo scheduler.** `griglia:auto-archive` (ogni giorno alle 03:30) gira solo se gira lo scheduler di Laravel:
una riga di cron, oppure `php artisan schedule:work` in sviluppo:

```cron
* * * * * cd /srv/my-app && php artisan schedule:run >> /dev/null 2>&1
```

**Aggiornamenti in tempo reale.** Il broadcasting resta opzionale: senza un broadcaster la board funziona,
semplicemente non si aggiorna da sola. Dove invece usi Reverb, riavvialo dopo ogni aggiornamento del package
(`php artisan reverb:restart`) — altrimenti quel processo tiene in memoria il codice vecchio.

**Cache.** Un container che rifà `config:cache` a ogni avvio perdona una cache vecchia; un host nudo no. Dopo
aver modificato `.env` o `config/griglia.php` lancia `php artisan config:cache`, e `php artisan route:cache`
dopo aver cambiato `route_prefix` — un `404` su una rotta di Griglia è quasi sempre questo.

**Asset.** Pubblicare CSS e JS compilati è come altrove, e va rifatto a ogni aggiornamento:
`php artisan vendor:publish --tag=griglia-assets --force` — vedi [Asset front-end](assets.md).

**Le notifiche** partono in linea, senza `ShouldQueue`: non serve un worker di coda. Il Web Push ha comunque
bisogno di `/griglia-sw.js` servito dalla radice del sito, cosa che fa la rotta del package finché la document
root è `public/`.

**Il file di istruzioni.** `AGENTS.md` dice all'agente come chiamare Artisan. Su un host senza container i
comandi sono semplicemente `php artisan griglia:check` — controlla come è scritto dopo averlo pubblicato, vedi
[Avviare l'agente](../agent/running.md).
