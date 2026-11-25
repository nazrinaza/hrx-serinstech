<x-app-layout>
    <x-slot name="title">Payroll</x-slot>
    <x-slot name="header"><h1>Payroll runs</h1></x-slot>
    <div class="grid gap-6 xl:grid-cols-4">
        <div class="card overflow-x-auto xl:col-span-3">
            <table class="table">
                <thead><tr><th>Month</th><th>Pay date</th><th class="num">Employees</th><th class="num">Gross (RM)</th><th class="num">Net (RM)</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($runs as $r)
                    <tr class="cursor-pointer" onclick="location='{{ route('payroll.show', $r) }}'">
                        <td class="font-medium"><a href="{{ route('payroll.show', $r) }}">{{ $r->label() }}</a></td>
                        <td>{{ $r->pay_date->format('d M Y') }}</td>
                        <td class="num">{{ $r->payslips_count }}</td>
                        <td class="num">{{ number_format($r->payslips_sum_gross_pay ?? 0, 2) }}</td>
                        <td class="num">{{ number_format($r->payslips_sum_net_pay ?? 0, 2) }}</td>
                        <td><span class="badge-{{ $r->status }}">{{ ucfirst($r->status) }}</span> @if ($r->is_published)<span class="badge-published">Published</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-ink/60">No payroll yet. Start your first month on the right.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $runs->links() }}
        </div>
        <form method="POST" action="{{ route('payroll.store') }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2>Run payroll</h2>
            <div><label class="label">Month</label><input type="month" name="period" class="input" value="{{ old('period', $next->format('Y-m')) }}" required></div>
            <div><label class="label">Pay date</label><input type="date" name="pay_date" class="input" value="{{ old('pay_date', $next->copy()->endOfMonth()->toDateString()) }}" required></div>
            <p class="help">Calculates every employee employed in that month from their salary, recurring items and unpaid leave. You can review and adjust before finalizing.</p>
            <button class="btn-primary">Calculate</button>
        </form>
    </div>
</x-app-layout>
