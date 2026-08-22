# License

Griglia is released under the **MIT license** — the same license as Laravel, Livewire and Tailwind. The full
text is in [`LICENSE`](https://github.com/alle80/griglia/blob/master/LICENSE) at the root of the repository,
and `composer.json` declares `"license": "MIT"`, so Packagist and any license scanner read it from the
metadata as well.

This page exists because "MIT" on a badge does not tell you what you may do with the code, what the project
asks of a contributor, or which third-party licenses come along with it.

## What you may do

| You may | On this condition |
|---|---|
| Use Griglia in a product, commercial or not, closed source or not | Keep the copyright and license notice in the copies you distribute |
| Fork it, change it, rename it, remove parts of it | The same notice |
| Redistribute it, sell it, sublicense it, bundle it inside another package | The same notice |
| Run it privately and never publish anything | Nothing |

That is the whole deal: no copyleft, no obligation to publish your changes, no contributor licence agreement,
no fee, no reporting. In exchange the license gives **no warranty and no liability** — how Griglia behaves in
your infrastructure, and the backups of the database it writes to, are the host application's responsibility.

## Why MIT

The real choice was between staying permissive and asking for something back. Griglia is a package you install
*inside* your own application, next to your own code: a license that puts conditions on the application around
it would make the board harder to adopt than it is to write.

| Option | Why not |
|---|---|
| **MIT** — chosen | The shortest permissive text people already recognise, and the license of everything Griglia builds on, so a host application never has to reconcile two sets of terms |
| Apache-2.0 | Permissive too, with an explicit patent grant — but also per-file headers and a `NOTICE` file to maintain, ceremony that a single-maintainer board does not repay |
| BSD-2-Clause / BSD-3-Clause | Equivalent in practice; MIT is the convention in the PHP and Laravel ecosystem |
| GPL / AGPL | Copyleft would attach conditions to the application that embeds Griglia — the opposite of what a `composer require` should cost you |
| No license | Public on GitHub is not permission. With no license nobody may legally use, copy or deploy the code |

## What the license covers

Everything in the repository: the PHP and Blade sources, the migrations, the CSS and JavaScript, the built
assets in `public/build/`, the translations, the theme packs, the documentation pages you are reading and the
brand images in `public/images/brand/`.

One courtesy the license does not demand: the name «Griglia» and its logo identify *this* project. Use them to
say that your work is based on Griglia, not in a way that suggests a fork is Griglia or that the maintainer
endorses it.

## Redistributing the built assets

`public/build/griglia.js` is a bundle: SortableJS, Laravel Echo and Pusher JS are compiled into it, and the
bundler strips their comments. All three are MIT, so redistributing the built file is fine — carry the notices
of the table below with it if you ship the bundle instead of building it yourself.

## Third-party components

| Component | Where it lives | License |
|---|---|---|
| `illuminate/*` (Laravel) | Runtime dependency | MIT |
| `livewire/livewire` | Runtime dependency | MIT |
| `spatie/laravel-settings` | Runtime dependency | MIT |
| `laravel-notification-channels/webpush`, `minishlink/web-push` | Runtime dependency (web push) | MIT |
| `league/commonmark` | Runtime dependency (Markdown in notes and comments) | BSD-3-Clause |
| SortableJS, Laravel Echo, Pusher JS | Bundled into `public/build/griglia.js` | MIT |
| Tailwind CSS, Vite | Build time only | MIT |
| MkDocs Material, mkdocs-static-i18n | Documentation site only | MIT |
| JetBrains Mono | Documentation site typography, loaded from Google Fonts | SIL Open Font License 1.1 |

Every one of them is permissive and compatible with MIT. `composer licenses` and `npm ls --long` print the
list as it is today, transitive dependencies included; this table is the summary a reviewer usually needs.

## Contributions

**Inbound equals outbound**: what you contribute is licensed under the MIT license, on the same terms as the
rest of the project. There is no contributor licence agreement and no copyright assignment — you keep the
copyright on what you write, and opening a pull request is your agreement to publish it under those terms.
[Contributing](contributing.md) says what a change must carry.

If a change brings third-party code with it, say so in the pull request — origin, license, and a line in the
table above. Code under a copyleft license cannot be merged, however small.

## Changing the license

Relicensing needs the agreement of everyone who holds copyright on the code, so it is not something the
maintainer can decide alone. The commitment of the project is narrower and easier to trust: **Griglia stays
under an OSI-approved permissive license.** A move to another permissive license would be discussed in a
public issue before anything is changed; a move to copyleft is off the table.

## Where the license is declared

| Place | What it says |
|---|---|
| `LICENSE` | The MIT text — copyright 2026 Alessandro (alle80) |
| `composer.json` | `"license": "MIT"`, what Packagist and SPDX scanners read |
| `README.md` and the footer of this site | A link back to this page |
