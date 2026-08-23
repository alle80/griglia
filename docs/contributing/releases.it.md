# Versioni e rilasci

Il numero di versione dice se un aggiornamento può rompere la tua applicazione, il changelog dice cosa fare
per rimediare. Questa pagina scrive le due promesse e come si fa davvero un rilascio.

## Cosa promette un numero di versione

Griglia segue il [versionamento semantico](https://semver.org). È ancora su `0.x`, dove le regole scalano di
una posizione:

| Parte di `0.MINOR.PATCH` | Cambia per | Può romperti? |
|---|---|---|
| `MINOR` | funzioni, e tutto ciò che tocca la superficie pubblica | **sì** — leggi il changelog prima di alzarla |
| `PATCH` | correzioni, documentazione, dettagli interni | no |

Fissa quindi la minor che hai provato (`"alle80/griglia": "^0.89.0"`) e tratta un salto di minor come un
piccolo progetto di aggiornamento: la procedura è il [runbook di aggiornamento](../operations/upgrading.md).

Non c'è una data per la `1.0`: arriverà quando la superficie pubblica qui sotto smetterà di muoversi, non a
calendario.

## Che cosa è pubblico

Cambiare una di queste cose è una rottura, e finisce in una minor:

- **Chiavi di configurazione** di `config/griglia.php` e impostazioni dei gruppi `agent`, `app` e
  `optimization` — vedi [File di configurazione](../reference/config.md) e
  [Impostazioni](../reference/settings.md).
- **Comandi artisan**: nome, argomenti e opzioni — vedi [Comandi artisan](../reference/commands.md).
- **File pubblicabili**: viste, file di lingua, script e asset precompilati, e i tag che li pubblicano.
- **Punti di estensione**: il gate di accesso, il modello utente, `Mode`, le rotte registrate e i loro nomi.
- **L'evento `TodoChanged`** e il suo payload — vedi [Eventi e broadcasting](../reference/events.md).
- **Tabelle e colonne** che l'applicazione ospite può leggere.

Tutto il resto — classi senza una pagina di documentazione, helper interni, dettagli del CSS, la forma di un
partial Blade — è interno e può cambiare in una patch. Se dipendi da qualcosa di interno, scrivilo in una
issue: un punto di estensione costa meno, a entrambi, di un fork privato.

Le **deprecazioni** si annunciano sotto *Deprecated* nel changelog e, quando la modifica lo permette,
continuano a funzionare per un'altra minor prima di sparire.

## Versioni supportate

È supportata solo la minor più recente: le correzioni si mettono lì, non ci sono backport sulle `0.x`
precedenti. Il perché sta in [Governance](governance.md#versioni-supportate).

## Il changelog è il rilascio

Ogni rilascio è descritto una volta sola, in
[`CHANGELOG.md`](https://github.com/alle80/griglia/blob/master/CHANGELOG.md), nel formato
[Keep a Changelog](https://keepachangelog.com/it/1.1.0/). Nessuno lo riscrive: la GitHub Release è generata da
quella sezione da `.github/workflows/release.yml`, così un rilascio non può finire raccontato in due modi
diversi.

Anche le definizioni dei link che chiudono il file sono generate:

```bash
php .github/scripts/changelog-notes.php --links   # tutto il blocco [x.y.z]: …/compare/…
php .github/scripts/changelog-notes.php 0.89.12   # le note di una versione, come vengono pubblicate
```

`ReleaseProcessTest` fallisce se il blocco non corrisponde più, quindi non può marcire in silenzio.

## Come si fa un rilascio

Quattro passi a mano, poi prosegue GitHub.

```bash
# 1. Sposta le voci di Unreleased sotto un titolo loro, con la data di oggi.
#    ## [0.90.0] - 2026-08-23
php .github/scripts/changelog-notes.php --links   # 2. rigenera il blocco di link in fondo al file

npm run build                                     # 3. solo se sono cambiati CSS, JS o viste
composer qa                                       # 4. stile, suite, pagine di reference generate

git tag v0.90.0 && git push origin master v0.90.0
```

Il push del tag fa partire tutto il resto:

| Poi | Chi |
|---|---|
| la versione compare su Packagist | l'hook di Packagist sul repository |
| esce una GitHub Release con le note del changelog e il link di confronto | `release.yml` |
| il sito della documentazione viene ricostruito | `docs.yml` |

Un tag la cui versione non ha una sezione nel changelog fa fallire il workflow di rilascio invece di
pubblicare una release vuota. Per ripubblicare le note di un tag già uscito, lancia a mano il workflow
*release* indicando quel tag.

## Dove sta il sorgente

`alle80/griglia` è un **mirror di pubblicazione**. Il package si sviluppa dentro il monorepo
dell'applicazione che lo usa, in `src/packages/griglia`, e uno script di rilascio ricopia quella cartella su
`master`, la tagga e fa push. Lo script si rifiuta di partire se `master` ha versioni o file che il sorgente
non ha, così un rilascio non può cancellare in silenzio del lavoro arrivato da un'altra parte.

Per chi contribuisce non cambia niente: si parte da `master`, la pull request si apre lì e lì viene rivista e
integrata. Quello che aggiunge è un passo per chi mantiene — riportare l'integrazione nel monorepo prima del
rilascio successivo — ed è il motivo per cui su `master` si vedono tag e merge invece di una lunga storia
quotidiana.

## Vedi anche

- [Contribuire](contributing.md) — la modifica in sé: test, voce di changelog, pull request.
- [Standard di qualità](quality.md) — l'asticella che ogni rilascio ha già superato.
- [Aggiornare Griglia in sicurezza](../operations/upgrading.md) — l'altra faccia di un salto di minor.
