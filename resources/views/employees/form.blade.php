<x-app-layout>
    @php $new = ! $employee->exists; $v = fn ($f) => old($f, $employee->{$f} instanceof \Carbon\CarbonInterface ? $employee->{$f}->toDateString() : $employee->{$f}); @endphp
    <x-slot name="title">{{ $new ? 'Add employee' : 'Edit '.$employee->full_name }}</x-slot>
    <x-slot name="header"><h1>{{ $new ? 'Add employee' : 'Edit '.$employee->full_name }}</h1></x-slot>

    <form method="POST" action="{{ $new ? route('employees.store') : route('employees.update', $employee) }}" class="space-y-6">
        @csrf @unless ($new) @method('PUT') @endunless

        <div class="card card-body">
            <h2 class="mb-4">Personal</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><label class="label">Employee no. *</label><input name="employee_no" class="input" value="{{ $v('employee_no') }}" required></div>
                <div class="lg:col-span-2"><label class="label">Full name (as in IC) *</label><input name="full_name" class="input" value="{{ $v('full_name') }}" required></div>
                <div><label class="label">Citizenship *</label>
                    <select name="citizenship" class="input">@foreach (['citizen' => 'Malaysian', 'pr' => 'Permanent resident', 'foreigner' => 'Foreigner'] as $k => $l)<option value="{{ $k }}" @selected($v('citizenship') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="label">IC no.</label><input name="ic_no" class="input" value="{{ $v('ic_no') }}" placeholder="900101-14-5678"></div>
                <div><label class="label">Passport no.</label><input name="passport_no" class="input" value="{{ $v('passport_no') }}"></div>
                <div><label class="label">Gender</label>
                    <select name="gender" class="input"><option value="">—</option><option value="male" @selected($v('gender') === 'male')>Male</option><option value="female" @selected($v('gender') === 'female')>Female</option></select></div>
                <div><label class="label">Date of birth</label><input type="date" name="date_of_birth" class="input" value="{{ $v('date_of_birth') }}"><p class="help">Needed for EPF/SOCSO/EIS age rules.</p></div>
                <div><label class="label">Marital status *</label>
                    <select name="marital_status" class="input">@foreach (['single', 'married', 'divorced', 'widowed'] as $m)<option value="{{ $m }}" @selected($v('marital_status') === $m)>{{ ucfirst($m) }}</option>@endforeach</select></div>
                <div><label class="label">Email</label><input type="email" name="email" class="input" value="{{ $v('email') }}"><p class="help">Used for their login and leave emails.</p></div>
                <div><label class="label">Phone</label><input name="phone" class="input" value="{{ $v('phone') }}"></div>
                <div class="sm:col-span-2 lg:col-span-3"><label class="label">Address</label><textarea name="address" rows="2" class="input">{{ $v('address') }}</textarea></div>
            </div>
        </div>

        <div class="card card-body">
            <h2 class="mb-4">Job</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><label class="label">Department</label>
                    <select name="department_id" class="input"><option value="">—</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected($v('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                <div><label class="label">Position</label>
                    <select name="position_id" class="input"><option value="">—</option>@foreach ($positions as $p)<option value="{{ $p->id }}" @selected($v('position_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                <div><label class="label">Line manager</label>
                    <select name="manager_id" class="input"><option value="">— (HR approves leave)</option>@foreach ($managers as $m)<option value="{{ $m->id }}" @selected($v('manager_id') == $m->id)>{{ $m->full_name }}</option>@endforeach</select></div>
                <div><label class="label">Employment type *</label>
                    <select name="employment_type" class="input">@foreach (['permanent', 'contract', 'probation', 'intern'] as $t)<option value="{{ $t }}" @selected($v('employment_type') === $t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
                <div><label class="label">Join date *</label><input type="date" name="join_date" class="input" value="{{ $v('join_date') }}" required></div>
                <div><label class="label">Last working day</label><input type="date" name="resign_date" class="input" value="{{ $v('resign_date') }}"><p class="help">Leave blank while employed.</p></div>
                @if ($new)
                    <div><label class="label">Basic salary (RM / month) *</label><input type="number" step="0.01" name="basic_salary" class="input" value="{{ old('basic_salary') }}" required></div>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card card-body space-y-4">
                <h2>Bank</h2>
                <div><label class="label">Bank</label><input name="bank_name" class="input" value="{{ $v('bank_name') }}" placeholder="Maybank"></div>
                <div><label class="label">Account no.</label><input name="bank_account_no" class="input" value="{{ $v('bank_account_no') }}"></div>
            </div>
            <div class="card card-body">
                <h2 class="mb-4">Statutory &amp; tax</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="label">EPF no.</label><input name="epf_no" class="input" value="{{ $v('epf_no') }}"></div>
                    <div><label class="label">SOCSO no.</label><input name="socso_no" class="input" value="{{ $v('socso_no') }}"></div>
                    <div><label class="label">Income tax no.</label><input name="tax_no" class="input" value="{{ $v('tax_no') }}"></div>
                    <div><label class="label">EPF employee rate %</label><input type="number" step="0.01" name="epf_employee_rate" class="input" value="{{ $v('epf_employee_rate') }}" placeholder="Default"><p class="help">Only if they chose a higher rate.</p></div>
                    <div><label class="label">Children (tax relief)</label><input type="number" min="0" name="children_count" class="input" value="{{ $v('children_count') ?? 0 }}"></div>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 text-sm">
                    @foreach (['is_tax_resident' => 'Tax resident in Malaysia', 'spouse_working' => 'Spouse has income', 'is_disabled' => 'Employee is disabled (OKU)', 'spouse_disabled' => 'Spouse is disabled'] as $f => $l)
                        <label class="inline-flex items-center gap-2"><input type="hidden" name="{{ $f }}" value="0"><input type="checkbox" name="{{ $f }}" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked($v($f))> {{ $l }}</label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <button class="btn-primary">{{ $new ? 'Add employee' : 'Save changes' }}</button>
            <a href="{{ $new ? route('employees.index') : route('employees.show', $employee) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
