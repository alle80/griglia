# Themes

A theme is a **slug, a handful of words and a block of CSS variables**. The board ships one, `slate`; you can
add more in three ways, from the cheapest to the most shareable:

| | How | Who installs it |
|---|---|---|
| **In config** | `config('griglia.themes')` | you, in code |
| **At runtime** | `Themes::registerTheme()` | you, in a service provider |
| **As a pack** | a zip installed from Settings → 🎨 Themes, or `griglia:theme-import` | an administrator, from the board |

Select the active one in **Settings → App → Theme**. The board stays at `/` (under the configured route
prefix); themes do not create `/<slug>` routes, and `/dashboard` redirects to that same board.

Want a look that changes the *markup*, not just the colours? That is a dedicated style — see
[Extending Griglia](../configuration/extending.md#a-dedicated-style-your-own-components).

## The variables

Everything a theme paints lives in a `.theme-<slug>` block. None of them is required: what you leave out
falls back to the value the board's CSS declares.

| Group | Variables |
|---|---|
| Page | `--tl-bg`, `--tl-fg`, `--tl-page-max` |
| Type | `--tl-font`, `--tl-display`, `--tl-ls` (letter-spacing), `--tl-tt` (text-transform), `--tl-item-size`, `--tl-counter-size`, `--tl-footer-size`, `--tl-claim-size` |
| Title | `--tl-title`, `--tl-title-size`, `--tl-title-sh` (shadow) |
| Cards | `--tl-card`, `--tl-card-fg`, `--tl-card-w`, `--tl-bcol` (border colour), `--tl-bw`, `--tl-bstyle`, `--tl-radius`, `--tl-shadow` |
| Accent | `--tl-accent`, `--tl-accent-fg`, `--tl-stamp`, `--tl-stamp-size` |
| Controls | `--tl-input`, `--tl-input-fg`, `--tl-add-bg`, `--tl-add-fg`, `--tl-add-size`, `--tl-check-bg`, `--tl-check-radius`, `--tl-num-bg`, `--tl-num-fg`, `--tl-num-size`, `--tl-num-radius` |
| Chrome | `--tl-head`, `--tl-chrome-bg`, `--tl-chrome-fg`, `--tl-chrome-hover`, `--tl-menu-bg`, `--tl-menu-shadow`, `--tl-mini-bg`, `--tl-modal` |
| Done tasks | `--tl-done-filter`, `--tl-done-action` |

The `/settings` page dresses itself from the same values, so a theme needs nothing else to look finished.

## Texts and languages

Besides colours, a theme defines the few words the board prints: `claim` and `footer` (head and foot of the
page), `counter` («3/5 *done*»), `done_all`, `add` (the button that opens the insert form), `stamp` (on
completed tasks), `confirm` (the delete question; `:title` is replaced) and `placeholder` (insert form and
sub-tasks). Each of them can be:

- a **translation key** — the built-in Slate uses `griglia::t.theme.add`, `griglia::t.theme.placeholder`, …
  so its texts follow the [language of the board](../configuration/index.md#the-language-of-the-board)
  (a published `lang/vendor/griglia/<locale>/t.php` can reword them);
- a **literal** — used as it is, in whatever language it is written (the sample `pollon` speaks Italian only);
- a **per-locale map** — `{"en": "add", "it": "aggiungi"}`: the board picks the current language, then
  `app.fallback_locale`, then the first entry.

Texts a pack leaves out fall back to the translated ones of Slate. `griglia:theme-export` writes the keys of
a built-in theme as per-locale maps, so the exported `theme.json` is readable and stays bilingual once
imported. A `config('griglia.themes')` or `Themes::registerTheme()` entry whose slug is a built-in theme
overrides it **key by key**: `['slate' => ['icon_img' => '/images/slate.svg']]` changes the icon and keeps
the translated texts.

## Writing a pack

A pack is a zip with three things:

```
theme.json      slug, label, icon, fonts, the texts above, deco emoji, version, author
theme.css       one `.theme-<slug> { --tl-…: … }` block (plus any rule scoped to that class)
images/         optional; referenced with relative urls, e.g. "icon_img": "images/pollon.svg"
```

### Follow one: `pollon`

[`resources/themes/pollon`](https://github.com/alle80/griglia/tree/master/resources/themes/pollon) in the
repository is a complete, working pack — the "Pollon" theme of the app Griglia grew out of. Read it as the
reference, or copy it as the starting point.

**1. Start from an existing theme** rather than an empty file:

```bash
php artisan griglia:theme-export slate --css-from=resources/css/app.css
```

The command writes `theme.json` and `theme.css` from the theme as the board sees it, resolving the built-in
translated texts into per-locale maps.

**2. Edit `theme.json`.** This is `pollon`'s, whole:

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

`fonts` is a family string appended to `config('griglia.fonts_url')` (bunny.net by default, Google-compatible);
`deco` are the emoji the board scatters as decoration. Texts here are literals, which is why this pack speaks
only Italian — write them as per-locale maps to ship it bilingual.

**3. Edit `theme.css`.** One block, the slug in the selector, and only the variables you actually change:

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

**4. Zip and install.** `theme.json` goes at the root of the zip (or inside a single top-level folder):

```bash
cd resources/themes/pollon && zip -r ../pollon.zip .
php artisan griglia:theme-import ../pollon.zip
```

or drop the zip in Settings → 🎨 Themes. The theme then shows up in the switcher like any other.

!!! warning "Packs are untrusted content"
    Installing is administrator-only. SVG is refused, the CSS is sanitised (no `@import`, no external urls),
    size and entry counts are capped and pack assets are served from a sandboxed route. See
    [Security](../operations/security.md).

## See also

- [Extending Griglia](../configuration/extending.md) — dedicated styles, settings skins, published views.
- [Security](../operations/security.md) — why packs are treated as untrusted content.
- [Front-end assets](../getting-started/assets.md) — where the theme CSS is loaded from.
