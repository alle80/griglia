# Upgrade Griglia safely

Use this runbook when moving an existing host application to another Griglia release. It changes installed
dependencies, database schema and published assets; take an application and database backup first.

## Prerequisites

- run commands from the host Laravel application root during a controlled deployment window;
- confirm the target version supports the host PHP, Laravel and Livewire versions;
- read the target entries in the [changelog](https://github.com/alle80/griglia/blob/master/CHANGELOG.md);
- identify whether the application published Griglia's config or views and whether it uses `precompiled` or
  `vite` assets.

## Procedure

```bash
composer update alle80/griglia
php artisan migrate                                    # migrations are idempotent
php artisan vendor:publish --tag=griglia-assets --force # only in precompiled mode
```

If the host uses `vite` mode, rebuild its Vite/Tailwind bundle instead of publishing precompiled assets. If it
published package views, compare those overrides with the new package sources before returning traffic.

## Versioning

While the package is on `0.x`, the **minor** number is where breaking changes may appear: pin what you are
comfortable with (`^0.89.0`) and read the
[CHANGELOG](https://github.com/alle80/griglia/blob/master/CHANGELOG.md) before bumping it. The full policy —
what counts as public, how deprecations are announced, which versions are supported — is on
[Versioning and releases](../contributing/releases.md).

## Verify the upgrade

- **Published views** (`vendor:publish --tag=griglia-views`) do not update themselves: compare them with
  the package sources when a release touches the UI.
- **Precompiled assets** must be republished with `--force`, otherwise the browser keeps the old build.
- **Settings** get their new defaults from the settings migrations, so run `migrate` before using the
  new options.
- Rebuild configuration and route caches according to the host deployment process.
- Open the board, create a disposable todo, change its state and delete it.
- Run `php artisan griglia:check --all` and confirm it reads the same list.
- If the release touched attachments, open an existing attachment through its authenticated route.

## Rollback

Restore the previous Composer lock file and run `composer install`, then restore the pre-upgrade database and
published assets. Do not attempt to reverse schema changes manually unless the target changelog supplies an
explicit rollback. Escalate when a migration completed but no verified backup is available.

## Common problems

| Symptom | Likely cause | Action |
|---|---|---|
| New behaviour does not appear | stale config, route, view or asset cache | rebuild the relevant cache and asset bundle |
| Custom UI lost fields or actions | published view overrides are older than package views | port the overrides onto the current sources |
| Composer will not select the target release | host constraint or locked transitive dependency | review the conflict; do not force an unsupported combination |
| Attachments return 404 after an old upgrade | disk transition was not completed | follow the migration note below |

## Private attachment disk (0.71.0)

The default `GRIGLIA_ATTACHMENTS_DISK` changed from `public` to `local`. Existing installations that have
published `config/griglia.php` keep their published value; review it explicitly. New uploads on the private
disk require `GRIGLIA_ATTACHMENTS_VIA_CONTROLLER=true` (the default) and are available only through Griglia's
authenticated, owner-scoped attachment route. Do not expose `storage/app/private` through a web-server alias.

Files already stored on `public` are not moved automatically. Either keep `GRIGLIA_ATTACHMENTS_DISK=public`
temporarily, or move `attachments/` from the old disk root to the configured private disk before switching.
After changing the environment, run `php artisan config:clear`; the public `storage` symlink is not needed for
private attachments and may be removed if no other part of the host application uses it.
