<?php

namespace Alle80\Griglia\Http\Middleware;

use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Themes;
use Closure;
use Illuminate\Http\Request;

/**
 * Remembers in the session the style of the list you are looking at (manga, jack, c64, slate…),
 * so the pages "without a style of their own" (e.g. /settings) dress the same way.
 */
class RememberStyle
{
    public function handle(Request $request, Closure $next)
    {
        $prefix = trim((string) config('griglia.route_prefix', ''), '/');
        $slug = trim($request->path(), '/');
        if ($prefix !== '' && str_starts_with($slug, $prefix)) {
            $slug = trim(substr($slug, strlen($prefix)), '/');
        }
        if ($slug === '') {
            $configured = app(AppSettings::class)->default_style;
            session(['style' => Themes::has($configured) ? $configured : Themes::default()]);
        }

        return $next($request);
    }

    /** Current style: session, then the default style from /settings, then the default theme. */
    public static function current(): string
    {
        $style = session('style') ?: (app(AppSettings::class)->default_style ?: Themes::default());

        return Themes::known($style) ? $style : Themes::default();
    }
}
