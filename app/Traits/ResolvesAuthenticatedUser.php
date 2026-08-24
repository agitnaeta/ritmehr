<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the acting user across both guards this app uses.
 *
 * Backpack authenticates admins on the `backpack` guard, while the employee
 * portal and API use the default `web` guard. Anything that needs "the user
 * making this request" has to check both.
 */
trait ResolvesAuthenticatedUser
{
    protected function resolveUser(?Request $request = null): ?User
    {
        $guard = config('backpack.base.guard');

        if ($guard && auth()->guard($guard)->check()) {
            return auth()->guard($guard)->user();
        }

        return $request ? $request->user() : auth()->user();
    }
}
