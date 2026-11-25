<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="header"><h1>{{ $title }}</h1></x-slot>
    @php $rules = ['fixed' => 'Fixed days', 'annual_ea' => 'Annual (EA: 8/12/16 by service)', 'sick_ea' => 'Sick (EA: 14/18/22 by service)']; @endphp
    <div class="space-y-4">
        @foreach ($rows->push(new \App\Models\LeaveType(['is_paid' => true, 'is_active' => true, 'entitlement_rule' => 'fixed', 'default_days' => 0, 'max_carry_forward' => 0])) as $row)
            <form method="POST" action="{{ $row->exists ? route('setup.update', [$kind, $row->id]) : route('setup.store', $kind) }}" class="card card-body {{ $row->exists ? '' : 'border-dashed border-ember' }}">
                @csrf @if ($row->exists) @method('PUT') @endif
                @unless ($row->exists)<h2 class="mb-3">Add leave type</h2>@endunless
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
                    <div class="lg:col-span-2"><label class="label">Name</label><input name="name" class="input" value="{{ $row->name }}" required></div>
                    <div><label class="label">Code</label><input name="code" class="input" value="{{ $row->code }}" required></div>
                    <div class="lg:col-span-2"><label class="label">Entitlement</label>
                        <select name="entitlement_rule" class="input">@foreach ($rules as $v => $l)<option value="{{ $v }}" @selected($row->entitlement_rule === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div><label class="label">Days / year</label><input name="default_days" type="number" step="0.5" class="input" value="{{ $row->default_days + 0 }}"></div>
                    <div><label class="label">Max carry forward</label><input name="max_carry_forward" type="number" step="0.5" class="input" value="{{ $row->max_carry_forward + 0 }}"></div>
                    <div><label class="label">Only for</label>
                        <select name="gender" class="input"><option value="">Everyone</option><option value="female" @selected($row->gender === 'female')>Female</option><option value="male" @selected($row->gender === 'male')>Male</option></select></div>
                    <div class="lg:col-span-3 flex flex-wrap gap-4 text-sm pb-2">
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="is_paid" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked($row->is_paid)> Paid</label>
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="requires_attachment" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked($row->requires_attachment)> Needs document</label>
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked($row->is_active)> Active</label>
                    </div>
                    <div class="text-right"><button class="btn-primary btn-sm">{{ $row->exists ? 'Save' : 'Add' }}</button></div>
                </div>
            </form>
        @endforeach
        <p class="help">For EA rules the higher of the statutory tier and "Days / year" is used. Annual leave is prorated in the joining year. Unpaid leave types have no balance and are deducted in payroll.</p>
    </div>
</x-app-layout>
