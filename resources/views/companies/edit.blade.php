<x-app-layout>
    <x-slot name="title">Company settings</x-slot>
    <x-slot name="header"><h1>Company settings</h1></x-slot>
    @php $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; @endphp
    <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-2">
        @csrf @method('PUT')
        <div class="card card-body space-y-4">
            <h2>Company</h2>
            <div><label class="label">Name</label><input name="name" class="input" value="{{ old('name', $company->name) }}" required></div>
            <div><label class="label">SSM registration no.</label><input name="registration_no" class="input" value="{{ old('registration_no', $company->registration_no) }}"></div>
            <div><label class="label">Address</label><textarea name="address" rows="3" class="input">{{ old('address', $company->address) }}</textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="label">Phone</label><input name="phone" class="input" value="{{ old('phone', $company->phone) }}"></div>
                <div><label class="label">Email</label><input name="email" type="email" class="input" value="{{ old('email', $company->email) }}"></div>
            </div>
            <div>
                <label class="label">Logo (for payslips)</label>
                @if ($company->logo_path)<img src="{{ asset('storage/'.$company->logo_path) }}" class="h-12 mb-2" alt="">@endif
                <input type="file" name="logo" accept="image/*" class="text-sm">
                <p class="help">PNG or JPG, up to 1 MB. Without one, the Serinstech logo is used.</p>
            </div>
        </div>
        <div class="space-y-6">
            <div class="card card-body space-y-4">
                <h2>Statutory employer numbers</h2>
                <div><label class="label">EPF employer no.</label><input name="epf_employer_no" class="input" value="{{ old('epf_employer_no', $company->epf_employer_no) }}"></div>
                <div><label class="label">SOCSO employer no.</label><input name="socso_employer_no" class="input" value="{{ old('socso_employer_no', $company->socso_employer_no) }}"></div>
                <div><label class="label">LHDN employer no. (E)</label><input name="tax_employer_no" class="input" value="{{ old('tax_employer_no', $company->tax_employer_no) }}"></div>
                <div>
                    <label class="label">HRDF levy</label>
                    <select name="hrdf_rate" class="input">
                        @foreach (['0' => 'Not registered (0%)', '0.5' => '0.5%', '1' => '1%'] as $v => $l)
                            <option value="{{ $v }}" @selected((float) old('hrdf_rate', $company->hrdf_rate) == (float) $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="card card-body">
                <h2>Working days</h2>
                <p class="help mb-3">Used to count leave days. Public holidays are set under Setup.</p>
                <div class="flex flex-wrap gap-3">
                    @foreach ($days as $n => $d)
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <input type="checkbox" name="work_days[]" value="{{ $n }}" class="rounded border-slate text-ember focus:ring-ember"
                                @checked(in_array($n, old('work_days', $company->workDays())))> {{ $d }}
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="btn-primary">Save settings</button>
        </div>
    </form>
</x-app-layout>
