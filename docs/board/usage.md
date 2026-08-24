# Using the board

## Lists

The lists menu (top left) switches between your lists, creates new ones, renames or deletes them. A plan is
written on its own page — **New plan…** in the same menu, see [Plans](../features/plans.md). The **agent list** (config `agent_list`) is the channel with the
coding agent; any other list is yours (or a plan).

## Tasks and states

Every row has a state dot:

| Dot | State | Who sets it |
|-----|-------|-------------|
| ![waiting](../images/state-waiting.svg){ width="18" } | waiting | you — the agent must not touch it |
| ![open to work](../images/state-open.svg){ width="18" } | open to work | you — ready for the agent |
| ![working](../images/state-working.svg){ width="18" } | working | the agent (`--take`) — icon animated, progress % and phase next to the title |
| ![paused](../images/state-paused.svg){ width="18" } | paused | the agent (`--pause`) — progress is preserved; the agent worker resumes it automatically |
| ![question](../images/state-question.svg){ width="18" } | question | the agent asked something; answer in the modal and restart |
| ![stopped](../images/state-stop.svg){ width="18" } | stopped | you tapped the working badge — the agent stops at once |
| ![done](../images/state-done.svg){ width="18" } | done | the agent (`--done`) or you (checkbox) |

Tap the dot to move between *waiting* and *open to work*, stop the agent, or reopen a paused task. A completed row always
shows the *done* icon, even if it was open to work before completion.

When the agent has left a result, a very short automatic summary appears under the title. Agents can give a
more precise one with `griglia:check --done --summary="…"`; otherwise Griglia derives it from the closing
comment. It tells apart a sequence of resumed tasks that all share the same title.

At the right end of the title line every row shows the task **id** (`id:510`): the same `id:N` the agent prints
in `griglia:check`, and the number you use with `--take` / `--done` or when you talk about a task. Tap it to
copy the number (the chip says *copied* for a moment, and the modal does not open). The big number at the left
of the row is the position in the list, which changes when you reorder or archive; the id never changes.

### The colour of the row

A row you have not read yet is drawn with a coloured **border around its card**, and the colour says how
much it wants from you:

| Border | Meaning | Where it comes from |
|--------|---------|---------------------|
| green | done, nothing to check | `--done` (no `--outcome`, or `--outcome=ok`) |
| yellow | done, but something needs a look | `--done --outcome=alert` |
| red | something is in the way | `--done --outcome=blocked` |
| violet | the agent is waiting for your answers | `--ask` (open questions) |

The four colours are fixed rather than derived from the theme accent, and a highlighted row keeps them at
full strength: it is exempt from both the fading and the greyscale a theme applies to completed rows,
which would otherwise wash the border out.

Completed rows remain subdued, but their action buttons use a lighter grey (`--tl-done-action`) and stronger
opacity so archive, resume and delete stay legible, including on dark themes and small screens.

The colour is the whole signal: no badge in the row, no chip in the modal. Open the task and the border goes
back to the usual one of the theme; a task you close yourself has no coloured border, because there is no
result to read. A screen reader still gets the meaning, from a hidden label on the row, and the row's tooltip
spells it out.

The row writes the border colour on itself (inline), not only through the `.db-attention` / `.db-att-*`
classes. An app that runs the package views from `vendor/` while its stylesheet is compiled from another
copy of the package can end up with no rule at all for those classes: the highlight would silently
disappear. The stylesheet still adds the pulse and can be re-themed through `--db-att`.

### Carrying on after a task is done

**A closed task stays closed.** The checkbox and the state dot do not reopen it: what the agent answered
stays as it was answered, and nothing it already finished goes back in front of it.

To carry on there is one way: **resume** (the ↻ button on the row or in the modal). It creates a *new* task
right after the old one, with the same title and the old one attached as context — note, answer, sub-tasks
and images stay one click away (the box is closed until you open it: what matters now is what you are
asking today), and `griglia:check` shows them to the agent.

Resuming a resumed task keeps the **whole chain**: the box lists every previous step, from the most recent
one down to the request that started it all (`+2 earlier` next to the title), and the agent receives the same
history — so nothing that was asked or answered along the way is lost. If a step of the chain is deleted, the
task after it is re-linked to the one before, exactly like the chain of a plan.

Nothing else is a one-way door: a task that leaves the board (archived or deleted) hands its chain over to
the task before it, so a plan never waits for something that will never arrive, and a task with open
questions can be taken back without answering — tap its badge in the modal: the questions stay recorded and
the task goes back to waiting.

## The task modal
The board row and modal header prefix every task title with its list name (`List · Task`), so its context stays visible even when a search spans several lists. Renaming still edits only the task title.


Title, **Task** note (Markdown editor, with a microphone for [speech to text](../features/ai.md#speech-to-text)), the agent's answer box, statistics (working time,
tokens, cost), the agent's **skills** accordion, images (upload, camera, paste; AI description when enabled),
sub-tasks (Markdown, sortable), questions/answers, resume-from context.

A task is read-only while it is **working**, so the request cannot change under the agent’s feet. Tap the
working badge to stop it and return it to waiting before editing; it can then be marked open to work again.

The header uses the whole bar: on the left the **state badge**, whose text label stays beside its icon on
every screen, with ‹ `3/7` › and the task id (`id:510`, tap to copy the number; on a phone it moves down to
the commands line) — and on the right the commands: the agent that owns the task, **move to another list**, archive, delete, close, plus **resume with
changes** (a new linked task) on a completed one.

When several agents are configured, the selected agent is shown on its own line below the task title. The native
selector sizes itself from the selected agent name and never replaces it with an ellipsis; only the row's available
width limits it.

### Saving happens by itself

The title and the note are saved **while you type** — a short pause is enough. There is no «Save» button: what is
written is stored automatically. The small «Saved» flag next to the field says when the
last save happened, and the editor closes with `Enter` (title), `Esc`, or a click outside it.

As soon as the text differs from the one you started from, a **Cancel** button (↩)
appears next to the «Saved» flag: it puts back the title — or the note — as it was when you opened the
editor, and leaves you inside it, so you can keep writing from there. It is one step back, not a history:
close the editor and reopen it and the «previous version» becomes the text you have just left.

The same goes for the inline rename of a row in the list. Sub-tasks are not saved live: they keep their own
✓ and ✕ buttons.

### Moving between tasks

The modal has ‹ and › next to the state badge, with the position of the task in the list (`3/7`): they open
the previous and the next task without closing the modal — the way to follow a plan from one step to the
next. The **left and right arrow keys** do the same, unless you are typing in a field.

### Copying what is in a note

Notes and agent answers are Markdown: single newlines are displayed as line breaks, and a **code block has a copy button** in its corner (commands, prompts,
snippets), **inline code copies itself with one click**, and links open in a new tab.
While editing a note, the browser sizes its field directly from the content, so background autosaves cannot
collapse it or hide the last lines; saving stays silent so it does not interrupt typing.

## Toolbar

On desktop, compact labels, filter controls and the Markdown editor toolbar use a larger, more readable size;
the denser mobile sizing is preserved to leave room for board and modal content.

Free-text search (title, notes, comment, sub-tasks, questions, image descriptions), state and agent filters, archive.
The state filter is a **drop-down**: pick *All*, *To do*, *Done*, *Open to work*, *Working*, *Paused* or *Questions*,
and the icon beside it becomes the badge of the state in use (a funnel when no filter is on). One control instead of
seven chips, so the toolbar stays on one line on a phone as well as on the full-width board.
When several agents are configured, the chip with the robot icon (**All agents**) narrows the list to one agent's
tasks; the filter follows the effective assignment (task override, then list default, then the global default),
combines with the search and the state filters, and — like them — disables drag & drop while it is on. Its visual
style stays unchanged after choosing an agent; the selected name is the indication of the active value.
Turn on **All lists** beside the search field to show tasks from every active list you own. The scope applies to the
unfiltered board as well as text, state and agent filters, and each result shows its list name. Archived lists and
other users’ lists stay outside the board.
On a plan list the **Plan** bar shows progress and the start/pause buttons (see [Plans](../features/plans.md)).

The two view buttons switch the task area between the original **list** and a **grid** of vertical cards. The grid
uses one column on phones, two on tablets and three on desktop; the browser remembers the choice for the next visit.
From a 1200px window up the three-column ceiling is lifted: the columns auto-fill at a fixed card width
(`--tl-card-w`, 22rem by default), so a wide screen simply gets more cards per row — see
[the full-width board](#desktop-the-full-width-board).

## Desktop: the full-width board

On a big screen every application page uses the **whole window**: the board, settings, context, statistics,
plans, the plan editor and agents all share a readable ceiling of 1920px, past which the page stays centred
(`.tl-page-wide`, override with the `--tl-page-max` CSS variable).
Long titles and notes stop wrapping every few words. In grid view the columns are free to multiply — see the
toolbar section above — and the task modal follows the screen too, up to `max-w-6xl` on very wide displays.
Inside the narrow side tab the same page keeps the ordinary responsive columns: the 1200px rule is measured on
the iframe, not on the window. On a phone nothing changes: the container was never the limit there.

There is no second, wider address to go to. `/dashboard` used to be one and now **redirects to the board**, so
old links, bookmarks and the side tab keep working. The path still comes from the config key `dashboard_route`
(`GRIGLIA_DASHBOARD_ROUTE`, default `/dashboard`); set it to `null` and both the redirect and the tab below
disappear. A host application that owns `/` and turns `home_route` off keeps the board on that path instead of
the redirect.

### The side tab

Every page of the site — the board's pages and your own application's — carries a **slide-out tab pinned to
one edge of the window**: a handle, debugbar style, that opens a panel with the board inside it. Click the handle and the panel slides out; drag its inner edge to resize
it (from 300px up to 70% of the window); ⤢ opens the whole board in the tab you are on; ✕ closes the panel.
Whether it is open and how wide it is are remembered in the browser (`localStorage`), so the panel comes back
the way you left it on the next page. It never appears on the board itself — there it would only frame the page
you are already on — and it is **desktop only**:
below the `lg` breakpoint it is not rendered at all, on the principle that a phone has no room to spare.

Two settings in `/settings` govern it:

| Setting | What it does |
|---|---|
| **DASHBOARD side tab** (`show_dashboard_tab`) | Shows or hides the tab. Off, the board stays reachable at its own address. |
| **Dashboard tab side** (`tab_side`) | Which edge the tab lives on — `right` (default) or `left`. |

There is nothing to add to your layouts: a middleware in the `web` group splices the tab into every HTML page
the host application returns, just before `</body>` — the same trick laravel-debugbar uses. It stays out of
everything that is not a full page (JSON and AJAX responses, redirects, downloads, streams, Livewire, Turbo and
Inertia partial updates), out of the package pages (whose layout prints it already, so it is never doubled) and
out of the pages of a visitor who may not open the board. To keep it away from a corner of your application,
list the paths in `config/griglia.php`:

```php
'inject_tab_except' => ['admin/*', 'horizon/*'],
```

Patterns are globs matched `Request::is` style, against the path and against the route name; the default is an
empty list. The tab carries its own CSS and its own framework-free JavaScript inline, so it also works on pages
that load neither the package stylesheet nor Alpine.

## Mobile

Everything is designed for phones: rows on two levels, full-screen modal, full-width notification panel, Web Push.

The modal header stacks on a narrow screen: the state badge keeps its text label beside the icon and,
with ‹ `3/7` ›, stays on the first line next to the close button — always understandable and reachable, whatever the list
holds — the agent selector takes the line below, aligned left, and the remaining commands (move, archive,
delete) sit on the last line, led on the left by the task id chip (`id:510`), which leaves the first line so
the close button never wraps; touch targets big enough for a thumb. The selector has a line of its
own on every screen: among the icons its label («Default (Claude Code)») ended up clipped. Nothing is
hidden behind a menu, and nothing runs off the edge of the screen.

## See also

- [The agent side](../agent/index.md) — what the agent does with what you write here.
- [Plans](../features/plans.md) · [Notifications](../features/notifications.md) · [AI features](../features/ai.md)
- [Feature overview](../features/index.md) — the whole board in one page.
