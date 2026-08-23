# Sviluppo

Questa guida è per chi modifica il sorgente del package. Lavorare in un clone con PHP 8.3+ e Composer; Node 22
serve solo per gli asset front-end. Non puntare mai i test al database di un'applicazione.

## Il repository

```
config/         griglia.php (pubblicato nell'applicazione ospite)
database/       migrazioni (tabelle + default delle impostazioni)
docs/           questo sito (vedi «Costruire questo sito»)
resources/      viste, sorgenti css/js, lang/{en,it}, pacchetto di temi di esempio
routes/         le rotte del package
src/            componenti Livewire, modelli, comandi console, classi di supporto
tests/          orchestra/testbench + phpunit
```

## Lavorarci

```bash
composer install
composer qa                        # lint + test + docs:check: tutto il cancello, nell'ordine della CI
composer lint                      # Laravel Pint + Larastan livello 5
composer format                    # applica lo stile invece di segnalarlo
composer test                      # testbench, sqlite in memoria
vendor/bin/testbench serve         # un'applicazione Laravel nuda con il package montato
npm install && npm run build       # asset precompilati → public/build
vendor/bin/testbench griglia:docs-build --strict # il sito della documentazione
```

La suite di regressione del ciclo di revisione si trova in `tests/Feature/ReviewWorkflowTest.php`. Copre sia il
percorso storico senza revisore sia i flussi completi di invio, approvazione, richiesta modifiche e nuovo invio,
incluse le transizioni di stato non valide. `tests/Feature/ReviewUiTest.php` copre l'assegnazione del revisore
opzionale nel modale del task.

La suite copre migrazioni, delimitazione per utente, i componenti Livewire, `griglia:check` /
`griglia:watch`, il registro dei temi e i pacchetti zip, l'allineamento delle traduzioni fra `en` e `it` e
l'evento di broadcast. GitHub Actions verifica l'intera matrice PHP 8.3/8.4 e Laravel 12/13. Job separati provano
le versioni minime supportate delle dipendenze su PHP 8.3 e Laravel 12, eseguono la suite su MySQL 8 e rifiutano
dipendenze Composer con vulnerabilità note tramite `composer audit`; Pint e PHPStan girano in un job lint dedicato. In locale i test continuano a usare SQLite
in memoria, a meno che `GRIGLIA_TEST_DB=mysql` e le normali variabili `DB_*` selezionino un database MySQL.

### La suite non gira mai su un database vero

`RefreshDatabase` e il workbench di Testbench (`workbench: install: true` lancia `migrate:fresh`) cancellano
**tutte le tabelle** della connessione che ricevono, e un processo avviato dentro il container di
un'applicazione eredita le variabili `DB_*` di quell'applicazione: è così che il progetto d'origine ha perso i
dati della board il 22/08/2026. `Alle80\Griglia\Testing\DatabaseGuard`, attivato dal service provider quando il
processo è phpunit o lo scheletro di Testbench, controlla quindi ogni connessione appena viene aperta e
interrompe l'esecuzione se il driver non è SQLite e il nome del database non contiene `test` (`griglia_test`,
quello usato in CI, passa). Usare `GRIGLIA_ALLOW_PROD_DB=1` solo se un database di test non può davvero
rispettare la convenzione.

In pratica: lanciare `vendor/bin/phpunit` dove nessuna variabile `DB_*` punta a un database vivo, e passare a
`vendor/bin/testbench` una connessione esplicita — un blocco `env:` in `testbench.yaml` non ha la meglio sulle
variabili d'ambiente reali:

```bash
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: … vendor/bin/testbench griglia:docs-generate --check
```

I modelli `Todo`, `Checklist`, `Ingredient` e `Question` includono factory del package per test mirati:

```php
$list = Checklist::factory()->create();
$todo = Todo::factory()->for($list)->create();
$ingredient = Ingredient::factory()->for($todo)->create();
$question = Question::factory()->for($todo)->create();
```

I modelli risolvono direttamente il namespace delle factory del package, quindi non serve configurare un resolver
dei nomi nell'applicazione ospite o in Testbench.

`composer lint` esegue prima i controlli di formattazione e poi `vendor/bin/phpstan analyse` su `src/`. Larastan è
configurato al livello 5 senza baseline. Il piccolo elenco di eccezioni in `phpstan-ignores.neon` documenta
singolarmente i limiti di inferenza del framework, ognuna vincolata a un identificatore e a un file;
`reportUnmatchedIgnoredErrors` è attivo, quindi un'eccezione che smette di corrispondere fa fallire l'analisi
invece di trasformarsi silenziosamente in debito permanente. La politica completa, e cosa fare quando un
controllo fallisce, è nella pagina [Standard di qualità](quality.md).

## Rilasciare

Ogni modifica va in `CHANGELOG.md` (Keep a Changelog, con una sezione **Security** quando serve), e il tag
`vX.Y.Z` è il rilascio: Packagist lo pubblica e la GitHub Release viene generata da quella sezione del
changelog. Cosa promette un numero di versione, che cosa è pubblico e i quattro passi per fare un rilascio
stanno in [Versioni e rilasci](releases.md).

Vedi anche [Standard di qualità](quality.md) — l'asticella che una modifica deve superare e che cosa protegge
ogni job della CI — [Contribuire](contributing.md) e [Costruire questo sito](docs-site.md).

## Verificare prima di proporre una modifica

Eseguire `composer qa` (lint, suite, pagine generate) e `composer docs:build` per il sito. Il risultato
atteso è zero errori Pint/PHPStan, suite PHPUnit verde e build strict bilingue. Usare una connessione SQLite
esplicita se la shell eredita variabili `DB_*` dell’applicazione, come descritto sopra. Annotare un prerequisito
mancante invece di dichiarare verificato un controllo non eseguito.
