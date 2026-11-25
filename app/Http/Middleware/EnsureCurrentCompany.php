<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which company the user is working in, verifies membership,
 * and shares it with every view.
 */
class EnsureCurrentCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companies = $user->accessibleCompanies();

        $company = $companies->firstWhere('id', $user->current_company_id) ?? $companies->first();

        if ($company === null) {
            if ($user->is_super_admin) {
                return $request->routeIs('companies.*') ? $next($request) : redirect()->route('companies.create');
            }
            abort(403, 'Your account is not linked to any company yet. Please contact HR.');
        }

        if ($user->current_company_id !== $company->id) {
            $user->forceFill(['current_company_id' => $company->id])->save();
        }

        $this->current->set($company, $user->roleIn($company));

        View::share([
            'currentCompany' => $company,
            'currentRole' => $this->current->role(),
            'myCompanies' => $companies,
        ]);

        return $next($request);
    }
}
