# Estendere Griglia

Griglia è un package, non uno starter kit: la tua applicazione resta tua e la board si cambia da fuori. Ogni
punto qui sotto è una giuntura pubblica — pubblichi un file, registri qualcosa in un service provider, ascolti
un evento. Nessuno di questi passa dal fare un fork del package.

| Voglio… | Dove |
|---|---|
| Cambiare l'aspetto di una pagina | [Pubblicare le viste](#viste-pubblica-solo-quelle-che-cambi) |
| Cambiare le parole, o aggiungere una lingua | [Pubblicare le traduzioni](#stringhe-e-una-terza-lingua) |
| Aggiungere una combinazione di colori | [Temi generici](#un-tema-generico-colori-e-parole) |
| Dare a un look componenti, viste e rotta sue | [Stili dedicati](#uno-stile-dedicato-componenti-tuoi) |
| Vestire `/settings` come quel look | [Skin delle impostazioni](#la-skin-delle-impostazioni) |
| Fare qualcosa quando un task cambia | [`TodoChanged`](#reagire-a-un-cambiamento) |
| Decidere chi entra, e con quale modello | [Accessi](#chi-entra-e-con-quale-modello) |
| Tenermi le URL | [Rotte tue](#rotte-tue) |

## Viste: pubblica solo quelle che cambi

```bash
php artisan vendor:publish --tag=griglia-views
```

Le viste del package finiscono in `resources/views/vendor/griglia/`. Un file lì **vince su quello del
package, file per file** — quindi cancella tutto quello che non hai toccato: le viste che lasci continuano a
seguire il package, quelle che tieni in casa si fermano alla versione che hai pubblicato.

Valgono anche i componenti Blade: `<x-griglia::icon name="working" />` si risolve come vista
`griglia::components.icon`, quindi un `components/icon.blade.php` pubblicato sovrascrive anche il set di
icone.

| Cosa | File |
|---|---|
| La cornice della pagina (font, `<head>`, classe del tema) | `layouts/themed.blade.php` |
| La board | `livewire/todo-list.blade.php` (con `livewire/partials/*`) |
| Il modale del task | `livewire/ingredient-modal.blade.php` |
| La pagina delle impostazioni | `livewire/settings-page.blade.php` |
| Icone, toast, pezzi condivisi | `components/*.blade.php` |

!!! warning "Le viste pubblicate non si aggiornano da sole"
    Una versione nuova può aggiungere un `wire:` o un partial a una vista che hai copiato. Quando
    [aggiorni](../operations/upgrading.md), confronta le tue copie con quelle del package — oppure butta la
    copia e usa le variabili CSS e un [tema](../features/themes.md), che non invecchiano.

## Stringhe, e una terza lingua

```bash
php artisan vendor:publish --tag=griglia-lang
```

Ottieni `lang/vendor/griglia/en/t.php` e `.../it/t.php`. Laravel fonde il file pubblicato **sopra** quello
del package chiave per chiave, quindi tieni solo le stringhe che riscrivi: tutto il resto continua a seguire
il package.

La board parla inglese (lingua base) e italiano. Per aggiungerne una terza:

1. Pubblica le traduzioni, poi copia il file inglese:
   ```bash
   cp lang/vendor/griglia/en/t.php lang/vendor/griglia/fr/t.php
   ```
2. Traduci i **valori** di `fr/t.php`. I segnaposto (`:title`, `:agent`, `:count`) devono sopravvivere alla
   traduzione. Una chiave che non hai ancora tradotto ricade su `app.fallback_locale`, quindi un file tradotto
   a metà è comunque usabile.
3. Il cablaggio finisce qui: `Alle80\Griglia\Support\Locale::available()` guarda dentro `resources/lang/*`
   del package e `lang/vendor/griglia/*`, quindi **il francese compare in Impostazioni → App → Lingua della
   board** e il middleware `SetLocale` lo applica a ogni pagina, richieste Livewire comprese.

Due dettagli che vale la pena conoscere:

- **Il nome nel menu a tendina.** `Locale::NAMES` scrive per esteso `en` e `it`; ogni altro codice lo nomina
  `ext-intl` (`Français`), e senza quell'estensione resta il codice maiuscolo (`FR`).
- **Le date seguono.** Applicare la lingua imposta anche quella di Carbon, quindi «3 ore fa» lo traduce
  Carbon, non `t.php`.

Anche i testi di un tema scritti come chiave di traduzione (`griglia::t.theme.add`, come fa il tema Slate
integrato) seguono la lingua nuova — vedi [Temi](../features/themes.md#testi-e-lingue) per le forme letterale
e per lingua.

!!! note "Due traduzioni diverse"
    Questa è la lingua della **board**. La lingua di queste pagine di documentazione è un'altra cosa — vedi
    [Traduzioni](../contributing/translations.md).

## Un tema generico: colori e parole

Un tema generico è uno slug, una manciata di parole e un blocco CSS `.theme-<slug>` di variabili. Lo registri
in configurazione…

```php
// config/griglia.php
'themes' => [
    'lagoon' => [
        'label' => 'Lagoon',
        'icon' => '🌊',
        'fonts' => 'inter:400,700',          // passato a config('griglia.fonts_url')
        'claim' => 'cose da fare',
        'counter' => 'fatti',
        'done_all' => 'tutto fatto',
        'add' => 'aggiungi',
        'stamp' => 'fatto',
        'footer' => '',
        'confirm' => 'elimino «:title»?',
        'placeholder' => 'scrivi qui…',
        'deco' => ['🌊', '⛵'],
    ],
],
```

…oppure a runtime, quando la definizione va calcolata:

```php
// app/Providers/AppServiceProvider.php
use Alle80\Griglia\Themes;

public function boot(): void
{
    Themes::registerTheme('lagoon', [/* le stesse chiavi */]);
}
```

Poi aggiungi le variabili al tuo CSS e scegli il tema in Impostazioni → App → Tema:

```css
.theme-lagoon {
    --tl-bg: #eef6fb; --tl-fg: #123; --tl-card: #fff; --tl-accent: #0f766e;
}
```

Una voce il cui slug è quello di un tema **integrato** lo sovrascrive chiave per chiave
(`['slate' => ['icon_img' => '/images/slate.svg']]` cambia l'icona e lascia tutto il resto, traduzioni
comprese). Ogni altro slug sostituisce l'intera definizione.

Preferisci distribuirlo come uno zip che un amministratore installa dalla board? Quello è un **pacchetto di
tema** — [Temi](../features/themes.md#scrivere-un-pacchetto) ne accompagna uno, `pollon`, dall'esportazione
all'installazione.

## Uno stile dedicato: componenti tuoi

Un tema generico riusa le viste del package. Quando vuoi un look che cambia il *markup* — un'altra
impaginazione della board, un tuo template di riga — registri uno **stile dedicato**: componente Livewire
tuo, viste tue, rotta tua, elencato nel selettore degli stili accanto ai temi generici.

```php
// app/Livewire/RetroBoard.php
namespace App\Livewire;

use Alle80\Griglia\Livewire\ThemedTodoList;
use Livewire\Attributes\Layout;

#[Layout('layouts.retro')]          // (1)!
class RetroBoard extends ThemedTodoList
{
    public function render()
    {
        return view('livewire.retro-board', [
            'todos' => $this->todos(),
            't' => \Alle80\Griglia\Themes::get($this->theme),
            'listName' => $this->listName(),
            'archivedCount' => $this->archivedCount(),
            'filtering' => $this->isFiltering(),
            'plan' => $this->planStatus(),
        ]);
    }
}
```

1.  **Questa riga non è facoltativa.** Un `#[Layout]` ereditato dalla classe base vince su `->layout()`
    chiamato in `render()`: una sottoclasse che dimentica il proprio attributo si disegna dentro il layout
    del package.

Estendere `ThemedTodoList` (o direttamente `TodoList`) ti regala tutta la board: scoping delle liste, stati,
avanzamento, domande, allegati, aggiornamenti dal vivo. Tu cambi `render()`, le viste e il layout — nient'altro.

Metti la rotta nella tua applicazione e dillo alla board:

```php
// routes/web.php
Route::get('/retro', \App\Livewire\RetroBoard::class)->middleware('web');

// app/Providers/AppServiceProvider.php
use Alle80\Griglia\Themes;

Themes::registerStyle('retro', [
    'label' => 'Retro',
    'icon' => '🕹️',
    'icon_img' => '/images/retro.svg',   // facoltativo: un'immagine al posto dell'emoji
    'route' => '/retro',
]);
```

Il selettore degli stili adesso elenca Retro per primo (gli stili dedicati vengono prima dei temi generici) e
punta a `/retro`. `Alle80\Griglia\Http\Middleware\RememberStyle` ricorda in sessione lo stile della pagina in
cui sei, così le pagine senza un look proprio — `/settings`, `/context` — possono vestirsi come lui.

### La skin delle impostazioni

Che è appunto a cosa serve una **skin**: le classi e le variabili CSS che `/settings` usa quando lo stile
corrente è il tuo. I temi generici ne ricevono una gratis dalle loro variabili `--tl-*`; uno stile dedicato
registra la sua.

```php
Themes::registerSkin('retro', [
    'layout' => 'layouts.retro', 'layoutData' => [], 'home' => '/retro',
    'card' => 'retro-card p-5',
    'h1' => 'retro-display text-2xl',
    'h2' => 'retro-display text-xl',
    'sub' => 'text-sm italic opacity-70',
    'label' => 'font-bold',
    'help' => 'text-sm opacity-70',
    'input' => 'retro-input px-3 py-1.5 focus:outline-none',
    'back' => 'retro-key cursor-pointer px-3 py-1.5',
    'divide' => 'divide-y divide-current/15',
    'vars' => '--set-on:#33d17a;--set-off:#05130a;--set-border:#2c5c3f;--set-knob:#fff;--set-shadow:none',
]);
```

Ogni chiave è usata così com'è: `layout`/`layoutData` finiscono nel `->layout()` di Livewire, `home` è il
link «indietro», `vars` colora gli interruttori (`--set-on`, `--set-off`, `--set-border`, `--set-knob`,
`--set-shadow`) e le altre sono stringhe di classi. Mettile tutte: la pagina legge ogni chiave direttamente.
La skin generica che `Themes::settingsSkin()` restituisce per un tema qualsiasi è la cosa più corta da
copiare e modificare.

## Reagire a un cambiamento

Ogni modifica a un todo, a un sotto-task, a una domanda o a un allegato emette
`Alle80\Griglia\Events\TodoChanged` — trasmesso al browser, e a disposizione dei tuoi listener:

```php
Event::listen(\Alle80\Griglia\Events\TodoChanged::class, function ($event) {
    if ($event->stateChanged && $event->state === 'done') {
        // manda un messaggio in chat, scrivi una metrica, chiama un webhook…
    }
});
```

Il payload, i canali e come ascoltarlo da JavaScript sono in
[Eventi e broadcasting](../reference/events.md).

## Chi entra, e con quale modello

In modalità `server` il package sostituisce il middleware `auth` con il proprio gate e fa due domande alla
tua applicazione:

```php
// app/Models/User.php
public function canAccessGriglia(): bool
{
    return $this->hasTeam();       // può aprire la board
}

public function canManageGriglia(): bool
{
    return $this->is_admin;        // può aprire /settings, /context e installare pacchetti di tema
}
```

Preferisci i Gate? `GRIGLIA_ACCESS_GATE=access-griglia` e `GRIGLIA_ADMIN_GATE=manage-griglia` vengono
consultati quando i metodi non ci sono, e `GRIGLIA_ADMINS="1,alice@example.com"` è l'ultima parola. L'ordine
completo sta in [Accessi, amministratori e modalità](access.md).

Anche il modello si configura: `GRIGLIA_USER_MODEL` (default `App\Models\User`) è la classe che possiede le
liste. Puntalo al tuo modello e le relazioni del package lo seguono.

## Rotte tue

Spegni le rotte del package e monta i componenti dove vuoi:

```php
// config/griglia.php
'register_routes' => false,
```

```php
// routes/web.php
use Alle80\Griglia\Http\Middleware\{GrigliaAccess, OpenFromLink, RememberStyle, SetLocale};

Route::middleware(['web', GrigliaAccess::class, SetLocale::class, RememberStyle::class, OpenFromLink::class])
    ->prefix('board')
    ->group(function () {
        Route::get('/', \Alle80\Griglia\Livewire\ThemedTodoList::class)->name('griglia.home');
        Route::get('/settings', \Alle80\Griglia\Livewire\SettingsPage::class)
            ->middleware(\Alle80\Griglia\Http\Middleware\GrigliaAdmin::class)->name('griglia.settings');
    });
```

Tieni tutti e quattro i middleware: `GrigliaAccess` **è** l'autenticazione del package (`auth` viene
ignorato), `SetLocale` applica la lingua della board, `RememberStyle` alimenta la skin delle impostazioni e
`OpenFromLink` apre un task arrivato da una notifica. Tieni anche i **nomi** delle rotte: la board ci si
collega.

Se ti serve solo un prefisso diverso, lascia accese le rotte e imposta `GRIGLIA_ROUTE_PREFIX=board`; il
package si registra dopo le rotte della tua applicazione, quindi in ogni caso la tua `/` ha la precedenza.

## Quanto è stabile tutto questo?

Finché il package è sullo `0.x`, queste giunture sono ciò che una versione minore cerca di non rompere, e una
rottura viene annunciata nel [changelog](../reference/changelog.md) e in
[Aggiornare](../operations/upgrading.md). Le viste pubblicate sono l'eccezione: sono una copia, e le copie
invecchiano.

## Vedi anche

- [Temi](../features/themes.md) — pacchetti di tema, le variabili `--tl-*`, i testi dei temi.
- [Eventi e broadcasting](../reference/events.md) · [Accessi, amministratori e modalità](access.md)
- [File di configurazione](../reference/config.md) — tutte le chiavi, generate dal codice.
