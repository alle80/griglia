# Standard di qualità

Questa pagina è l'asticella che una modifica deve superare per entrare, e l'elenco di ciò che la controlla al
posto della memoria di una persona. Se vuoi solo i comandi: lancia `composer qa` prima di aprire una pull
request; il resto della pagina spiega che cosa protegge ogni controllo e che cosa resta volutamente alla
revisione umana.

## L'asticella

Sette regole, nell'ordine in cui di solito si infrangono.

| # | Regola | Chi la controlla |
|---|---|---|
| Q1 | La suite è verde su ogni combinazione supportata — PHP 8.3 e 8.4, Laravel 12 e 13, dipendenze più vecchie e più nuove, SQLite e MySQL | CI (`tests`, `prefer-lowest`, `mysql`) |
| Q2 | Una correzione porta con sé un test che senza di lei fallisce; una funzione porta il test del comportamento che promette | la revisione |
| Q3 | Zero differenze per Pint e zero errori PHPStan al livello 5, senza baseline | `composer lint`, CI (`lint`) |
| Q4 | Ogni stringa visibile è tradotta in `en` **e** in `it`, e non nomina mai un agente: `:agent`, mai «Claude» | `TranslationsTest`, `DocsTranslationsTest` |
| Q5 | La documentazione arriva nello stesso commit, in entrambe le lingue, e le pagine di reference generate corrispondono al codice | `composer docs:check`, CI, `DocsGenerateTest` |
| Q6 | Ogni modifica ha una voce in `CHANGELOG.md` sotto *Unreleased* | la revisione, il template di PR |
| Q7 | Nessuna dipendenza con vulnerabilità note, e nessuna dipendenza aggiunta senza una ragione scritta nella pull request | CI (`security`), la revisione |

Q2 e Q6 sono le due che una macchina non può giudicare: per questo sono le due che il
[template di pull request](https://github.com/alle80/griglia/blob/master/.github/PULL_REQUEST_TEMPLATE.md) ti
chiede di confermare a mano.

## I controlli che puoi lanciare

```bash
composer qa            # lint + test + docs:check — quello che fa la CI, nello stesso ordine
composer lint          # Pint --test, poi PHPStan livello 5 su src/
composer format        # applica lo stile invece di segnalarlo
composer test          # la suite PHPUnit via Testbench, SQLite in memoria
composer test:coverage # la suite più il rapporto di copertura (serve pcov o xdebug)
composer docs:check    # le pagine di reference generate corrispondono ancora al codice
composer docs:build    # il sito bilingue, con i warning trattati come errori (serve MkDocs)
```

A `composer qa` bastano PHP e Composer, ed è per questo che è lui il cancello: la build stretta del sito è un
comando a parte perché richiede la catena Python di [Costruire questo sito](docs-site.md). Lancia la suite
dove nessuna variabile `DB_*` punta a un database vivo — la [guida allo
sviluppo](development.md#la-suite-non-gira-mai-su-un-database-vero) spiega la guardia che lo impedisce, e
perché esiste.

## Che cosa gira su GitHub

Tutto quello che segue gira su ogni pull request e su ogni push su `master`
([`tests.yml`](https://github.com/alle80/griglia/blob/master/.github/workflows/tests.yml)). Un'esecuzione
superata da un nuovo push sullo stesso branch viene annullata; su `master` non viene mai annullata, così ogni
commit del branch principale conserva il suo verdetto. Il token del workflow è di sola lettura.

| Job | Che cosa protegge | In locale |
|---|---|---|
| `PHP × Laravel` (4 job) | la matrice supportata, e le pagine generate | `composer test`, `composer docs:check` |
| `prefer-lowest` | il limite *inferiore* di ogni vincolo — la versione che un'applicazione prudente installa davvero | `composer update --prefer-lowest --prefer-stable && composer test` |
| `lint` | stile e analisi statica | `composer lint` |
| `mysql` | le query che SQLite perdona e MySQL 8 no | `GRIGLIA_TEST_DB=mysql … composer test` |
| `security` | vulnerabilità note nell'albero delle dipendenze | `composer audit` |

[`docs.yml`](https://github.com/alle80/griglia/blob/master/.github/workflows/docs.yml) pubblica il sito su
GitHub Pages a ogni push su `master` che tocca la documentazione. Costruisce con `--strict`, quindi un link
rotto fa fallire la pubblicazione invece di finire online.

## Politica sull'analisi statica

PHPStan gira al **livello 5** su `src/`, con Larastan, e **senza baseline**. Una baseline nasconde il debito
esistente dietro una spunta verde e cresce in silenzio; l'alternativa è la piccola lista di eccezioni in
`phpstan-ignores.neon`, ed è scomoda apposta:

- ogni voce è vincolata a un identificatore di errore **e** a un file, quindi non può coprire di nascosto un
  problema diverso da un'altra parte;
- `reportUnmatchedIgnoredErrors` resta attivo, quindi un'eccezione che smette di corrispondere — perché il
  codice è stato sistemato o il file è stato cancellato — **fa fallire** l'analisi invece di diventare debito
  permanente;
- ogni voce dichiara quale limite di inferenza del framework sta aggirando, nel commento sopra il suo gruppo.

Alzare il livello è benvenuto come pull request a sé, mai come effetto collaterale di una funzione.

## Test

La suite gira con `orchestra/testbench` su SQLite in memoria e copre migrazioni, scoping per utente, i
componenti Livewire, i contratti di `griglia:check` e `griglia:watch`, piani e revisioni, temi, impostazioni,
notifiche, parità delle traduzioni e i file di community del repository stesso.

- **Una correzione comincia da un test che fallisce.** «Non è testabile» di solito vuol dire che la cucitura è
  nel posto sbagliato: scrivilo nella pull request e se ne discute, invece di passarci sopra.
- **Il contratto dei comandi è un'API pubblica.** L'output e le opzioni di `griglia:check` sono ciò su cui
  ogni agente si appoggia: cambiarne una riga significa cambiare `GrigliaCheckCommandTest` apposta, mai per
  distrazione.
- **Anche i file del repository hanno i loro test.** `CONTRIBUTING.md`, il codice di condotta, i moduli per le
  issue, gli script e questo impianto di qualità: un modulo YAML rotto è silenzioso — GitHub semplicemente
  smette di proporlo.
- **Nessun test tocca un database vero.** `DatabaseGuard` interrompe qualsiasi connessione che non sia SQLite
  o un database il cui nome contenga `test`.

### Copertura

La copertura è uno **strumento, non un cancello**. `composer test:coverage` la stampa e scrive un rapporto
HTML in `build/coverage`, che è il modo giusto per scovare un ramo che ti sei dimenticato di testare. Serve
pcov o xdebug; mentre scriviamo la suite copre circa l'83% delle righe di `src/`. In CI
non c'è una percentuale minima, e non è una dimenticanza: il numero è facile da alzare senza testare niente
(un test che attraversa una classe senza asserire nulla conta lo stesso), e una soglia trasforma l'attenzione
della revisione in aritmetica. Quello che si pretende davvero è Q2 — una modifica arriva con un test che
senza di lei fallisce — cioè la proprietà di cui la percentuale è solo un indizio.

## Dipendenze

- I vincoli di runtime restano **larghi** (`^12.0|^13.0`): li risolve l'applicazione ospite, quindi
  restringerne uno è una rottura per qualcuno. `prefer-lowest` in CI è ciò che tiene onesto il limite
  inferiore.
- Una nuova dipendenza di runtime richiede una ragione nella pull request; ciò che è utile ma non necessario
  va in `suggest`, come `laravel/ai` e `laravel/reverb`.
- [Dependabot](https://github.com/alle80/griglia/blob/master/.github/dependabot.yml) apre pull request
  raggruppate ogni settimana per le dipendenze di **sviluppo** e per le GitHub Actions, e ogni mese per la
  catena front-end. Non tocca mai i vincoli di runtime. Le sue pull request passano dalla stessa matrice di
  tutte le altre.
- `composer audit` fa fallire la build su una vulnerabilità nota. Una vulnerabilità di Griglia, invece, non è
  una issue: vedi [Sicurezza](../operations/security.md).

## Stile e convenzioni

- **PHP**: Laravel Pint, preset `laravel`, nessuna deroga locale. `composer format` corregge, la CI si limita
  a controllare.
- **I commenti spiegano il perché**, in inglese, e devono valere la riga che occupano: qui si preferisce un
  paragrafo che spiega una trappola a cinque righe che riscrivono il codice.
- **Inglese nel repository**, italiano solo nelle pagine `.it.md` del sito. Un test passa in rassegna i
  commenti di `src/`, `scripts/`, le viste e i fogli di stile in cerca di parole italiane e fallisce alla
  prima (`QualityStandardsTest`).
- **Commit** in inglese con prefisso convenzionale (`feat:`, `fix:`, `docs:`, `chore:`, `ci:`, `refactor:`),
  una modifica logica per branch.
- **Nessun nome di agente nelle stringhe**: la board è neutrale rispetto all'agente, e il segnaposto è
  `:agent`.

## Quando un controllo fallisce

| Sintomo | Di solito è |
|---|---|
| fallisce `docs:check` | hai cambiato un comando, una chiave di configurazione o un'impostazione: rilancia `vendor/bin/testbench griglia:docs-generate` e committa le pagine |
| fallisce solo `prefer-lowest` | hai usato qualcosa che esiste nella dipendenza più nuova ma non nella più vecchia che dichiari |
| fallisce solo `mysql` | strict mode, una colonna ambigua, un `GROUP BY`, o una lunghezza di colonna che SQLite ignora |
| fallisce una sola versione di PHP | una deprecazione legata a quella versione |
| PHPStan fallisce dopo un rebase | una voce di `phpstan-ignores.neon` non corrisponde più: cancellala, non alzarle il `count` |

Niente di tutto questo è burocrazia fine a sé stessa: ogni regola esiste perché la sua assenza è già costata
tempo a qualcuno. Se una di loro ostacola una buona modifica, scrivilo nella pull request — lo standard si può
spostare, saltarlo in silenzio no.

Vedi anche [Contribuire](contributing.md), [Sviluppo](development.md) e [Governance](governance.md).
