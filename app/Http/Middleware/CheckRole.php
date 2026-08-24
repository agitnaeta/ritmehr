<?php

namespace App\Http\Middleware;

use App\Traits\ResolvesAuthenticatedUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use ResolvesAuthenticatedUser;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $this->resolveUser($request);

        if (! $user || ! $user->hasAnyRole($roles)) {
            abort(403, 'Unauthorized. You do not have the required role.');
        }

        return $next($request);
    }
}
