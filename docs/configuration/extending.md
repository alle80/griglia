# Extending Griglia

Griglia is a package, not a starter kit: you keep your app, and change the board from the outside. Every
point below is a public seam — publish a file, register something in a service provider, listen to an event.
Nothing here asks you to fork the package.

| I want to… | Where |
|---|---|
| Change how a page looks | [Publish the views](#views-publish-the-ones-you-change) |
| Change the words, or add a language | [Publish the translations](#strings-and-a-third-language) |
| Add a colour scheme | [Generic themes](#a-generic-theme-colours-and-words) |
| Give a look its own components, views and route | [Dedicated styles](#a-dedicated-style-your-own-components) |
| Dress `/settings` like that look | [Settings skins](#the-settings-skin) |
| Do something when a task changes | [`TodoChanged`](#react-to-a-change) |
| Decide who gets in, and as whom | [Access hooks](#who-gets-in-and-as-whom) |
| Own the URLs | [Your own routes](#your-own-routes) |

## Views: publish the ones you change

```bash
php artisan vendor:publish --tag=griglia-views
```

The package views land in `resources/views/vendor/griglia/`. A file there **wins over the package one, file
by file** — so delete everything you did not touch: the views you keep go on following the package, the ones
you keep in your app freeze at the version you published.

That includes the Blade components: `<x-griglia::icon name="working" />` resolves as the view
`griglia::components.icon`, so a published `components/icon.blade.php` overrides the icon set too.

| What | File |
|---|---|
| The page frame (fonts, `<head>`, theme class) | `layouts/themed.blade.php` |
| The board | `livewire/todo-list.blade.php` (with `livewire/partials/*`) |
| The task modal | `livewire/ingredient-modal.blade.php` |
| The settings page | `livewire/settings-page.blade.php` |
| Icons, toasts, shared bits | `components/*.blade.php` |

!!! warning "Published views do not upgrade themselves"
    A new version may add a `wire:` binding or a partial to a view you copied. When
    [upgrading](../operations/upgrading.md), diff your copies against the package ones — or drop the copy and
    reach for CSS variables and a [theme](../features/themes.md) instead, which never go stale.

## Strings, and a third language

```bash
php artisan vendor:publish --tag=griglia-lang
```

You get `lang/vendor/griglia/en/t.php` and `.../it/t.php`. Laravel merges a published file **over** the
package one key by key, so keep only the strings you reword — everything you leave out goes on following the
package.

The board ships English (base) and Italian. To add a third language:

1. Publish the translations, then copy the English file:
   ```bash
   cp lang/vendor/griglia/en/t.php lang/vendor/griglia/fr/t.php
   ```
2. Translate the **values** of `fr/t.php`. Placeholders (`:title`, `:agent`, `:count`) must survive the
   translation. A key you have not got to yet falls back to `app.fallback_locale`, so a half-translated file
   is a usable one.
3. That is all the wiring there is: `Alle80\Griglia\Support\Locale::available()` scans the package's
   `resources/lang/*` and `lang/vendor/griglia/*`, so **French now appears in Settings → App → Board
   language**, and the `SetLocale` middleware applies it to every page — Livewire requests included.

Two details worth knowing:

- **The name in the selector.** `Locale::NAMES` spells out `en` and `it`; any other code is named by
  `ext-intl` (`Français`), and by its uppercase code (`FR`) when the extension is missing.
- **Dates follow.** Applying the locale sets Carbon's too, so «3 hours ago» is translated by Carbon, not by
  `t.php`.

Theme texts written as translation keys (`griglia::t.theme.add`, the way the built-in Slate theme writes
them) follow the new language as well — see [Themes](../features/themes.md#texts-and-languages) for the
literal and per-locale forms.

!!! note "Two different translations"
    This is the language of the **board**. The language of these documentation pages is a separate thing —
    see [Translations](../contributing/translations.md).

## A generic theme: colours and words

A generic theme is a slug, a handful of words and a `.theme-<slug>` block of CSS variables. Register it in
config…

```php
// config/griglia.php
'themes' => [
    'lagoon' => [
        'label' => 'Lagoon',
        'icon' => '🌊',
        'fonts' => 'inter:400,700',          // passed to config('griglia.fonts_url')
        'claim' => 'things to do',
        'counter' => 'done',
        'done_all' => 'all done',
        'add' => 'add a task',
        'stamp' => 'done',
        'footer' => '',
        'confirm' => 'delete «:title»?',
        'placeholder' => 'write here…',
        'deco' => ['🌊', '⛵'],
    ],
],
```

…or at runtime, when the definition has to be computed:

```php
// app/Providers/AppServiceProvider.php
use Alle80\Griglia\Themes;

public function boot(): void
{
    Themes::registerTheme('lagoon', [/* same keys */]);
}
```

Then add the variables to your CSS, and select the theme in Settings → App → Theme:

```css
.theme-lagoon {
    --tl-bg: #eef6fb; --tl-fg: #123; --tl-card: #fff; --tl-accent: #0f766e;
}
```

An entry whose slug is a **built-in** theme overrides it key by key
(`['slate' => ['icon_img' => '/images/slate.svg']]` changes the icon and keeps everything else, translations
included). Any other slug replaces the whole definition.

Prefer distributing it as a zip that an administrator installs from the board? That is a **theme pack** —
[Themes](../features/themes.md#writing-a-pack) walks through one, `pollon`, from export to install.

## A dedicated style: your own components

A generic theme reuses the package views. When you want a look that changes the *markup* — a different
board layout, your own row template — register a **dedicated style**: your Livewire component, your views,
your route, listed in the board's style switcher next to the generic themes.

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

1.  **This line is not optional.** A `#[Layout]` inherited from the parent class wins over `->layout()`
    called in `render()`, so a subclass that forgets its own attribute renders inside the package layout.

Extending `ThemedTodoList` (or `TodoList` itself) buys you the whole board: list scoping, states, progress,
questions, attachments, live updates. You change `render()`, the views and the layout — nothing else.

Route it in your app and tell the board about it:

```php
// routes/web.php
Route::get('/retro', \App\Livewire\RetroBoard::class)->middleware('web');

// app/Providers/AppServiceProvider.php
use Alle80\Griglia\Themes;

Themes::registerStyle('retro', [
    'label' => 'Retro',
    'icon' => '🕹️',
    'icon_img' => '/images/retro.svg',   // optional: an image instead of the emoji
    'route' => '/retro',
]);
```

The style switcher now lists Retro first (dedicated styles come before generic themes) and links to
`/retro`. `Alle80\Griglia\Http\Middleware\RememberStyle` remembers, in the session, the style of the page
you are on, so the pages that have no look of their own — `/settings`, `/context` — can dress like it.

### The settings skin

Which is what a **skin** is for: the classes and CSS variables `/settings` uses when the current style is
yours. Generic themes get one for free from their `--tl-*` variables; a dedicated style registers its own.

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

Every key is used as it is: `layout`/`layoutData` go to Livewire's `->layout()`, `home` is the back link,
`vars` colours the switches (`--set-on`, `--set-off`, `--set-border`, `--set-knob`, `--set-shadow`) and the
rest are class strings. Give all of them: the page reads each key directly. The generic skin that
`Themes::settingsSkin()` returns for any theme is the shortest thing to copy and edit.

## React to a change

Every change to a todo, a sub-task, a question or an attachment fires
`Alle80\Griglia\Events\TodoChanged` — broadcast to the browser, and available to your own listeners:

```php
Event::listen(\Alle80\Griglia\Events\TodoChanged::class, function ($event) {
    if ($event->stateChanged && $event->state === 'done') {
        // post to chat, write a metric, call a webhook…
    }
});
```

The payload, the channels and how to listen from JavaScript are in
[Events and broadcasting](../reference/events.md).

## Who gets in, and as whom

In `server` mode the package replaces the plain `auth` middleware with its own gate, and asks your app two
questions:

```php
// app/Models/User.php
public function canAccessGriglia(): bool
{
    return $this->hasTeam();       // may open the board at all
}

public function canManageGriglia(): bool
{
    return $this->is_admin;        // may open /settings, /context and install theme packs
}
```

Prefer Gates? `GRIGLIA_ACCESS_GATE=access-griglia` and `GRIGLIA_ADMIN_GATE=manage-griglia` are consulted
when the methods are absent, and `GRIGLIA_ADMINS="1,alice@example.com"` is the last word. The full order of
precedence is in [Access, administrators and modes](access.md).

The model itself is configurable: `GRIGLIA_USER_MODEL` (default `App\Models\User`) is the class that owns
the lists. Point it at your own model and the package's relations follow.

## Your own routes

Turn the package routes off and mount the components where you like:

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

Keep the four middleware: `GrigliaAccess` **is** the authentication of the package (`auth` is ignored),
`SetLocale` applies the board language, `RememberStyle` feeds the settings skin and `OpenFromLink` opens a
task linked from a notification. Keep the route **names** too — the board links to them.

If all you need is a different prefix, leave the routes on and set
`GRIGLIA_ROUTE_PREFIX=board` instead; the package registers itself after your app's routes, so your own
`/` keeps precedence either way.

## How stable is all this?

While the package is on `0.x`, these seams are what a minor version tries hardest not to break, and a
breaking change to any of them is announced in the [changelog](../reference/changelog.md) and in
[Upgrading](../operations/upgrading.md). Published views are the exception: they are a copy, and copies age.

## See also

- [Themes](../features/themes.md) — theme packs, the `--tl-*` variables, theme texts.
- [Events and broadcasting](../reference/events.md) · [Access, administrators and modes](access.md)
- [Configuration file](../reference/config.md) — every key, generated from the code.
