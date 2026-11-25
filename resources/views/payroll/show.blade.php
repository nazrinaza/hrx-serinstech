<x-app-layout>
    <x-slot name="title">Payroll {{ $run->label() }}</x-slot>
    <x-slot name="header">
        <div>
            <h1>Payroll · {{ $run->label() }}</h1>
            <p class="text-sm text-ink/60">Pay date {{ $run->pay_date->format('d M Y') }} ·
                <span class="badge-{{ $run->status }}">{{ ucfirst($run->status) }}</span>
                @if ($run->is_published)<span class="badge-published">Published to employees</span>@endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($run->isDraft())
                <form method="POST" action="{{ route('payroll.recalculate', $run) }}">@csrf<button class="btn-secondary">Recalculate</button></form>
                <form method="POST" action="{{ route('payroll.destroy', $run) }}" onsubmit="return confirm('Delete this draft payroll?')">@csrf @method('DELETE')<button class="btn-danger">Delete draft</button></form>
                <form method="POST" action="{{ route('payroll.finalize', $run) }}" onsubmit="return confirm('Finalize {{ $run->label() }}? Amounts will be locked.')">@csrf<button class="btn-primary">Finalize</button></form>
            @else
                <a href="{{ route('payroll.bank-file', $run) }}" class="btn-secondary">Bank file (CSV)</a>
                <a href="{{ route('payroll.statutory', $run) }}" class="btn-secondary">Statutory report (CSV)</a>
                @unless ($run->is_published)
                    <form method="POST" action="{{ route('payroll.reopen', $run) }}">@csrf<button class="btn-secondary">Reopen</button></form>
                @endunless
                <form method="POST" action="{{ route('payroll.publish', $run) }}">@csrf
                    <input type="hidden" name="publish" value="{{ $run->is_published ? 0 : 1 }}">
                    <button class="{{ $run->is_published ? 'btn-secondary' : 'btn-primary' }}">{{ $run->is_published ? 'Unpublish payslips' : 'Publish payslips' }}</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5 mb-6">
        @foreach ([
            ['Gross pay', $totals['gross_pay']], ['Net pay', $totals['net_pay']],
            ['EPF (ee + er)', $totals['epf_employee'] + $totals['epf_employer']],
            ['SOCSO + EIS (ee + er)', $totals['socso_employee'] + $totals['socso_employer'] + $totals['eis_employee'] + $totals['eis_employer']],
            ['PCB', $totals['pcb']],
        ] as [$label, $value])
            <div class="card card-body"><div class="text-xs font-semibold uppercase tracking-wide text-ink/60">{{ $label }}</div><div class="mt-1 text-xl font-bold tabular-nums">RM {{ number_format($value, 2) }}</div></div>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr>
                <th>Employee</th><th class="num">Basic</th><th class="num">Gross</th><th class="num">EPF</th><th class="num">SOCSO</th>
                <th class="num">EIS</th><th class="num">PCB</th><th class="num">Other ded.</th><th class="num">Net</th><th class="num">Employer cost</th>
            </tr></thead>
            <tbody>
            @forelse ($payslips as $p)
                @php
                    $statEe = $p->epf_employee + $p->socso_employee + $p->eis_employee + $p->pcb;
                    $employerCost = $p->gross_pay + $p->epf_employer + $p->socso_employer + $p->eis_employer + $p->hrdf;
                @endphp
                <tr class="cursor-pointer" onclick="location='{{ route('payroll.payslip', $p) }}'">
                    <td><a href="{{ route('payroll.payslip', $p) }}" class="font-medium">{{ $p->employee_snapshot['full_name'] }}</a>
                        <div class="text-xs text-ink/50">{{ $p->employee_snapshot['employee_no'] }}@if ($p->days_worked_ratio < 1) · prorated {{ round($p->days_worked_ratio * 100) }}%@endif @if ($p->unpaid_leave_days > 0) · {{ $p->unpaid_leave_days + 0 }} unpaid day(s)@endif</div></td>
                    <td class="num">{{ number_format($p->basic_salary, 2) }}</td>
                    <td class="num">{{ number_format($p->gross_pay, 2) }}</td>
                    <td class="num">{{ number_format($p->epf_employee, 2) }}</td>
                    <td class="num">{{ number_format($p->socso_employee, 2) }}</td>
                    <td class="num">{{ number_format($p->eis_employee, 2) }}</td>
                    <td class="num">{{ number_format($p->pcb, 2) }}</td>
                    <td class="num">{{ number_format($p->total_deductions - $statEe, 2) }}</td>
                    <td class="num font-semibold">{{ number_format($p->net_pay, 2) }}</td>
                    <td class="num text-ink/70">{{ number_format($employerCost, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-ink/60">No employees with a salary in this month.</td></tr>
            @endforelse
            </tbody>
            @if ($payslips->isNotEmpty())
                <tfoot class="font-semibold bg-mist">
                    <tr><td class="px-3 py-2">Total ({{ $payslips->count() }})</td><td></td>
                        <td class="num px-3">{{ number_format($totals['gross_pay'], 2) }}</td><td class="num px-3">{{ number_format($totals['epf_employee'], 2) }}</td>
                        <td class="num px-3">{{ number_format($totals['socso_employee'], 2) }}</td><td class="num px-3">{{ number_format($totals['eis_employee'], 2) }}</td>
                        <td class="num px-3">{{ number_format($totals['pcb'], 2) }}</td><td></td>
                        <td class="num px-3">{{ number_format($totals['net_pay'], 2) }}</td><td></td></tr>
                </tfoot>
            @endif
        </table>
    </div>
    <p class="help mt-2">Statutory figures follow the rates in <code>config/statutory.php</code>. Check a sample against the official KWSP, PERKESO and LHDN calculators before paying.</p>
</x-app-layout>
