<?php

namespace App\Http\Middleware;

use App\Traits\ResolvesAuthenticatedUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    use ResolvesAuthenticatedUser;

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $this->resolveUser($request);

        if (! $user || ! $user->hasAnyPermission($permissions)) {
            abort(403, 'Unauthorized. You do not have the required permission.');
        }

        return $next($request);
    }
}
