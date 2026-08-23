# Temi

Un tema è **uno slug, una manciata di parole e un blocco di variabili CSS**. La board ne porta uno, `slate`;
puoi aggiungerne altri in tre modi, dal più economico al più condivisibile:

| | Come | Chi lo installa |
|---|---|---|
| **In configurazione** | `config('griglia.themes')` | tu, nel codice |
| **A runtime** | `Themes::registerTheme()` | tu, in un service provider |
| **Come pacchetto** | uno zip installato da Impostazioni → 🎨 Temi, o `griglia:theme-import` | un amministratore, dalla board |

Il tema attivo si sceglie in **Impostazioni → App → Tema**. La board resta su `/` (sotto il prefisso di rotta
configurato): i temi non creano rotte `/<slug>`, e `/dashboard` rimanda alla stessa board.

Vuoi un look che cambia il *markup*, non solo i colori? Quello è uno stile dedicato — vedi
[Estendere Griglia](../configuration/extending.md#uno-stile-dedicato-componenti-tuoi).

## Le variabili

Tutto ciò che un tema dipinge sta in un blocco `.theme-<slug>`. Nessuna variabile è obbligatoria: quelle che
non definisci ricadono sul valore dichiarato dal CSS della board.

| Gruppo | Variabili |
|---|---|
| Pagina | `--tl-bg`, `--tl-fg`, `--tl-page-max` |
| Testo | `--tl-font`, `--tl-display`, `--tl-ls` (spaziatura), `--tl-tt` (maiuscole/minuscole), `--tl-item-size`, `--tl-counter-size`, `--tl-footer-size`, `--tl-claim-size` |
| Titolo | `--tl-title`, `--tl-title-size`, `--tl-title-sh` (ombra) |
| Schede | `--tl-card`, `--tl-card-fg`, `--tl-card-w`, `--tl-bcol` (colore del bordo), `--tl-bw`, `--tl-bstyle`, `--tl-radius`, `--tl-shadow` |
| Accento | `--tl-accent`, `--tl-accent-fg`, `--tl-stamp`, `--tl-stamp-size` |
| Controlli | `--tl-input`, `--tl-input-fg`, `--tl-add-bg`, `--tl-add-fg`, `--tl-add-size`, `--tl-check-bg`, `--tl-check-radius`, `--tl-num-bg`, `--tl-num-fg`, `--tl-num-size`, `--tl-num-radius` |
| Cornice | `--tl-head`, `--tl-chrome-bg`, `--tl-chrome-fg`, `--tl-chrome-hover`, `--tl-menu-bg`, `--tl-menu-shadow`, `--tl-mini-bg`, `--tl-modal` |
| Task fatti | `--tl-done-filter`, `--tl-done-action` |

Anche la pagina `/settings` si veste con gli stessi valori: a un tema non serve altro per sembrare finito.

## Testi e lingue

Oltre ai colori, un tema definisce le poche parole che la board stampa: `claim` e `footer` (testa e piede
della pagina), `counter` («3/5 *fatti*»), `done_all`, `add` (il bottone che apre il form di inserimento),
`stamp` (sui task completati), `confirm` (la domanda di eliminazione; `:title` viene sostituito) e
`placeholder` (form di inserimento e sotto-task). Ognuna può essere:

- una **chiave di traduzione** — lo Slate integrato usa `griglia::t.theme.add`,
  `griglia::t.theme.placeholder`, … così i suoi testi seguono la
  [lingua della board](../configuration/index.md#la-lingua-della-board) (un `lang/vendor/griglia/<locale>/t.php`
  pubblicato può riformularli);
- un **testo letterale** — usato così com'è, nella lingua in cui è scritto (il `pollon` di esempio parla solo
  italiano);
- una **mappa per lingua** — `{"en": "add", "it": "aggiungi"}`: la board sceglie la lingua corrente, poi
  `app.fallback_locale`, poi la prima voce.

I testi che un pacchetto non definisce ricadono su quelli tradotti di Slate. `griglia:theme-export` scrive le
chiavi di un tema integrato come mappe per lingua, così il `theme.json` esportato si legge e resta bilingue
una volta importato. Una voce di `config('griglia.themes')` o di `Themes::registerTheme()` con lo slug di un
tema integrato lo sovrascrive **chiave per chiave**: `['slate' => ['icon_img' => '/images/slate.svg']]` cambia
l'icona e tiene i testi tradotti.

## Scrivere un pacchetto

Un pacchetto è uno zip con tre cose:

```
theme.json      slug, label, icona, font, i testi qui sopra, emoji deco, versione, autore
theme.css       un solo blocco `.theme-<slug> { --tl-…: … }` (più eventuali regole dentro quella classe)
images/         facoltativa; referenziata con url relative, es. "icon_img": "images/pollon.svg"
```

### Seguine uno: `pollon`

[`resources/themes/pollon`](https://github.com/alle80/griglia/tree/master/resources/themes/pollon), nel
repository, è un pacchetto completo e funzionante — il tema «Pollon» dell'applicazione da cui Griglia è nata.
Leggilo come riferimento, o copialo come punto di partenza.

**1. Parti da un tema che esiste**, non da un file vuoto:

```bash
php artisan griglia:theme-export slate --css-from=resources/css/app.css
```

Il comando scrive `theme.json` e `theme.css` a partire dal tema così come lo vede la board, trasformando i
testi tradotti di quelli integrati in mappe per lingua.

**2. Modifica `theme.json`.** Questo è quello di `pollon`, per intero:

```json
{
    "slug": "pollon",
    "label": "Pollon",
    "icon": "⛅",
    "fonts": "baloo-2:400,700",
    "claim": "C'è da fare sull'Olimpo",
    "counter": "esauditi",
    "done_all": "Forse sì, forse no, forse invece chissà!",
    "add": "+ Un pizzico di polvere del buonumore",
    "stamp": "DIVINO!",
    "confirm": "Scagliare «:title» giù dall'Olimpo?",
    "placeholder": "Desiderio per gli dèi…",
    "footer": "Pollon, Pollon, combinaguai…",
    "deco": ["⛅", "🏛️", "💛", "🕊️"]
}
```

`fonts` è una stringa di famiglie accodata a `config('griglia.fonts_url')` (bunny.net di default, compatibile
con Google); `deco` sono le emoji che la board sparge come decorazione. Qui i testi sono letterali, ed è per
questo che il pacchetto parla solo italiano: scrivili come mappe per lingua per distribuirlo bilingue.

**3. Modifica `theme.css`.** Un blocco solo, con lo slug nel selettore, e solo le variabili che cambi davvero:

```css
.theme-pollon {
    --tl-bg: linear-gradient(#ffd9e8, #cfe8ff);
    --tl-fg: #6b4a5a;
    --tl-font: 'Baloo 2', cursive; --tl-display: 'Baloo 2', cursive;
    --tl-title: #e6537a; --tl-title-sh: 2px 2px 0 #fff;
    --tl-card: #fffdf8; --tl-bcol: #e0a8c0; --tl-radius: 18px;
    --tl-shadow: 0 4px 0 rgb(224 168 192 / 0.5);
    --tl-accent: #d4a017; --tl-accent-fg: #fff; --tl-stamp: #d4a017;
    --tl-add-bg: #e6537a; --tl-head: #ffe3ee;
}
```

**4. Zippa e installa.** `theme.json` sta alla radice dello zip (o dentro un'unica cartella di primo livello):

```bash
cd resources/themes/pollon && zip -r ../pollon.zip .
php artisan griglia:theme-import ../pollon.zip
```

oppure trascina lo zip in Impostazioni → 🎨 Temi. Da lì in poi il tema compare nel selettore come tutti gli
altri.

!!! warning "I pacchetti sono contenuto non fidato"
    L'installazione è riservata agli amministratori. Gli SVG sono rifiutati, il CSS viene sanificato (niente
    `@import`, niente url esterne), dimensioni e numero di file sono limitati e gli asset dei pacchetti
    vengono serviti da una rotta isolata. Vedi [Sicurezza](../operations/security.md).

## Vedi anche

- [Estendere Griglia](../configuration/extending.md) — stili dedicati, skin delle impostazioni, viste pubblicate.
- [Sicurezza](../operations/security.md) — perché i pacchetti sono trattati come contenuto non fidato.
- [Asset front-end](../getting-started/assets.md) — da dove viene caricato il CSS dei temi.
