<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:hr') — groups are defined on the User model.
 */
class RequireRole
{
    private const GROUPS = [
        'admin' => User::ADMIN_ROLES,
        'hr' => User::HR_ROLES,
        'payroll' => User::PAYROLL_ROLES,
        'manager' => ['owner', 'admin', 'hr', 'manager'],
    ];

    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next, string $group): Response
    {
        abort_unless($this->current->roleIs(self::GROUPS[$group] ?? []), 403);

        return $next($request);
    }
}
