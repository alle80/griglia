<?php

namespace Alle80\Griglia\Http\Middleware;

use Alle80\Griglia\Mode;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Guards the board routes according to the mode:
 *  - local  → everybody in (no authentication);
 *  - server → an authenticated user is required (same behaviour as the `auth` middleware: redirect to
 *             login) and, on top of it, the user must pass the access check: `canAccessGriglia(): bool`
 *             on the user model if defined (Filament/Nova style), else the Gate ability named by config
 *             `griglia.access_gate` if set, else any authenticated user.
 */
class GrigliaAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (Mode::isLocal()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            $login = ! $request->expectsJson() && Route::has('login') ? route('login', absolute: false) : null;
            throw new AuthenticationException('Unauthenticated.', ['web'], $login);
        }

        abort_unless(static::allows($user), 403, __('griglia::t.errors.forbidden'));

        return $next($request);
    }

    /**
     * The same verdict as `handle()`, as a boolean and without exceptions: may this user open the board?
     * Used where there is nothing to abort — e.g. deciding whether to show the board tab (InjectBoardTab).
     */
    public static function allows(mixed $user): bool
    {
        if (Mode::isLocal()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canAccessGriglia')) {
            return (bool) $user->canAccessGriglia();
        }

        if ($gate = config('griglia.access_gate')) {
            return Gate::forUser($user)->allows($gate);
        }

        return true;
    }
}
