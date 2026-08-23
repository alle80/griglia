<!--
  Thank you. Keep it short and complete: the problem, what you changed, how you tested it.
  A vulnerability never goes in a pull request — see SECURITY.md.
  The full guidelines: https://alle80.github.io/griglia/contributing/contributing/
-->

## The problem

<!-- What was wrong or missing, from the point of view of whoever uses the board. Link the issue: Closes #123 -->

## What changed

<!-- The shape of the change, not the diff: the classes, commands, settings or pages it touches, and the
     decisions a reviewer would otherwise have to guess. Say it if a behaviour changes for people upgrading. -->

## How you tested it

<!-- The test that fails without this change (name it), plus what you did by hand. Screenshots before/after
     for anything visual, on desktop and on a phone. -->

## Checklist

- [ ] `composer lint` is clean (Pint + PHPStan, no baseline)
- [ ] `composer test` is green
- [ ] `vendor/bin/testbench griglia:docs-build --strict` builds
- [ ] a test that fails without the change
- [ ] documentation updated in the same commit, English **and** Italian
- [ ] a `CHANGELOG.md` entry under *Unreleased*
- [ ] no hardcoded agent name (`:agent`, never a product name), no new dependency without a reason
- [ ] translations in sync (`resources/lang/en` and `resources/lang/it`), migrations additive
- [ ] branched from an up-to-date `master`, commits in English with a conventional prefix

<!--
  Written with a coding agent? Welcome, and no disclaimer needed — but you answer for the diff:
  read it, and make sure the tests fail without the change.
  Anything you could not do (an Italian page, a test you could not write) belongs here, in writing.
-->
