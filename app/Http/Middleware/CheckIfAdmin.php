<?php

namespace App\Http\Middleware;

use Closure;

class CheckIfAdmin
{
    /**
     * Roles allowed into the Backpack admin panel.
     */
    private const ADMIN_ROLES = ['super_admin', 'hr_admin', 'manager'];

    /**
     * Admins and regular employees share the `users` table, so this decides
     * who may see /admin/*.
     *
     * A user with no roles at all is treated as an admin: this app predates
     * roles, and existing accounts must not be locked out by the upgrade.
     * Only accounts explicitly limited to `employee` are pushed to the portal.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     */
    private function checkIfUserIsAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->roles()->count() === 0) {
            return true;
        }

        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    /**
     * Answer to unauthorized access request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    private function respondToUnauthorizedRequest($request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response(trans('backpack::base.unauthorized'), 401);
        }

        // A signed-in employee is not unauthenticated — send them to the
        // portal instead of bouncing them back to a login form.
        if (backpack_auth()->check()) {
            return redirect()->route('portal.dashboard');
        }

        return redirect()->guest(backpack_url('login'));
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (backpack_auth()->guest()) {
            return $this->respondToUnauthorizedRequest($request);
        }

        if (! $this->checkIfUserIsAdmin(backpack_user())) {
            return $this->respondToUnauthorizedRequest($request);
        }

        return $next($request);
    }
}
