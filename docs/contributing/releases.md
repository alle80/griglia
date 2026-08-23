# Versioning and releases

The version number tells you whether an upgrade can break your application, and the changelog tells you what
to do about it. This page states both promises, and how a release is actually cut.

## What a version number promises

Griglia follows [semantic versioning](https://semver.org). It is still on `0.x`, where the rules shift by one
position:

| Part of `0.MINOR.PATCH` | Changes when | Can it break you? |
|---|---|---|
| `MINOR` | features, and anything that changes the public surface | **yes** — read the changelog before bumping |
| `PATCH` | fixes, documentation, internals | no |

So pin the minor you have tested (`"alle80/griglia": "^0.89.0"`) and treat a minor bump as a small upgrade
project: the [upgrade runbook](../operations/upgrading.md) is the procedure.

There is no `1.0` date. It arrives when the public surface below stops moving, not on a schedule.

## What counts as public

A change to any of these is breaking, and lands in a minor:

- **Configuration keys** in `config/griglia.php` and the settings of the `agent`, `app` and `optimization`
  groups — see [Configuration file](../reference/config.md) and [Settings](../reference/settings.md).
- **Artisan commands**: their names, arguments and options — see [Artisan commands](../reference/commands.md).
- **Published files**: views, language files, scripts and precompiled assets, and the tags that publish them.
- **Extension hooks**: the access gate, the user model, `Mode`, the registered routes and their names.
- **The `TodoChanged` event** and its payload — see [Events and broadcasting](../reference/events.md).
- **Database tables and columns** the host application can read.

Everything else — classes with no documentation page, internal helpers, CSS internals, the shape of a Blade
partial — is internal and may change in a patch. If you depend on an internal, say so in an issue: an
extension point is cheaper for both of us than a private fork.

**Deprecations** are announced under *Deprecated* in the changelog and, whenever the change allows it, keep
working for one more minor before they go.

## Supported versions

Only the newest minor is supported: fixes go on top of it, there are no backports to older `0.x` lines. The
reasoning is on [Governance](governance.md#supported-versions).

## The changelog is the release

Every release is described once, in [`CHANGELOG.md`](https://github.com/alle80/griglia/blob/master/CHANGELOG.md),
in [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Nothing restates it: the GitHub Release is
generated from that section by `.github/workflows/release.yml`, so a release cannot end up described in two
different ways.

The link definitions that close the file are generated too:

```bash
php .github/scripts/changelog-notes.php --links   # the whole [x.y.z]: …/compare/… block
php .github/scripts/changelog-notes.php 0.89.12   # the notes for one version, as published
```

`ReleaseProcessTest` fails when the block no longer matches, so it cannot silently rot.

## Cutting a release

Four manual steps, then GitHub takes over.

```bash
# 1. Move the Unreleased entries under a heading of their own, dated today.
#    ## [0.90.0] - 2026-08-23
php .github/scripts/changelog-notes.php --links   # 2. refresh the link block at the end of the file

npm run build                                     # 3. only when CSS, JS or views changed
composer qa                                       # 4. lint, suite, generated reference pages

git tag v0.90.0 && git push origin master v0.90.0
```

Pushing the tag triggers everything else:

| Then | Who |
|---|---|
| the version appears on Packagist | the Packagist hook on the repository |
| a GitHub Release appears with the changelog notes and a compare link | `release.yml` |
| the documentation site is rebuilt | `docs.yml` |

A tag whose version has no changelog section fails the release workflow instead of publishing an empty
release. To republish notes for a tag already out there, run the *release* workflow by hand with that tag.

## Where the source lives

`alle80/griglia` is a **publishing mirror**. The package is developed inside the application monorepo that
uses it, under `src/packages/griglia`, and a release script mirrors that directory onto `master`, tags it and
pushes. The script refuses to run when `master` carries versions or files the source does not have, so a
release cannot quietly delete work that arrived from somewhere else.

For a contributor this changes nothing: branch from `master`, open the pull request there, and it is reviewed
and merged there. What it adds is a maintainer step — carrying the merge back into the monorepo before the
next release — and it is the reason `master` shows tags and merges rather than a long day-to-day history.

## Repository metadata

Description, homepage, topics and the feature switches of the GitHub repository are not in the code, so they
drift without anybody noticing. They live in
[`.github/repository.json`](https://github.com/alle80/griglia/blob/master/.github/repository.json), and a
script writes them:

```bash
php .github/scripts/repo-metadata.php          # what differs from the live repository
php .github/scripts/repo-metadata.php --apply  # write the file to GitHub, needs `gh auth login`
```

The description there and the one in `composer.json` are the same sentence, because GitHub and Packagist are
the two places a stranger reads first; a test fails when they drift apart.

The **social preview** is the exception: GitHub has no API for it. After changing
`docs/images/social-preview.png` (1280×640 PNG) upload it once from *Settings → General → Social preview*.
The same image is the `og:image` of every page of this site, through `overrides/main.html`.

## See also

- [Contributing](contributing.md) — the change itself: tests, changelog entry, pull request.
- [Quality standards](quality.md) — the bar every release has already cleared.
- [Upgrade Griglia safely](../operations/upgrading.md) — the other side of a minor bump.
