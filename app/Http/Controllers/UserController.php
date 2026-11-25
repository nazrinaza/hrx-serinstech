<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $company = $current->get();

        return view('users.index', [
            'users' => $company->users()->orderBy('name')->get(),
            'employees' => Employee::whereNull('user_id')->orderBy('full_name')->get(),
            'roles' => $this->assignableRoles($current),
        ]);
    }

    public function store(Request $request, CurrentCompany $current)
    {
        $company = $current->get();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'role' => ['required', Rule::in($this->assignableRoles($current))],
            'employee_id' => 'nullable|integer',
        ]);

        $user = User::firstWhere('email', $data['email']);
        if ($user && $company->users()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => 'This user already has access to this company.']);
        }

        $isNew = ! $user;
        $user ??= User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::password(32), // unknown to anyone; user sets their own via the emailed link
        ]);
        $company->users()->attach($user->id, ['role' => $data['role']]);

        if (! empty($data['employee_id'])) {
            Employee::whereKey($data['employee_id'])->whereNull('user_id')->update(['user_id' => $user->id]);
        }

        if ($isNew) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', $isNew
            ? "Account created. {$user->email} has been emailed a link to set their password."
            : "{$user->email} already had an account and now has access to {$company->name}.");
    }

    public function update(Request $request, User $user, CurrentCompany $current)
    {
        $company = $current->get();
        $this->assertMember($company, $user);
        $role = $request->validate(['role' => ['required', Rule::in($this->assignableRoles($current))]])['role'];

        if ($this->isLastOwner($company, $user) && $role !== 'owner') {
            return back()->withErrors(['role' => 'A company needs at least one owner.']);
        }

        $company->users()->updateExistingPivot($user->id, ['role' => $role]);

        return back()->with('status', "Role updated for {$user->name}.");
    }

    public function destroy(Request $request, User $user, CurrentCompany $current)
    {
        $company = $current->get();
        $this->assertMember($company, $user);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot remove your own access.']);
        }
        if ($this->isLastOwner($company, $user)) {
            return back()->withErrors(['user' => 'A company needs at least one owner.']);
        }

        $company->users()->detach($user->id);
        Employee::where('user_id', $user->id)->update(['user_id' => null]);

        return back()->with('status', "{$user->name} no longer has access to {$company->name}.");
    }

    /** Only owners can grant the owner role. */
    private function assignableRoles(CurrentCompany $current): array
    {
        return $current->role() === 'owner' ? Company::ROLES : array_values(array_diff(Company::ROLES, ['owner']));
    }

    private function assertMember(Company $company, User $user): void
    {
        $role = $company->users()->whereKey($user->id)->first()?->pivot->role;
        abort_if($role === null, 404);
        // Only owners may change or remove another owner.
        abort_if($role === 'owner' && app(CurrentCompany::class)->role() !== 'owner', 403);
    }

    private function isLastOwner(Company $company, User $user): bool
    {
        $owners = $company->users()->wherePivot('role', 'owner')->pluck('users.id');

        return $owners->count() === 1 && $owners->first() === $user->id;
    }
}
