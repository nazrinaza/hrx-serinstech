<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\CurrentCompany;
use Database\Seeders\CompanyDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function switch(Request $request)
    {
        $user = $request->user();
        $id = (int) $request->validate(['company_id' => 'required|integer'])['company_id'];
        abort_unless($user->accessibleCompanies()->contains('id', $id), 403);

        $user->forceFill(['current_company_id' => $id])->save();

        return redirect()->route('dashboard');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->is_super_admin, 403);

        return view('companies.index', [
            'companies' => Company::withCount(['employees', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->is_super_admin, 403);

        return view('companies.create');
    }

    public function store(Request $request, CurrentCompany $current)
    {
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'registration_no' => 'nullable|string|max:50',
        ]);

        $company = DB::transaction(function () use ($data, $user, $current) {
            $company = Company::create($data + ['work_days' => [1, 2, 3, 4, 5]]);
            $company->users()->attach($user->id, ['role' => 'owner']);
            $current->set($company, 'owner');
            CompanyDefaults::apply($company);

            return $company;
        });

        $user->forceFill(['current_company_id' => $company->id])->save();

        return redirect()->route('company.edit')->with('status', "{$company->name} created. Complete the company details below.");
    }

    public function edit(CurrentCompany $current)
    {
        return view('companies.edit', ['company' => $current->get()]);
    }

    public function update(Request $request, CurrentCompany $current)
    {
        $company = $current->get();
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name,'.$company->id,
            'registration_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'epf_employer_no' => 'nullable|string|max:30',
            'socso_employer_no' => 'nullable|string|max:30',
            'tax_employer_no' => 'nullable|string|max:30',
            'hrdf_rate' => 'required|in:0,0.5,1',
            'work_days' => 'required|array|min:1',
            'work_days.*' => 'integer|between:1,7',
            'logo' => 'nullable|image|max:1024',
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);
        $data['work_days'] = array_map('intval', $data['work_days']);

        $company->update($data);

        return back()->with('status', 'Company settings saved.');
    }
}
