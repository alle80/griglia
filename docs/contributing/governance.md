# Governance

This page says who decides what happens to Griglia, what the project promises and what it refuses,
which versions get fixes and how long you should expect to wait for an answer. Read it before opening a
large pull request: most rejected work is rejected for scope, not for quality.

## Mission

**Griglia gives a developer and a CLI coding agent a shared, observable workflow inside a Laravel
application.** You write a request, decide when it is ready, and follow the agent while it claims the task,
asks questions, reports progress and closes it with a recorded result — on infrastructure you control.

The project pursues five goals, in this order:

1. **A working contract with any agent.** The agent side is two artisan commands (`griglia:check`,
   `griglia:watch`) plus an instructions file. Nothing in the package depends on a specific vendor, model or CLI.
2. **Your data on your machine.** Griglia stores tasks, questions and statistics in your database. It does not
   call a model provider on its own, and every AI feature is optional and off by default.
3. **A short path from installing to seeing it work.** `composer require`, `migrate`, and a board that runs.
4. **Honest state.** What the board shows — working, question, progress, phase, cost — is what actually
   happened, not an animation.
5. **Documentation that removes the need to ask.** Every user-visible behaviour has a page, in English and
   Italian, and the reference pages are generated from the code.

## Scope

| In scope | Out of scope |
|---|---|
| The board, its states, lists, sub-tasks, notes and attachments | Being an IDE, a chat interface or an autonomous coding model |
| The agent contract: `griglia:check`, `griglia:watch`, context, skills, statistics | Shipping or bundling a coding agent, or prompts for one |
| Live updates, notifications, plans, themes, settings and the docs site | A hosted service, a public demo instance or account management |
| Host-side helper scripts published with `vendor:publish --tag=griglia-scripts` | Anything that belongs to the host application: authentication, user administration, layout |

Requests that fall outside the scope are not failures — they are usually a good extension point, a host-app
concern, or a separate package. The maintainer says which one in the issue.

## Roles

| Role | Who | What they may do |
|---|---|---|
| **Maintainer** | Alessandro ([@alle80](https://github.com/alle80)) | Decides scope and design, reviews and merges, cuts releases, owns the roadmap |
| **Contributor** | Anyone who opens an issue or a pull request | Proposes, discusses, implements; keeps authorship and credit |
| **Coding agent** | An agent working for a contributor | Writes code and documentation under the responsibility of the person who opens the pull request |

There is one maintainer today, and the project says so rather than pretending to be a committee. The door to
co-maintainers is open: a contributor with a sustained record of merged changes and reviews can be offered
commit rights, and the change is recorded here.

## How decisions are made

The maintainer decides, in the open, with the reason written in the issue or the pull request.

1. **Discuss before building.** For anything larger than a fix, open an issue first and describe the problem
   before the solution. A pull request that arrives without one may be closed for scope alone.
2. **A change is accepted when** it fits the scope above, keeps existing behaviour working (or documents the
   break), carries tests, documentation and a `CHANGELOG.md` entry, and passes CI — see
   [Contributing](contributing.md).
3. **A change is refused when** it belongs to the host application, adds a dependency that a few lines would
   have replaced, hardcodes an agent, a language or a provider, or grows the surface faster than the
   documentation can follow.
4. **Disagreement** is settled by argument first. If it persists, the maintainer decides and records why; the
   issue stays open until that reason is written.
5. **Silence is not rejection.** If an issue or a pull request has had no answer past the times below, ping it.

## Supported versions

| Version | Status |
|---|---|
| Latest `0.x` minor | Supported: fixes, security patches and documentation |
| Every earlier version | Not supported — no backports |

Griglia is pre-1.0 and follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html) with the pre-1.0
rules: a **minor** bump (`0.89 → 0.90`) may change behaviour or remove something, a **patch** bump never does.
Breaking changes are listed in [`CHANGELOG.md`](../reference/changelog.md) with the migration step, and the
larger ones in the [upgrade runbook](../operations/upgrading.md).

Upgrading is therefore a matter of moving to the current minor, not of choosing a maintenance branch. The
support policy will be rewritten here before 1.0, when a stable major makes backports meaningful.

## Response times

Best effort by a single maintainer, in working days, not a service level agreement:

| You send | First human answer |
|---|---|
| A security report (see the [security policy](../operations/security.md)) | 3 days |
| A bug report | 7 days |
| A feature proposal | 14 days |
| A pull request | 14 days for the first review |

A first answer means triage — a question, a direction, or an accepted label — not a fix. Fixes ship in the
next release, and releases are cut when there is something worth releasing.

## Where to talk

| Channel | Use it for |
|---|---|
| [GitHub issues](https://github.com/alle80/griglia/issues) | Bugs and concrete proposals, one per issue |
| Private vulnerability reporting or e-mail | Anything with a security impact — never a public issue |
| GitHub Discussions | Questions and open-ended ideas, once enabled; until then open an issue and say it is a question |

In all of them the [code of conduct](code-of-conduct.md) applies: the Contributor Covenant 2.1, enforced by
the maintainer, with reports read by them alone.

## Contributions written with an agent

Griglia exists to make agent-assisted work observable, so pull requests written with a coding agent are
welcome and need no disclaimer. One rule applies: **the person who opens the pull request answers for it.**
Read the diff before sending it, make sure the tests fail without the change, and do not paste generated
documentation you have not checked against the code.

## License

Griglia is MIT-licensed, and the project commits to staying under an OSI-approved permissive license:
a `composer require` should never put conditions on the application that installs it. Contributions are
accepted on the same terms — inbound equals outbound, with no agreement to sign. The reasoning, the
third-party licenses that come with the package and what relicensing would take are on the
[License](license.md) page.

## Changing this page

Governance changes the same way the code does: a pull request, the maintainer's approval, and a line in the
`CHANGELOG.md`. Proposals to widen the scope, to add a maintainer or to change the support policy belong in
an issue first.
