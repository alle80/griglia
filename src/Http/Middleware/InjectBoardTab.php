<?php

namespace Alle80\Griglia\Http\Middleware;

use Alle80\Griglia\Settings\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Puts the slide-out board tab on every page of the host application, the way laravel-debugbar puts its
 * own bar there: the response is rendered by the app as usual and the tab is spliced in just before the
 * closing </body>. Nothing to add to the host layouts.
 *
 * The tab is skipped whenever the response is not a plain HTML page (JSON/AJAX, redirects, downloads,
 * streams, Livewire/Turbo/Inertia partial updates), when the board itself is being served (the package
 * layout already prints the tab), when the side tab is switched off in /settings, when the visitor may
 * not open the board, and on the paths listed in `griglia.inject_tab_except`.
 */
class InjectBoardTab
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response && $this->shouldInject($request, $response)) {
            $this->inject($response);
        }

        return $response;
    }

    /** Every guard, cheapest first: the settings and the access check are only paid on real HTML pages. */
    protected function shouldInject(Request $request, Response $response): bool
    {
        return $this->isFullPageRequest($request)
            && $this->isInjectableHtml($response)
            && ! $this->isExcludedPath($request)
            && $this->tabIsEnabled()
            && GrigliaAccess::allows($request->user());
    }

    /** A normal page load of the host app: not a partial update, not an API call, not a board page. */
    protected function isFullPageRequest(Request $request): bool
    {
        if ($request->ajax() || $request->pjax() || $request->expectsJson() || $request->isJson()) {
            return false;
        }

        // Livewire's /livewire/update, Turbo frames and Inertia visits swap a fragment of the page: a tab
        // injected there would be duplicated inside it (or dropped, which is worse: it would look random).
        if ($request->hasHeader('X-Livewire') || $request->hasHeader('Turbo-Frame') || $request->hasHeader('X-Inertia')) {
            return false;
        }

        if ($request->is('livewire/*')) {
            return false;
        }

        // The package pages print the tab from their own layout: injecting it again would double it.
        return ! str_starts_with((string) $request->route()?->getName(), 'griglia.');
    }

    /** A complete HTML document we may edit: no redirect, no error page, no download, no stream. */
    protected function isInjectableHtml(Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        if (! $response->isSuccessful() || $response->headers->has('Content-Disposition')) {
            return false;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', 'text/html'));
        if ($type !== '' && ! str_contains($type, 'text/html')) {
            return false;
        }

        $content = $response->getContent();

        // `</body>` is where the tab goes; `db-tab-handle` means somebody put it there already.
        return is_string($content) && stripos($content, '</body>') !== false && ! str_contains($content, 'db-tab-handle');
    }

    /** Paths the host app declared off limits (`Request::is` globs, matched on the path and on the route name). */
    protected function isExcludedPath(Request $request): bool
    {
        $patterns = array_filter((array) config('griglia.inject_tab_except', []));

        return $patterns !== [] && ($request->is(...$patterns) || $request->routeIs(...$patterns));
    }

    /** The tab has somewhere to point to and /settings did not switch it off. */
    protected function tabIsEnabled(): bool
    {
        if (! config('griglia.dashboard_route')) {
            return false;
        }

        try {
            return (bool) app(AppSettings::class)->show_dashboard_tab;
        } catch (\Throwable $e) {
            return false; // settings not migrated yet (fresh install, `migrate` still to run)
        }
    }

    /** Splice the rendered tab in just before the closing </body>. */
    protected function inject(Response $response): void
    {
        $content = (string) $response->getContent();
        $at = strripos($content, '</body>');
        if ($at === false) {
            return;
        }

        $tab = view('griglia::components.board-tab', ['standalone' => true])->render();
        $response->setContent(substr($content, 0, $at).$tab.substr($content, $at));

        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
        }
    }
}
