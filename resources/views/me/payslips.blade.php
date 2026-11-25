<x-app-layout>
    <x-slot name="title">My payslips</x-slot>
    <x-slot name="header"><h1>My payslips</h1></x-slot>
    @if (! $employee)
        @include('me._nolink')
    @else
        <div class="card overflow-x-auto max-w-3xl">
            <table class="table">
                <thead><tr><th>Month</th><th>Pay date</th><th class="num">Gross (RM)</th><th class="num">Net (RM)</th><th></th></tr></thead>
                <tbody>
                @forelse ($payslips as $p)
                    <tr>
                        <td class="font-medium">{{ $p->run->label() }}</td>
                        <td>{{ $p->run->pay_date->format('d M Y') }}</td>
                        <td class="num">{{ number_format($p->gross_pay, 2) }}</td>
                        <td class="num font-semibold">{{ number_format($p->net_pay, 2) }}</td>
                        <td class="text-right"><a href="{{ route('me.payslips.pdf', $p) }}" class="btn-primary btn-sm">Download PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-ink/60">No payslips have been published to you yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
