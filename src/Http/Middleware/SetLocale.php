<?php

namespace Alle80\Griglia\Http\Middleware;

use Alle80\Griglia\Support\Locale;
use Closure;
use Illuminate\Http\Request;

/**
 * Dresses every board page with the language chosen in /settings (setting `app.locale`).
 * It is also a Livewire «persistent» middleware, so the /livewire/update requests
 * (modals, saves) return the same strings as the page.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        Locale::apply();

        return $next($request);
    }
}
