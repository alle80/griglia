# Licenza

Griglia è distribuita con licenza **MIT** — la stessa di Laravel, Livewire e Tailwind. Il testo completo sta in
[`LICENSE`](https://github.com/alle80/griglia/blob/master/LICENSE) nella radice del repository, e
`composer.json` dichiara `"license": "MIT"`, così Packagist e qualsiasi scanner di licenze la leggono anche dai
metadati.

Questa pagina esiste perché la scritta «MIT» su un badge non dice cosa puoi farci, cosa il progetto chiede a
chi contribuisce e quali licenze di terze parti arrivano insieme al codice.

## Cosa puoi fare

| Puoi | A questa condizione |
|---|---|
| Usare Griglia in un prodotto, commerciale o no, a sorgente chiuso o no | Mantenere l'avviso di copyright e di licenza nelle copie che distribuisci |
| Farne un fork, modificarla, rinominarla, toglierne pezzi | Lo stesso avviso |
| Ridistribuirla, venderla, sublicenziarla, includerla in un altro package | Lo stesso avviso |
| Usarla in privato senza pubblicare nulla | Niente |

Il patto è tutto qui: nessun copyleft, nessun obbligo di pubblicare le tue modifiche, nessun accordo da
firmare, nessun costo, nessuna comunicazione dovuta. In cambio la licenza non dà **nessuna garanzia e nessuna
responsabilità**: come si comporta Griglia sulla tua infrastruttura, e i backup del database su cui scrive,
restano a carico dell'applicazione che la ospita.

## Perché MIT

La scelta vera era tra restare permissivi e chiedere qualcosa in cambio. Griglia è un package che si installa
*dentro* la tua applicazione, accanto al tuo codice: una licenza che mette condizioni sull'applicazione
intorno renderebbe la board più difficile da adottare di quanto sia da scrivere.

| Alternativa | Perché no |
|---|---|
| **MIT** — scelta | Il testo permissivo più breve e più riconoscibile, ed è la licenza di tutto ciò su cui Griglia è costruita: chi la ospita non deve mai far quadrare due insiemi di condizioni |
| Apache-2.0 | Permissiva anche lei, con concessione esplicita dei brevetti — ma anche intestazioni in ogni file e un `NOTICE` da mantenere: cerimonia che una board con un solo maintainer non ripaga |
| BSD-2-Clause / BSD-3-Clause | Nella pratica equivalenti; MIT è la convenzione nell'ecosistema PHP e Laravel |
| GPL / AGPL | Il copyleft attaccherebbe condizioni all'applicazione che incorpora Griglia — il contrario di quello che dovrebbe costarti un `composer require` |
| Nessuna licenza | «Pubblico su GitHub» non è un permesso: senza licenza nessuno può legalmente usare, copiare o mettere in produzione il codice |

## Cosa copre la licenza

Tutto quello che sta nel repository: i sorgenti PHP e Blade, le migrazioni, CSS e JavaScript, gli asset
compilati in `public/build/`, le traduzioni, i pacchetti dei temi, le pagine di documentazione che stai
leggendo e le immagini del marchio in `public/images/brand/`.

Una cortesia che la licenza non impone: il nome «Griglia» e il suo logo identificano *questo* progetto. Usali
per dire che il tuo lavoro è basato su Griglia, non in modo da far credere che un fork sia Griglia o che il
maintainer lo appoggi.

## Ridistribuire gli asset compilati

`public/build/griglia.js` è un bundle: SortableJS, Laravel Echo e Pusher JS finiscono compilati dentro e il
bundler ne toglie i commenti. Tutti e tre sono MIT, quindi ridistribuire il file compilato va bene — se
spedisci il bundle invece di ricompilarlo, portati dietro gli avvisi delle librerie elencate qui sotto.

## Componenti di terze parti

| Componente | Dove sta | Licenza |
|---|---|---|
| `illuminate/*` (Laravel) | Dipendenza a runtime | MIT |
| `livewire/livewire` | Dipendenza a runtime | MIT |
| `spatie/laravel-settings` | Dipendenza a runtime | MIT |
| `laravel-notification-channels/webpush`, `minishlink/web-push` | Dipendenza a runtime (web push) | MIT |
| `league/commonmark` | Dipendenza a runtime (Markdown in note e commenti) | BSD-3-Clause |
| SortableJS, Laravel Echo, Pusher JS | Compilati dentro `public/build/griglia.js` | MIT |
| Tailwind CSS, Vite | Solo in compilazione | MIT |
| MkDocs Material, mkdocs-static-i18n | Solo per il sito di documentazione | MIT |
| JetBrains Mono | Tipografia del sito di documentazione, caricata da Google Fonts | SIL Open Font License 1.1 |

Sono tutte permissive e compatibili con MIT. `composer licenses` e `npm ls --long` stampano l'elenco aggiornato
comprese le dipendenze indirette; questa tabella è il riassunto che di solito basta a chi deve valutare.

## Contributi

**Quello che entra esce con la stessa licenza**: ciò che contribuisci è licenziato MIT, alle stesse condizioni
del resto del progetto. Non c'è nessun accordo da firmare né cessione del copyright — resti titolare di quello
che scrivi, e aprire una pull request vale come consenso a pubblicarlo a quelle condizioni. Cosa deve portare
con sé una modifica lo dice [Contribuire](contributing.md).

Se una modifica porta con sé codice di terze parti, scrivilo nella pull request: provenienza, licenza e una
riga nella tabella qui sopra. Codice sotto licenza copyleft non può essere accettato, per quanto piccolo.

## Cambiare licenza

Cambiare licenza richiede il consenso di chiunque detenga il copyright sul codice, quindi non è una decisione
che il maintainer possa prendere da solo. L'impegno del progetto è più stretto e più facile da verificare:
**Griglia resta sotto una licenza permissiva approvata dall'OSI.** Il passaggio a un'altra licenza permissiva
si discuterebbe in una issue pubblica prima di toccare qualsiasi file; il passaggio al copyleft è escluso.

## Dove è dichiarata la licenza

| Dove | Cosa dice |
|---|---|
| `LICENSE` | Il testo MIT — copyright 2026 Alessandro (alle80) |
| `composer.json` | `"license": "MIT"`, quello che leggono Packagist e gli scanner SPDX |
| `README.md` e il piè di pagina di questo sito | Un rimando a questa pagina |
