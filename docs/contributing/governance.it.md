# Governance

Questa pagina dice chi decide che cosa succede a Griglia, che cosa il progetto promette e che cosa rifiuta,
quali versioni ricevono correzioni e quanto puoi aspettarti di attendere una risposta. Leggila prima di aprire
una pull request grande: quasi tutto il lavoro respinto viene respinto per il perimetro, non per la qualità.

## Missione

**Griglia dà a chi sviluppa e a un agente CLI un flusso di lavoro condiviso e osservabile dentro
un'applicazione Laravel.** Scrivi una richiesta, decidi quando è pronta e segui l'agente mentre la prende in
carico, fa domande, riporta l'avanzamento e la chiude con un risultato registrato — su infrastruttura tua.

Il progetto insegue cinque obiettivi, in quest'ordine:

1. **Un contratto che funziona con qualunque agente.** Il lato agente sono due comandi artisan
   (`griglia:check`, `griglia:watch`) più un file di istruzioni. Niente nel package dipende da un fornitore,
   un modello o una CLI specifici.
2. **I tuoi dati sulla tua macchina.** Griglia salva task, domande e statistiche nel tuo database. Non chiama
   da sé un fornitore di modelli e ogni funzione AI è opzionale e disattivata di default.
3. **Un percorso breve dall'installazione al primo risultato.** `composer require`, `migrate` e una board che
   gira.
4. **Stato onesto.** Quello che la board mostra — in lavorazione, domanda, percentuale, fase, costo — è quello
   che è successo davvero, non un'animazione.
5. **Documentazione che evita di dover chiedere.** Ogni comportamento visibile all'utente ha una pagina, in
   inglese e in italiano, e le pagine di reference sono generate dal codice.

## Perimetro

| Dentro | Fuori |
|---|---|
| La board, i suoi stati, le liste, i sotto-task, le note e gli allegati | Essere un IDE, un'interfaccia di chat o un modello che scrive codice da solo |
| Il contratto dell'agente: `griglia:check`, `griglia:watch`, contesto, skill, statistiche | Distribuire o includere un agente CLI, o i prompt per usarlo |
| Aggiornamenti dal vivo, notifiche, piani, temi, impostazioni e sito di documentazione | Un servizio ospitato, un'istanza demo pubblica o la gestione degli account |
| Gli script per l'host pubblicati con `vendor:publish --tag=griglia-scripts` | Tutto ciò che appartiene all'applicazione ospite: autenticazione, amministrazione degli utenti, layout |

Una richiesta fuori perimetro non è un fallimento: di solito è un punto di estensione, una responsabilità
dell'applicazione ospite o un package separato. Il maintainer dice quale, nella issue.

## Ruoli

| Ruolo | Chi | Che cosa può fare |
|---|---|---|
| **Maintainer** | Alessandro ([@alle80](https://github.com/alle80)) | Decide perimetro e progetto, revisiona e integra, rilascia, tiene la roadmap |
| **Contributore** | Chiunque apra una issue o una pull request | Propone, discute, implementa; resta l'autore e ne ha il credito |
| **Agente CLI** | Un agente che lavora per un contributore | Scrive codice e documentazione sotto la responsabilità di chi apre la pull request |

Oggi il maintainer è uno solo, e il progetto lo dichiara invece di far finta di essere un comitato. La porta ai
co-maintainer è aperta: a un contributore con una storia continuativa di modifiche integrate e di review può
essere proposto il diritto di scrittura, e la cosa viene registrata qui.

## Come si decide

Decide il maintainer, allo scoperto, con la motivazione scritta nella issue o nella pull request.

1. **Prima si discute, poi si costruisce.** Per qualcosa di più di una correzione, apri prima una issue e
   descrivi il problema prima della soluzione. Una pull request che arriva senza issue può essere chiusa per
   il solo perimetro.
2. **Una modifica è accettata quando** sta nel perimetro qui sopra, non rompe il comportamento esistente (o ne
   documenta la rottura), porta test, documentazione e una voce nel `CHANGELOG.md`, e passa la CI — vedi
   [Contribuire](contributing.md).
3. **Una modifica è rifiutata quando** appartiene all'applicazione ospite, aggiunge una dipendenza che poche
   righe avrebbero sostituito, incastra nel codice un agente, una lingua o un fornitore, oppure allarga la
   superficie più in fretta di quanto la documentazione riesca a seguire.
4. **I disaccordi** si risolvono prima con gli argomenti. Se restano, decide il maintainer e scrive perché: la
   issue resta aperta finché quella motivazione non c'è.
5. **Il silenzio non è un rifiuto.** Se una issue o una pull request non ha risposta oltre i tempi qui sotto,
   sollecitala.

## Versioni supportate

| Versione | Stato |
|---|---|
| L'ultima minor `0.x` | Supportata: correzioni, patch di sicurezza e documentazione |
| Ogni versione precedente | Non supportata — nessun backport |

Griglia è pre-1.0 e segue il [versionamento semantico](https://semver.org/spec/v2.0.0.html) con le regole del
pre-1.0: un incremento **minor** (`0.89 → 0.90`) può cambiare un comportamento o togliere qualcosa, un
incremento **patch** no. Le rotture sono elencate nel [`CHANGELOG.md`](../reference/changelog.md) con il passo
di migrazione, e le più grandi anche nel [runbook di aggiornamento](../operations/upgrading.md).

Aggiornare significa quindi passare alla minor corrente, non scegliere un ramo di manutenzione. La politica di
supporto verrà riscritta qui prima della 1.0, quando una major stabile renderà sensati i backport.

## Tempi di risposta

Impegno di un solo maintainer, in giorni lavorativi: non è un livello di servizio garantito.

| Che cosa mandi | Prima risposta umana |
|---|---|
| Una segnalazione di sicurezza (vedi la [politica di sicurezza](../operations/security.md)) | 3 giorni |
| Un bug | 7 giorni |
| Una proposta di funzionalità | 14 giorni |
| Una pull request | 14 giorni per la prima review |

Prima risposta vuol dire triage — una domanda, una direzione, un'etichetta di accettazione — non una
correzione. Le correzioni arrivano nel rilascio successivo, e si rilascia quando c'è qualcosa che vale la pena
rilasciare.

## Dove si parla

| Canale | A cosa serve |
|---|---|
| [Issue su GitHub](https://github.com/alle80/griglia/issues) | Bug e proposte concrete, una per issue |
| Segnalazione privata di vulnerabilità o e-mail | Tutto ciò che ha impatto sulla sicurezza — mai una issue pubblica |
| GitHub Discussions | Domande e idee aperte, quando saranno attive; fino ad allora apri una issue e dichiara che è una domanda |

## Contributi scritti con un agente

Griglia esiste per rendere osservabile il lavoro fatto con un agente, quindi le pull request scritte con un
agente CLI sono benvenute e non richiedono avvisi. Vale una regola sola: **chi apre la pull request ne
risponde.** Leggi il diff prima di mandarlo, verifica che i test falliscano senza la modifica e non incollare
documentazione generata che non hai confrontato con il codice.

## Come si cambia questa pagina

La governance cambia come il codice: una pull request, l'approvazione del maintainer e una riga nel
`CHANGELOG.md`. Le proposte di allargare il perimetro, di aggiungere un maintainer o di cambiare la politica
di supporto vanno prima in una issue.
