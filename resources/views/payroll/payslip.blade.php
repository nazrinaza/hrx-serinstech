<x-app-layout>
    @php $s = $payslip->employee_snapshot; $draft = $payslip->run->isDraft(); @endphp
    <x-slot name="title">Payslip {{ $s['full_name'] }}</x-slot>
    <x-slot name="header">
        <div>
            <a href="{{ route('payroll.show', $payslip->run) }}" class="text-sm underline text-ink/60">← {{ $payslip->run->label() }}</a>
            <h1>{{ $s['full_name'] }}</h1>
            <p class="text-sm text-ink/60">{{ $s['employee_no'] }} · {{ $s['position'] ?? '' }}</p>
        </div>
        <a href="{{ route('payroll.payslip.pdf', $payslip) }}" class="btn-primary">Download PDF</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            @foreach (['earning' => 'Earnings', 'deduction' => 'Deductions'] as $type => $label)
                <div class="card">
                    <div class="card-body border-b border-slate/50"><h2>{{ $label }}</h2></div>
                    <table class="table">
                        <tbody>
                        @foreach ($payslip->lines->where('type', $type) as $l)
                            <tr>
                                <td>{{ $l->name }} @if ($l->is_manual)<span class="text-xs text-ink/50">one-off</span>@endif</td>
                                <td class="text-xs text-ink/50">{{ collect($l->flags ?? [])->filter()->keys()->map(fn ($k) => strtoupper($k))->implode(' · ') }}</td>
                                <td class="num">{{ number_format($l->amount, 2) }}</td>
                                <td class="w-16 text-right">
                                    @if ($draft && $l->is_manual)
                                        <form method="POST" action="{{ route('payroll.payslip.lines.destroy', $l) }}">@csrf @method('DELETE')<button class="btn-danger btn-sm">✕</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if ($type === 'deduction')
                            @foreach (['EPF' => $payslip->epf_employee, 'SOCSO' => $payslip->socso_employee, 'EIS' => $payslip->eis_employee, 'PCB (income tax)' => $payslip->pcb] as $n => $v)
                                <tr><td>{{ $n }}</td><td class="text-xs text-ink/50">statutory</td><td class="num">{{ number_format($v, 2) }}</td><td></td></tr>
                            @endforeach
                        @endif
                        </tbody>
                        <tfoot class="bg-mist font-semibold"><tr>
                            <td class="px-3 py-2" colspan="2">Total {{ strtolower($label) }}</td>
                            <td class="num px-3">{{ number_format($type === 'earning' ? $payslip->gross_pay : $payslip->total_deductions, 2) }}</td><td></td>
                        </tr></tfoot>
                    </table>
                </div>
            @endforeach

            @if ($draft)
                <form method="POST" action="{{ route('payroll.payslip.lines.store', $payslip) }}" class="card card-body space-y-3" x-data="{ type: 'earning' }">
                    @csrf
                    <h2>Add one-off item for this month</h2>
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="sm:col-span-2"><label class="label">Description</label><input name="name" class="input" placeholder="e.g. Overtime 10 hrs" required></div>
                        <div><label class="label">Type</label><select name="type" class="input" x-model="type"><option value="earning">Earning</option><option value="deduction">Deduction</option></select></div>
                        <div><label class="label">Amount (RM)</label><input type="number" step="0.01" min="0.01" name="amount" class="input" required></div>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <span class="text-ink/60" x-text="type === 'earning' ? 'Counts towards:' : 'Reduces wages for:'"></span>
                        @foreach (['epf' => 'EPF', 'socso' => 'SOCSO', 'eis' => 'EIS', 'pcb' => 'PCB', 'hrdf' => 'HRDF'] as $f => $n)
                            <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="{{ $f }}" value="1" class="rounded border-slate text-ember focus:ring-ember" :checked="type === 'earning'"> {{ $n }}</label>
                        @endforeach
                    </div>
                    <p class="help">Overtime: untick EPF. Expense claims: untick all. Bonuses aren't supported in PCB yet — see README.</p>
                    <button class="btn-primary">Add &amp; recalculate</button>
                </form>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card card-body bg-ink text-white">
                <div class="text-xs uppercase tracking-wide text-white/60">Net pay</div>
                <div class="text-3xl font-bold text-ember tabular-nums">RM {{ number_format($payslip->net_pay, 2) }}</div>
                <div class="mt-2 text-sm text-white/70">{{ $s['bank_name'] ?? 'No bank' }} · {{ $s['bank_account_no'] ?? '—' }}</div>
            </div>
            <div class="card card-body text-sm">
                <h2 class="mb-2">Employer contributions</h2>
                <dl class="space-y-1">
                    @foreach (['EPF' => $payslip->epf_employer, 'SOCSO' => $payslip->socso_employer, 'EIS' => $payslip->eis_employer, 'HRDF' => $payslip->hrdf] as $n => $v)
                        <div class="flex justify-between"><dt>{{ $n }}</dt><dd class="tabular-nums">{{ number_format($v, 2) }}</dd></div>
                    @endforeach
                </dl>
                <h2 class="mt-4 mb-2">Wage bases</h2>
                <dl class="space-y-1">
                    <div class="flex justify-between"><dt>EPF wages</dt><dd class="tabular-nums">{{ number_format($payslip->epf_wages, 2) }}</dd></div>
                    <div class="flex justify-between"><dt>PCB taxable</dt><dd class="tabular-nums">{{ number_format($payslip->pcb_taxable, 2) }}</dd></div>
                    @if ($payslip->days_worked_ratio < 1)<div class="flex justify-between"><dt>Days worked</dt><dd>{{ round($payslip->days_worked_ratio * 100, 1) }}%</dd></div>@endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
