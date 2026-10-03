<?php

namespace Tests\Feature;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Guard rail for CWE-862: every admin route that WRITES must have a write
 * check somewhere a reviewer can see it. Route middleware in this app only
 * gates `.view` (routes/backpack/custom.php), so a write check has to live in
 * the controller — and it is easy to forget when adding a module.
 *
 * This is a static check: it proves a check exists, not that it names the
 * right permission. AdminWriteAccessTest covers behaviour per module.
 */
class AdminWriteAccessCoverageTest extends TestCase
{
    /**
     * Write routes that are safe without a permission check, with the reason.
     * Keys are "Controller@method" (short class name).
     */
    private const ALLOWED = [
        // Auth flows act on the requester's own credentials.
        'LoginController@login'                         => 'authentication',
        'LoginController@logout'                        => 'authentication',
        'RegisterController@register'                   => 'authentication (registration is disabled in config)',
        'ForgotPasswordController@sendResetLinkEmail'   => 'authentication',
        'ResetPasswordController@reset'                 => 'authentication',
        'MyAccountController@postAccountInfoForm'       => "edits the requester's own account",
        'MyAccountController@postChangePasswordForm'    => "changes the requester's own password",
        // Backpack list/search endpoints are reads sent as POST.
        '*@search'                                      => 'list data (read) sent as POST',
        'NotificationController@markAllRead'            => "marks the requester's own notifications",
        // Authorised per record in the service layer.
        'ApprovalCrudController@approve'                => 'ApprovalService::lockAndAuthorise — only the current step approver',
        'ApprovalCrudController@reject'                 => 'ApprovalService::lockAndAuthorise — only the current step approver',
        'ApprovalCrudController@cancel'                 => 'ApprovalService::cancel — only the requester',
        'LeaveRequestCrudController@cancel'             => 'LeaveService::cancel — only the requester',
    ];

    /**
     * Patterns that count as an explicit permission/role check inside a method
     * body. A bare abort_unless() is NOT enough — it is also used for 404s.
     */
    private const METHOD_CHECKS = [
        'hasAccessOrFail(', '->can(', '->canAny(', 'hasPermissionTo(', 'hasAnyPermission(', 'hasRole(', 'hasAnyRole(', 'Gate::',
    ];

    public function test_every_admin_write_route_has_a_write_check(): void
    {
        $missing = [];

        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), config('backpack.base.route_prefix', 'admin'))) {
                continue;
            }
            if (! array_diff($route->methods(), ['GET', 'HEAD'])) {
                continue;
            }

            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action);
            $key = class_basename($class) . '@' . $method;

            if (isset(self::ALLOWED[$key]) || isset(self::ALLOWED['*@' . $method])) {
                continue;
            }

            if ($this->routeRequiresWritePermission($route)) {
                continue;
            }

            if (! $this->isGuarded($class, $method)) {
                $missing[] = implode('|', $route->methods()) . ' ' . $route->uri() . "  →  {$key}";
            }
        }

        $this->assertSame([], $missing, "Admin write routes without a write-permission check:\n  "
            . implode("\n  ", $missing)
            . "\n\nAdd a denyAccess/hasAccessOrFail/abort_unless(can(...)) check, or — if it is"
            . " genuinely safe — an entry in AdminWriteAccessCoverageTest::ALLOWED with the reason.");
    }

    /** A `permission:` middleware that names anything other than a `.view` permission. */
    private function routeRequiresWritePermission(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            $permissions = explode(',', substr($middleware, strlen('permission:')));
            foreach ($permissions as $permission) {
                if (! str_ends_with($permission, '.view')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isGuarded(string $class, string $method): bool
    {
        $reflection = new \ReflectionClass($class);

        if ($this->setupAbortsWithoutPermission($reflection) || $this->constructorGuardsEveryAction($reflection)) {
            return true;
        }

        // Trait methods (Backpack's operations) report the controller as their
        // declaring class, so compare files to find a real override.
        $declared = $reflection->hasMethod($method)
            && $reflection->getMethod($method)->getFileName() === $reflection->getFileName();

        // Backpack's own store/update/destroy call hasAccessOrFail(); they are
        // only safe if the controller actually denies writes to someone.
        if (! $declared) {
            return $reflection->isSubclassOf(CrudController::class)
                && str_contains($this->source($reflection), 'denyAccess');
        }

        $body = $this->methodSource($reflection->getMethod($method));

        // An overridden CRUD write must enforce access FIRST, before validation
        // or side effects.
        if ($reflection->isSubclassOf(CrudController::class) && in_array($method, ['store', 'update', 'destroy'], true)) {
            return (bool) preg_match('/^\s*\{\s*(\/\/[^\n]*\n\s*)*(\$this->crud->hasAccessOrFail|CRUD::hasAccessOrFail)\(/', $this->afterSignature($body));
        }

        return $this->containsCheck($body)
            || $this->callsCheckingHelper($reflection, $body);
    }

    private function containsCheck(string $source): bool
    {
        foreach (self::METHOD_CHECKS as $needle) {
            if (str_contains($source, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** One level of indirection: `$this->guardEdit()` where guardEdit() aborts. */
    private function callsCheckingHelper(\ReflectionClass $reflection, string $body): bool
    {
        preg_match_all('/\$this->(\w+)\(/', $body, $calls);

        foreach (array_unique($calls[1]) as $name) {
            if (! $reflection->hasMethod($name)) {
                continue;
            }

            $helper = $reflection->getMethod($name);
            if ($helper->getFileName() !== $reflection->getFileName() || $helper->isPublic()) {
                continue;
            }

            if ($this->containsCheck($this->methodSource($helper))) {
                return true;
            }
        }

        return false;
    }

    /** `$this->middleware(fn ...)` in the constructor that aborts without a permission. */
    private function constructorGuardsEveryAction(\ReflectionClass $reflection): bool
    {
        $constructor = $reflection->getConstructor();
        if (! $constructor || $constructor->getFileName() !== $reflection->getFileName()) {
            return false;
        }

        $body = $this->methodSource($constructor);

        return str_contains($body, '$this->middleware(') && str_contains($body, '->can(')
            && (bool) preg_match('/abort(_unless|_if)?\s*\(/', $body);
    }

    /** Controllers like RoleCrudController refuse the whole panel in setup(). */
    private function setupAbortsWithoutPermission(\ReflectionClass $reflection): bool
    {
        if (! $reflection->hasMethod('setup')) {
            return false;
        }

        $setup = $reflection->getMethod('setup');
        if ($setup->getFileName() !== $reflection->getFileName()) {
            return false;
        }

        $body = $this->methodSource($setup);

        return (bool) preg_match('/abort(_unless)?\s*\(/', $body) && str_contains($body, '->can(');
    }

    private function source(\ReflectionClass $reflection): string
    {
        return file_get_contents($reflection->getFileName());
    }

    private function methodSource(\ReflectionMethod $method): string
    {
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    private function afterSignature(string $body): string
    {
        return substr($body, strpos($body, '{'));
    }
}
