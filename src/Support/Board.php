<?php

namespace Alle80\Griglia\Support;

use Illuminate\Support\Facades\Route;

/**
 * Where the board lives, as a URL.
 *
 * Always built from the package's own named routes. The `dashboard_route` config is a bare path
 * («/dashboard» by default, the same one Breeze and Jetstream take): a host application that already
 * owns that path keeps it, the package route never wins, and anything pointing at the raw path — the
 * slide-out tab above all — would frame the host's own dashboard instead of the board (task 646).
 */
class Board
{
    /** The board URL, or null when the host application registered neither board route. */
    public static function url(): ?string
    {
        foreach (['griglia.home', 'griglia.dashboard'] as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }

        return null;
    }
}
