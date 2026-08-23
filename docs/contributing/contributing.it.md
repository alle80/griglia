# Contribuire

Issue e pull request sono benvenute su
[github.com/alle80/griglia](https://github.com/alle80/griglia). Questa pagina è il *come*: che cosa mandare,
che cosa deve portare con sé una modifica e che cosa succede dopo che l'hai aperta. Il *che cosa* e il *chi* —
missione, perimetro, ruoli, versioni supportate e tempi di risposta — stanno nella pagina
[Governance](governance.md). Leggi prima quella se la tua modifica è più grande di una correzione: quasi tutto
ciò che viene rifiutato lo è per il perimetro, non per la qualità.

Griglia è mantenuta da una persona sola, nel tempo libero. Un contributo breve e completo viene integrato; uno
grande di cui nessuno ha parlato prima, di solito, aspetta.

## Modi per contribuire

| Se hai | Manda |
|---|---|
| Un bug | Una issue con una riproduzione — vedi [Segnalare un bug](#segnalare-un-bug) |
| Un'idea | Una issue che descrive il problema prima della soluzione — vedi [Proporre una modifica](#proporre-una-modifica) |
| Una correzione o una piccola funzione | Una pull request, test compresi |
| Una funzione più grande | Prima una issue, poi la pull request |
| Un buco nella documentazione, un refuso, una frase italiana migliore | Una pull request su `docs/` — stesse regole, senza i test |
| Del tempo | Rispondi alla issue di qualcun altro, o conferma un bug sulla tua installazione |

GitHub ti apre il modulo giusto: **New issue** propone una segnalazione di bug, un'idea, un problema di
documentazione e una domanda, e ogni modulo chiede esattamente quello che descrivono le sezioni qui sotto. Le
issue vuote sono disattivate: se nessuno dei quattro moduli calza, prendi il più vicino e scrivi nella prima
riga di che cosa si tratta davvero. Le pull request arrivano precompilate con il
[template](https://github.com/alle80/griglia/blob/master/.github/PULL_REQUEST_TEMPLATE.md): il problema, che
cosa è cambiato, come l'hai provato e la checklist in fondo a questa pagina.

La vulnerabilità è l'eccezione: mai una issue pubblica, mai una pull request che la descrive — segui la
[politica di sicurezza](../operations/security.md).

## Prima di scrivere codice

1. **Cerca tra le [issue](https://github.com/alle80/griglia/issues)**, aperte e chiuse. La risposta potrebbe
   già esserci, compreso un «è fuori perimetro».
2. **Controlla la tabella del perimetro** in [Governance](governance.md#perimetro). Tutto ciò che appartiene
   all'applicazione ospite — autenticazione, gestione degli utenti, il layout della tua app — non fa parte del
   package, per quanto utile sia.
3. **Apri una issue per qualsiasi cosa più grande di una correzione** e aspetta una direzione. Una pull
   request che arriva senza issue può essere chiusa solo per perimetro, ed è un brutto modo di spendere una
   serata.
4. **Dì che ci stai già lavorando.** Un commento sulla issue evita che due persone scrivano la stessa patch.

## Segnalare un bug

Una buona segnalazione è una che un altro riesce a riprodurre. Metti dentro:

- la **versione di Griglia** (`composer show alle80/griglia`), quelle di **Laravel** e **PHP**;
- la **modalità** (`server` o `local`) e, se conta, se sei dietro un proxy o dentro Docker;
- **che cosa hai fatto, che cosa ti aspettavi, che cosa è successo** — in quest'ordine, tre righe l'una
  bastano;
- l'**errore**: l'eccezione con lo stack trace, la console del browser per un problema di front-end, l'output
  del comando per uno lato agente (`php artisan griglia:check --all`);
- uno **screenshot** per qualsiasi cosa visiva, con il tema o lo stile che stavi usando.

La segnalazione migliore porta con sé una **riproduzione minima**: un'applicazione Laravel nuova con il
package installato, oppure un test che fallisce su `main`. Se non riesci a riprodurlo fuori dalla tua
applicazione, scrivilo: una segnalazione con un onesto «succede solo da me» vale comunque la pena di essere
aperta.

Il modulo **Bug report** chiede questo elenco un campo alla volta: compilarlo dall'alto in basso è il modo più
rapido di scrivere una segnalazione su cui nessuno debba tornare a fare domande.

## Proporre una modifica

Descrivi prima il **problema**: che cosa stavi facendo, che cosa ti ha costretto a fare la board, quanto
spesso. Poi proponi una soluzione e dì che cosa hai già escluso — un'impostazione che esiste, un tema, uno
script lato host.

Due vincoli decidono molte proposte prima ancora che si scriva il codice:

- **Il contratto con l'agente resta neutro.** La board parla con qualsiasi agente CLI attraverso
  `griglia:check` e `griglia:watch`. Niente può fissare nel codice un agente, un modello o un fornitore: le
  stringhe visibili usano il segnaposto `:agent`, mai un nome di prodotto.
- **Le funzioni AI sono facoltative e disattivate di default.** Il package non chiama da solo un fornitore di
  modelli.

Il modulo **Idea or feature request** ti fa dichiarare l'area a cui appartiene la modifica e confermare questi
due vincoli prima di inviarla: sono le domande che altrimenti ti tornerebbero indietro una settimana dopo.

Se la tua modifica aggiunge un'impostazione, spiega perché quelle esistenti non bastano: la pagina delle
impostazioni è una superficie che va documentata, tradotta e mantenuta funzionante per sempre.

## Preparare l'ambiente

Servono PHP 8.3+ e Composer; Node 22 solo se tocchi gli asset di front-end.

```bash
git clone https://github.com/alle80/griglia.git
cd griglia
composer install
composer test          # phpunit via orchestra/testbench, SQLite in memoria
composer lint          # Laravel Pint + Larastan livello 5
```

`vendor/bin/testbench serve` ti dà un'applicazione Laravel spoglia con il package montato: è il modo più
rapido per guardare quello che hai cambiato. La [guida allo sviluppo](development.md) contiene la mappa del
repository, le ricette per Testbench e MySQL, le factory e — questa leggila prima di lanciare qualsiasi cosa —
**perché la suite non deve mai puntare a un database vero**.

## Che cosa deve portare con sé una modifica

### Codice

- **Lo stile non si discute**: `composer lint` esegue Pint e PHPStan (Larastan livello 5, senza baseline).
  Devono essere puliti entrambi. `vendor/bin/pint` sistema la formattazione al posto tuo.
- **Segui il codice che hai intorno.** Convenzioni Laravel, classi piccole, niente framework personali.
- **Nessuna dipendenza nuova** senza una ragione scritta nella pull request. Poche righe battono un package.
- **Non rinominare i nomi storici.** I sotto-task sono `Ingredient` nel codice e nel database, le liste sono
  `Checklist`: stanno nel [glossario](../glossary.md), e rinominarli è una migrazione, non una pulizia.
- **Le migrazioni si aggiungono.** Questo package gira su database che contengono i task di qualcuno:
  aggiungi colonne con un default, non riscrivere una migrazione già rilasciata.
- **L'interfaccia segue lo stile della board**: il set di icone (`<x-griglia::icon name="…">`), le variabili
  degli skin dei temi e i componenti che esistono — non emoji per stati o azioni, non markup improvvisato.

### Test

Ogni comportamento che aggiungi o correggi ha un test in `tests/Feature`, accanto a quelli della stessa area
(`GrigliaCheckCommandTest`, `ReviewWorkflowTest`, `ThemesTest`…). La regola che conta: **il test deve fallire
senza la tua modifica** — verificalo mettendo da parte la correzione e rilanciandolo. I modelli hanno le
factory (`Todo`, `Checklist`, `Ingredient`, `Question`), quindi preparare i dati costa tre righe.

### Traduzioni

La lingua base è l'inglese (`resources/lang/en`) e `resources/lang/it` deve restare allineata: un test
fallisce quando una chiave esiste in una sola delle due. Stessa regola per la documentazione: ogni `pagina.md`
ha la sua `pagina.it.md`, e un test rifiuta una pagina italiana che sia una copia di quella inglese. Se non
parli italiano, scrivilo nella pull request e lascia la traduzione al manutentore: non tradurre a macchina una
pagina che non sai rileggere.

### Documentazione

La documentazione cambia **nello stesso commit** del codice. Una modifica visibile all'utente aggiorna la sua
pagina sotto `docs/` in entrambe le lingue; una rotta, un comando o un passo di installazione nuovi aggiornano
anche il `README.md`.

Le tre pagine di reference (`docs/reference/commands.md`, `config.md`, `settings.md`) sono **generate**:
modifica il comando, il file di configurazione o la classe delle impostazioni, poi lancia

```bash
vendor/bin/testbench griglia:docs-generate        # rigenera le pagine di reference
vendor/bin/testbench griglia:docs-build --strict  # costruisce il sito bilingue, i warning sono errori
```

La CI esegue `griglia:docs-generate --check` e fallisce se le pagine generate sono vecchie.

### CHANGELOG

Una voce sotto **Unreleased** in
[`CHANGELOG.md`](https://github.com/alle80/griglia/blob/master/CHANGELOG.md), nel formato
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) (*Added*, *Changed*, *Fixed*, *Removed*, *Security*,
*Documentation*). Scrivila per chi aggiorna: che cosa cambia per lui e, se serve, il passo di migrazione.

## Commit, branch e pull request

- **Parti da un `master` aggiornato**, una modifica logica per branch. Mai appoggiare il tuo branch su quello
  di un altro contributore.
- **Messaggi di commit in inglese**, con un prefisso conventional — `feat:`, `fix:`, `docs:`, `refactor:`,
  `test:`, `chore:` — e un oggetto che dice l'effetto, non il file.
- **Tieni il branch integrabile**: quando `master` si muove, riportalo dentro. Il force-push va bene prima
  della prima review; dopo, meglio aggiungere commit, così i commenti restano attaccati al loro contesto.
- **Apri la pull request** con: il problema, che cosa hai cambiato, come l'hai provato e `Closes #123` se
  esiste una issue. Screenshot (prima/dopo) per qualsiasi cosa visiva.

Da controllare prima di premere il bottone — è la stessa lista che il template della pull request ti dà già
da spuntare:

- [ ] `composer lint` è pulito
- [ ] `composer test` è verde
- [ ] `vendor/bin/testbench griglia:docs-build --strict` costruisce
- [ ] c'è un test che fallisce senza la modifica
- [ ] documentazione aggiornata, inglese **e** italiano
- [ ] una voce nel `CHANGELOG.md` sotto *Unreleased*
- [ ] nessun nome di agente fissato nel codice, nessuna dipendenza nuova senza ragione

## Che cosa succede dopo

La CI esegue la suite sulla matrice PHP 8.3/8.4 × Laravel 12/13, più job separati per le versioni minime
supportate delle dipendenze, MySQL 8, `composer audit`, Pint/PHPStan e la costruzione stretta della
documentazione. Una matrice rossa tocca a te; un job rosso che non riesci a riprodurre merita un commento —
a volte la colpa è del progetto.

Poi la legge una persona. La prima review arriva in circa due settimane
([tempi di risposta](governance.md#tempi-di-risposta)) ed è una conversazione: aspettati domande su perimetro
e nomi prima di ogni altra cosa. Il silenzio oltre quei tempi non è un rifiuto: fai un sollecito sulla pull
request.

Il lavoro accettato viene integrato dal manutentore, di solito con uno squash e con la tua paternità intatta.
Esce con il rilascio successivo: un tag `vX.Y.Z` è ciò che Packagist pubblica, la voce *Unreleased* si sposta
sotto quella versione e il sito della documentazione viene ricostruito da `master`. I rilasci si fanno quando
c'è qualcosa che vale la pena rilasciare, non a calendario.

Se la modifica viene rifiutata, la ragione è scritta nella pull request. I rifiuti per perimetro arrivano
spesso con una casa migliore per quel lavoro: un punto di estensione, uno script lato host o un package tuo.

## Contributi scritti con un agente

Griglia esiste per rendere osservabile il lavoro fatto con un agente, quindi le pull request scritte con un
agente sono benvenute e non hanno bisogno di dichiarazioni. Una regola sola: **chi apre la pull request ne
risponde.** Leggi il diff, verifica che i test falliscano senza la modifica e non incollare mai documentazione
generata che non hai confrontato con il codice. Una prosa che descrive un comportamento che il codice non ha è
peggio di una pagina mancante.

## Come ci si parla

Le review parlano del codice e sono dirette: aspettati «questo sta nell'applicazione ospite» invece di un
paragrafo di imbottitura. Lo stesso rispetto vale in senso opposto: niente disprezzo, niente battute
sull'installazione o sulla lingua di qualcuno, niente ridiscussioni di decisioni che hanno già una ragione
scritta. Il manutentore modera e, se serve, chiude i thread che smettono di essere tecnici.

Messo per iscritto, tutto questo è il [Contributor Covenant 2.1](code-of-conduct.md), che il progetto adotta
così com'è: che cosa copre, che cosa no e come viene gestita una segnalazione stanno nella pagina
[Codice di condotta](code-of-conduct.md).

## Licenza

Griglia è distribuita con licenza MIT e **anche quello che contribuisci è MIT**: nessun accordo di licenza da
firmare, nessuna cessione di copyright, nessun sign-off. Aprire una pull request è il tuo consenso a
pubblicare quel lavoro alle condizioni di
[`LICENSE`](https://github.com/alle80/griglia/blob/master/LICENSE). Se la modifica porta con sé codice di
terzi, dichiarane origine e licenza nella pull request — vedi [Licenza](license.md).

## Sicurezza

Per una vulnerabilità non aprire una issue pubblica — vedi [Sicurezza](../operations/security.md).
