<x-app-layout>
    <x-slot name="title">{{ $employee->full_name }}</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-ink text-xl font-bold text-ember">{{ strtoupper(substr($employee->full_name, 0, 1)) }}</div>
            <div>
                <h1>{{ $employee->full_name }}</h1>
                <p class="text-sm text-ink/60">{{ $employee->employee_no }} · {{ $employee->position?->name ?? 'No position' }} · {{ $employee->department?->name ?? 'No department' }}
                    · @if ($employee->isActive())<span class="badge-active">Active</span>@else<span class="badge-resigned">Left {{ $employee->resign_date->format('d M Y') }}</span>@endif</p>
            </div>
        </div>
        <div class="flex gap-2">
            @if (! $employee->user_id)
                <form method="POST" action="{{ route('employees.login', $employee) }}">@csrf<button class="btn-secondary" title="Emails them a link to set a password">Create login</button></form>
            @endif
            <a href="{{ route('employees.edit', $employee) }}" class="btn-primary">Edit</a>
        </div>
    </x-slot>

    <div class="mb-4 flex gap-1 border-b border-slate">
        @foreach (['personal' => 'Profile', 'pay' => 'Salary & allowances', 'leave' => 'Leave', 'payslips' => 'Payslips'] as $k => $l)
            <a href="{{ route('employees.show', [$employee, 'tab' => $k]) }}" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if ($tab === 'personal')
        @include('employees._profile', ['employee' => $employee])

    @elseif ($tab === 'pay')
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-body border-b border-slate/50"><h2>Basic salary history</h2></div>
                <table class="table">
                    <thead><tr><th>Effective from</th><th class="num">Basic (RM)</th><th>Reason</th></tr></thead>
                    <tbody>
                    @foreach ($employee->salaries as $s)
                        <tr><td>{{ $s->effective_from->format('d M Y') }}</td><td class="num">{{ number_format($s->basic_salary, 2) }}</td><td>{{ $s->reason }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
                <form method="POST" action="{{ route('employees.salaries.store', $employee) }}" class="card-body border-t border-slate/50 grid gap-3 sm:grid-cols-4 items-end">
                    @csrf
                    <div><label class="label">From</label><input type="date" name="effective_from" class="input" required></div>
                    <div><label class="label">New basic</label><input type="number" step="0.01" name="basic_salary" class="input" required></div>
                    <div><label class="label">Reason</label><input name="reason" class="input" placeholder="Increment"></div>
                    <button class="btn-primary">Add change</button>
                </form>
            </div>

            <div class="card">
                <div class="card-body border-b border-slate/50"><h2>Recurring allowances &amp; deductions</h2></div>
                <table class="table">
                    <thead><tr><th>Item</th><th class="num">RM / month</th><th>Period</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($employee->payItems->sortByDesc('effective_from') as $i)
                        <tr class="{{ $i->effective_to && $i->effective_to->isPast() ? 'text-ink/50' : '' }}">
                            <td>{{ $i->payItem->name }} <span class="text-xs text-ink/50">{{ $i->payItem->type }}</span></td>
                            <td class="num">{{ number_format($i->amount, 2) }}</td>
                            <td class="whitespace-nowrap">{{ $i->effective_from->format('M Y') }} – {{ $i->effective_to?->format('M Y') ?? 'ongoing' }}</td>
                            <td class="text-right">
                                @unless ($i->effective_to)
                                    <form method="POST" action="{{ route('employees.pay-items.end', [$employee, $i]) }}" class="flex gap-1 justify-end">@csrf
                                        <input type="date" name="effective_to" class="input py-1 w-36" required>
                                        <button class="btn-secondary btn-sm">End</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-ink/60">None.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('employees.pay-items.store', $employee) }}" class="card-body border-t border-slate/50 grid gap-3 sm:grid-cols-4 items-end">
                    @csrf
                    <div><label class="label">Item</label><select name="pay_item_id" class="input">@foreach ($payItems as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div><label class="label">RM / month</label><input type="number" step="0.01" name="amount" class="input" required></div>
                    <div><label class="label">From</label><input type="date" name="effective_from" class="input" value="{{ now()->startOfMonth()->toDateString() }}" required></div>
                    <button class="btn-primary">Add</button>
                </form>
            </div>
        </div>

        @php $opening = $employee->ytdOpenings->firstWhere('year', now()->year); @endphp
        <form method="POST" action="{{ route('employees.ytd.store', $employee) }}" class="card card-body mt-6 max-w-4xl">
            @csrf
            <h2>Year-to-date opening balance</h2>
            <p class="help mb-3">If this employee was paid earlier in the year outside this system (before go-live, or by a previous employer), enter those totals so PCB is calculated correctly for the rest of the year.</p>
            <div class="grid gap-3 sm:grid-cols-5 items-end">
                <div><label class="label">Year</label><input type="number" name="year" class="input" value="{{ now()->year }}" required></div>
                <div><label class="label">Taxable pay (RM)</label><input type="number" step="0.01" name="remuneration" class="input" value="{{ $opening->remuneration ?? 0 }}" required></div>
                <div><label class="label">Employee EPF (RM)</label><input type="number" step="0.01" name="epf" class="input" value="{{ $opening->epf ?? 0 }}" required></div>
                <div><label class="label">PCB paid (RM)</label><input type="number" step="0.01" name="pcb" class="input" value="{{ $opening->pcb ?? 0 }}" required></div>
                <button class="btn-primary">Save</button>
            </div>
        </form>

    @elseif ($tab === 'leave')
        <form class="mb-4 flex gap-2 items-center"><input type="hidden" name="tab" value="leave">
            <label class="text-sm">Year</label><input type="number" name="year" value="{{ $year }}" class="input w-28"><button class="btn-dark btn-sm">Show</button></form>
        <div class="card overflow-x-auto mb-6">
            <table class="table">
                <thead><tr><th>Leave type</th><th class="num">Entitled</th><th class="num">Carried fwd</th><th class="num">Adjustment</th><th class="num">Taken</th><th class="num">Pending</th><th class="num">Balance</th><th></th></tr></thead>
                @foreach ($balances as $b)
                    <tbody x-data="{ edit: false }">
                    <tr>
                        <td>{{ $b['type']->name }} @unless ($b['type']->is_paid)<span class="text-xs text-ink/50">unpaid</span>@endunless</td>
                        <td class="num">{{ $b['entitlement']->days + 0 }}</td>
                        <td class="num">{{ $b['entitlement']->carried_forward + 0 }}</td>
                        <td class="num">{{ $b['entitlement']->adjustment + 0 }}</td>
                        <td class="num">{{ $b['taken'] }}</td>
                        <td class="num">{{ $b['pending'] }}</td>
                        <td class="num font-semibold">{{ $b['type']->is_paid ? $b['balance'] : '—' }}</td>
                        <td class="text-right"><button type="button" class="btn-secondary btn-sm" @click="edit = !edit">Adjust</button></td>
                    </tr>
                    <tr class="bg-mist" x-cloak x-show="edit">
                        <td colspan="8">
                            <form method="POST" action="{{ route('employees.entitlements.adjust', [$employee, $b['entitlement']]) }}" class="flex flex-wrap gap-3 items-end">@csrf
                                <div><label class="label">Entitled</label><input type="number" step="0.5" name="days" value="{{ $b['entitlement']->days + 0 }}" class="input w-28"></div>
                                <div><label class="label">Carried fwd</label><input type="number" step="0.5" name="carried_forward" value="{{ $b['entitlement']->carried_forward + 0 }}" class="input w-28"></div>
                                <div><label class="label">Adjustment (+/−)</label><input type="number" step="0.5" name="adjustment" value="{{ $b['entitlement']->adjustment + 0 }}" class="input w-28"></div>
                                <button class="btn-primary btn-sm">Save</button>
                            </form>
                        </td>
                    </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
        @include('leave._history', ['history' => $leaveHistory, 'showEmployee' => false, 'cancelRoute' => 'leave.cancel'])

    @else
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Month</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($payslips->sortByDesc(fn ($p) => $p->run->year * 100 + $p->run->month) as $p)
                    <tr>
                        <td>{{ $p->run->label() }}</td>
                        <td class="num">{{ number_format($p->gross_pay, 2) }}</td>
                        <td class="num">{{ number_format($p->total_deductions, 2) }}</td>
                        <td class="num font-semibold">{{ number_format($p->net_pay, 2) }}</td>
                        <td><span class="badge-{{ $p->run->status }}">{{ ucfirst($p->run->status) }}</span></td>
                        <td class="text-right"><a class="btn-secondary btn-sm" href="{{ route('payroll.payslip', $p) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-ink/60">No payslips yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
