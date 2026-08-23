# Aggiornare Griglia in sicurezza

Usare questo runbook per portare un'applicazione ospite a un'altra release di Griglia. L'operazione cambia
dipendenze installate, schema del database e asset pubblicati: creare prima un backup di applicazione e database.

## Prerequisiti

- eseguire i comandi dalla root Laravel durante una finestra di deploy controllata;
- confermare che la versione target supporti PHP, Laravel e Livewire dell'applicazione;
- leggere le voci interessate nel [changelog](https://github.com/alle80/griglia/blob/master/CHANGELOG.md);
- identificare config o viste pubblicate e la modalità asset `precompiled` o `vite`.

## Procedura

```bash
composer update alle80/griglia
php artisan migrate                                    # le migrazioni sono idempotenti
php artisan vendor:publish --tag=griglia-assets --force # solo in modalità precompilata
```

In modalità `vite`, ricompilare il bundle Vite/Tailwind dell'applicazione invece di pubblicare gli asset. Se
sono state pubblicate viste, confrontare gli override con i nuovi sorgenti prima di riaprire il traffico.

## Versioni

Finché il package resta sullo `0.x`, è il numero **minor** il posto dove possono comparire cambiamenti che
rompono: fissa il vincolo che ti fa stare tranquillo (`^0.89.0`) e leggi il
[CHANGELOG](https://github.com/alle80/griglia/blob/master/CHANGELOG.md) prima di alzarlo. La politica completa
— che cosa è pubblico, come si annunciano le deprecazioni, quali versioni sono supportate — sta in
[Versioni e rilasci](../contributing/releases.md).

## Verificare l'aggiornamento

- **Le viste pubblicate** (`vendor:publish --tag=griglia-views`) non si aggiornano da sole: confrontale con i
  sorgenti del package quando un rilascio tocca l'interfaccia.
- **Gli asset precompilati** vanno ripubblicati con `--force`, altrimenti il browser tiene la build vecchia.
- **Le impostazioni** prendono i nuovi valori di default dalle migrazioni delle settings, quindi lancia
  `migrate` prima di usare le opzioni nuove.
- Rigenerare cache di configurazione e rotte secondo il processo di deploy dell'applicazione.
- Aprire la board, creare un todo usa e getta, cambiarne lo stato ed eliminarlo.
- Eseguire `php artisan griglia:check --all` e confermare che legga la stessa lista.
- Se la release tocca gli allegati, aprirne uno esistente attraverso la rotta autenticata.

## Rollback

Ripristinare il precedente lock file Composer ed eseguire `composer install`, poi recuperare database e asset
pubblicati dal backup. Non invertire manualmente le modifiche allo schema, salvo istruzioni esplicite nel
changelog. Escalare se una migrazione è terminata e non esiste un backup verificato.

## Problemi comuni

| Sintomo | Causa probabile | Azione |
|---|---|---|
| Il nuovo comportamento non compare | cache o asset vecchi | rigenerare cache e bundle interessati |
| L'interfaccia personalizzata perde campi o azioni | override delle viste più vecchi del package | riportare gli override sui sorgenti correnti |
| Composer non seleziona la release target | vincolo ospite o dipendenza transitiva bloccata | analizzare il conflitto senza forzare combinazioni non supportate |
| Gli allegati restituiscono 404 dopo un vecchio upgrade | transizione del disco incompleta | seguire la nota di migrazione qui sotto |

## Disco privato degli allegati (0.71.0)

Il default di `GRIGLIA_ATTACHMENTS_DISK` è passato da `public` a `local`. Le installazioni che hanno già
pubblicato `config/griglia.php` tengono il valore pubblicato: rivedilo in modo esplicito. I nuovi upload sul
disco privato richiedono `GRIGLIA_ATTACHMENTS_VIA_CONTROLLER=true` (il default) e sono raggiungibili solo
attraverso la rotta degli allegati di Griglia, autenticata e delimitata al proprietario. Non esporre
`storage/app/private` con un alias del web server.

I file già salvati su `public` non vengono spostati in automatico. O tieni `GRIGLIA_ATTACHMENTS_DISK=public`
per un po', oppure sposta `attachments/` dalla vecchia radice del disco al disco privato configurato prima di
cambiare. Dopo aver cambiato l'ambiente lancia `php artisan config:clear`; il symlink pubblico `storage` non
serve per gli allegati privati e si può togliere, se nessun'altra parte dell'applicazione lo usa.
