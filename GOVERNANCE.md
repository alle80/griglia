# Governance

Griglia has one maintainer, Alessandro ([@alle80](https://github.com/alle80)), who decides scope and design,
reviews pull requests and cuts releases. Decisions are made in the open, with the reason written in the issue
or the pull request.

The full text — mission, scope, roles, how a change is accepted, supported versions and response times —
lives on the documentation site and is not duplicated here:

**<https://alle80.github.io/griglia/contributing/governance/>** ([source](docs/contributing/governance.md),
[italiano](docs/contributing/governance.it.md))

The short version:

- **Scope**: the board, the agent contract (`griglia:check`, `griglia:watch`) and their documentation.
  Anything that belongs to the host application does not belong here.
- **Before building**, open an issue for anything larger than a fix.
- **A change carries** tests, documentation and a `CHANGELOG.md` entry, and passes CI — see
  [CONTRIBUTING](docs/contributing/contributing.md).
- **Supported versions**: the latest `0.x` minor only, no backports. Pre-1.0, a minor bump may break; a patch
  never does.
- **Response times** (best effort, working days): security 3, bugs 7, proposals and pull requests 14.
- **Security**: never a public issue — see [SECURITY.md](SECURITY.md).
