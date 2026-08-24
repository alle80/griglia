@php
    // `standalone`: rendered by InjectBoardTab into a page of the host application, where neither the
    // package stylesheet nor Alpine are loaded — hence the inline CSS and the plain-JS behaviour below.
    $standalone ??= false;
    $side = 'right';
    $show = true;
    try {
        $app = app(\Alle80\Griglia\Settings\AppSettings::class);
        $side = $app->tab_side ?: 'right';
        $show = (bool) $app->show_dashboard_tab;
    } catch (\Throwable $e) {
        // settings not migrated yet — fall back
    }
    $dash = $show ? config('griglia.dashboard_route') : null;
    // No tab on the board itself: since task 617 every board route shows the same full-width page, so the
    // panel would only frame the page you are already on (and, inside the iframe, a tab within a tab).
    // Injected pages are never board pages (the middleware skips them), so there is nothing to check.
    $onBoard = ! $standalone && request()->routeIs('griglia.home', 'griglia.dashboard');
@endphp

@if ($dash && ! $onBoard)
    {{-- Slide-out dashboard tab (desktop only), Laravel-debugbar style: a handle pinned to one
         edge; click opens a resizable panel with the board in an iframe. Self-contained on purpose:
         no stylesheet, no framework — it has to survive in any host page. --}}
    <style>
        .db-tab { display: none; }
        @media (min-width: 1024px) { .db-tab { display: block; } }
        .db-tab { position: fixed; inset: 0 auto 0 auto; top: 0; bottom: 0; z-index: 70; pointer-events: none; }
        .db-tab-right { right: 0; }
        .db-tab-left  { left: 0; }

        /* Handle: a slim vertical tab pinned to the edge */
        .db-tab-handle {
            pointer-events: auto; position: fixed; top: 50%; transform: translateY(-50%);
            display: inline-flex; align-items: center; justify-content: center;
            padding: 14px 6px; cursor: pointer; border: 1px solid var(--tl-bcol, #334155);
            background: var(--tl-accent, #16a34a); color: var(--tl-accent-fg, #fff);
            font: 600 12px/1 var(--tl-font, ui-monospace, monospace); letter-spacing: .08em;
            box-shadow: 0 2px 10px rgb(0 0 0 / .35); opacity: .92;
        }
        .db-tab-handle:hover { opacity: 1; }
        .db-tab-handle-txt { writing-mode: vertical-rl; text-orientation: mixed; text-transform: uppercase; }
        .db-tab-right .db-tab-handle { right: 0; border-radius: 8px 0 0 8px; border-right: 0; }
        .db-tab-left  .db-tab-handle { left: 0;  border-radius: 0 8px 8px 0; border-left: 0; }
        .db-tab.is-open .db-tab-handle { display: none; }

        /* Panel */
        .db-tab-panel {
            pointer-events: auto; position: fixed; top: 0; bottom: 0; width: var(--db-w, 33vw);
            max-width: 92vw; display: flex; flex-direction: column;
            background: var(--tl-bg, #0f172a); border-inline: 1px solid var(--tl-bcol, #334155);
            box-shadow: 0 0 40px rgb(0 0 0 / .45);
            visibility: hidden; opacity: 0;
            transition: transform .22s ease, opacity .22s ease, visibility 0s .22s;
        }
        .db-tab-right .db-tab-panel { right: 0; border-right: 0; transform: translateX(100%); }
        .db-tab-left  .db-tab-panel { left: 0;  border-left: 0;  transform: translateX(-100%); }
        .db-tab.is-open .db-tab-panel {
            visibility: visible; opacity: 1; transform: translateX(0);
            transition: transform .22s ease, opacity .22s ease;
        }
        @media (prefers-reduced-motion: reduce) { .db-tab-panel { transition: none; } }

        .db-resize { position: absolute; top: 0; bottom: 0; width: 8px; cursor: ew-resize; z-index: 2; }
        .db-resize:hover { background: var(--tl-accent, #16a34a); opacity: .35; }
        .db-tab-right .db-resize { left: -4px; }
        .db-tab-left  .db-resize { right: -4px; }

        .db-tab-head {
            display: flex; align-items: center; gap: 10px; padding: 8px 12px;
            border-bottom: 1px solid var(--tl-bcol, #334155); background: var(--tl-head, rgb(0 0 0 / .25));
            color: var(--tl-fg, #e2e8f0); font: 600 13px/1.2 var(--tl-font, ui-monospace, monospace);
        }
        .db-tab-title { flex: 1; text-transform: uppercase; letter-spacing: .06em; }
        .db-tab-open, .db-tab-close {
            cursor: pointer; background: none; border: 0; color: inherit; opacity: .7;
            font-size: 15px; line-height: 1; text-decoration: none; padding: 2px 4px;
        }
        .db-tab-open:hover, .db-tab-close:hover { opacity: 1; }
        .db-tab-frame { flex: 1 1 auto; width: 100%; height: 100%; border: 0; background: var(--tl-bg, #0f172a); }
    </style>

    <div class="db-tab db-tab-{{ $side }}" data-db-tab data-db-side="{{ $side }}" data-db-url="{{ $dash }}">
        {{-- Handle (visible when closed) --}}
        <button type="button" class="db-tab-handle" data-db-toggle aria-expanded="false"
                aria-label="{{ __('griglia::t.dashboard_tab') }}">
            <span class="db-tab-handle-txt">{{ __('griglia::t.dashboard_tab') }}</span>
        </button>

        {{-- Panel --}}
        <aside class="db-tab-panel" aria-label="{{ __('griglia::t.dashboard_tab') }}">
            <div class="db-resize" data-db-resize title="↔"></div>
            <header class="db-tab-head">
                <span class="db-tab-title">{{ __('griglia::t.dashboard_tab') }}</span>
                <a href="{{ $dash }}" class="db-tab-open" title="{{ __('griglia::t.dashboard_open_full') }}" target="_top">⤢</a>
                <button type="button" class="db-tab-close" data-db-toggle aria-label="{{ __('griglia::t.close') }}"><x-griglia::icon name="close" /></button>
            </header>
            <iframe class="db-tab-frame" src="about:blank" loading="lazy"
                    title="{{ __('griglia::t.dashboard_tab') }}"></iframe>
        </aside>
    </div>

    <script>
        (function () {
            var tab = document.currentScript.previousElementSibling;
            while (tab && !tab.hasAttribute('data-db-tab')) tab = tab.previousElementSibling;
            if (!tab || tab.dataset.dbReady) return;
            tab.dataset.dbReady = '1';

            var frame = tab.querySelector('.db-tab-frame'),
                url = tab.dataset.dbUrl,
                side = tab.dataset.dbSide,
                handle = tab.querySelector('.db-tab-handle'),
                width = parseInt(localStorage.getItem('db_tab_w'), 10);

            if (isNaN(width)) width = 460;
            var setWidth = function (w) { width = w; tab.style.setProperty('--db-w', w + 'px'); };
            setWidth(width);

            var setOpen = function (open, remember) {
                tab.classList.toggle('is-open', open);
                handle.setAttribute('aria-expanded', open ? 'true' : 'false');
                // The board is loaded on the first opening only, and left there afterwards.
                if (open && frame.getAttribute('src') !== url) frame.setAttribute('src', url);
                if (remember) localStorage.setItem('db_tab_open', open ? '1' : '0');
            };
            setOpen(localStorage.getItem('db_tab_open') === '1', false);

            tab.querySelectorAll('[data-db-toggle]').forEach(function (button) {
                button.addEventListener('click', function () { setOpen(!tab.classList.contains('is-open'), true); });
            });

            tab.querySelector('[data-db-resize]').addEventListener('mousedown', function (e) {
                e.preventDefault();
                var startX = e.clientX, startW = width;
                var move = function (ev) {
                    var dx = ev.clientX - startX, w = side === 'right' ? startW - dx : startW + dx;
                    setWidth(Math.max(300, Math.min(Math.round(window.innerWidth * 0.7), w)));
                };
                var up = function () {
                    document.removeEventListener('mousemove', move);
                    document.removeEventListener('mouseup', up);
                    document.body.style.userSelect = '';
                    localStorage.setItem('db_tab_w', width);
                };
                document.body.style.userSelect = 'none';
                document.addEventListener('mousemove', move);
                document.addEventListener('mouseup', up);
            });
        })();
    </script>
@endif
