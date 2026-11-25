<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <div>
            <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}</h1>
            <p class="text-sm text-ink/60">{{ $currentCompany->name }} · {{ now()->format('l, j F Y') }}</p>
        </div>
    </x-slot>

    @if ($myPending > 0)
        <a href="{{ route('approvals.index') }}" class="mb-6 flex items-center justify-between rounded-lg border border-ember bg-ember-50 px-5 py-4">
            <span class="font-semibold">You have {{ $myPending }} leave request{{ $myPending > 1 ? 's' : '' }} waiting for your approval.</span>
            <span class="btn-primary btn-sm">Review</span>
        </a>
    @endif

    @if ($memosToAck > 0)
        <a href="{{ route('memos.feed') }}" class="mb-6 flex items-center justify-between rounded-lg border border-ember bg-ember-50 px-5 py-4">
            <span class="font-semibold">{{ $memosToAck }} memo{{ $memosToAck > 1 ? 's need' : ' needs' }} your acknowledgement.</span>
            <span class="btn-primary btn-sm">Read</span>
        </a>
    @endif

    <section class="mb-6">
        <div class="mb-3 flex items-center justify-between">
            <h2>Company memos</h2>
            <div class="flex gap-2">
                @if ($isHr)<a href="{{ route('memos.create') }}" class="btn-primary btn-sm">+ New memo</a>@endif
                <a href="{{ route('memos.feed') }}" class="btn-secondary btn-sm">View all</a>
            </div>
        </div>
        @if ($memos->isEmpty())
            <div class="card card-body text-sm text-ink/60">No memos right now.@if ($isHr) Post one with “New memo”.@endif</div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($memos as $memo)
                    @include('memos._card', ['memo' => $memo, 'read' => $memoReads->get($memo->id), 'compact' => true])
                @endforeach
            </div>
        @endif
    </section>

    @if ($stats)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            @foreach ([
                ['Active employees', $stats['headcount'], route('employees.index')],
                ['Joined this month', $stats['joiners'], route('employees.index')],
                ['On leave today', $stats['onLeaveToday'], route('leave.index', ['status' => 'approved', 'from' => today()->toDateString(), 'to' => today()->toDateString()])],
                ['Pending leave requests', $stats['pendingLeave'], route('leave.index')],
            ] as [$label, $value, $href])
                <a href="{{ $href }}" class="card card-body hover:border-ember">
                    <div class="text-xs font-semibold uppercase tracking-wide text-ink/60">{{ $label }}</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums">{{ $value }}</div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-body border-b border-slate/50"><h2>On leave (next 7 days)</h2></div>
            <table class="table">
                <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th class="num">Days</th></tr></thead>
                <tbody>
                @forelse ($onLeave as $l)
                    <tr>
                        <td>{{ $l->employee->full_name }}</td>
                        <td>{{ $l->leaveType->name }}</td>
                        <td>{{ $l->start_date->format('d M') }}{{ $l->end_date->ne($l->start_date) ? ' – '.$l->end_date->format('d M') : '' }}</td>
                        <td class="num">{{ $l->days + 0 }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-ink/60">Nobody is on leave in the next 7 days.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-6">
            @if ($stats && $stats['lastRun'])
                <div class="card card-body">
                    <h2>Latest payroll</h2>
                    <p class="mt-1 text-sm text-ink/60">{{ $stats['lastRun']->label() }} · <span class="badge-{{ $stats['lastRun']->status }}">{{ ucfirst($stats['lastRun']->status) }}</span></p>
                    <p class="mt-3 text-2xl font-bold tabular-nums">RM {{ number_format($stats['lastRun']->payslips_sum_net_pay ?? 0, 2) }}</p>
                    <p class="text-xs text-ink/60">net pay</p>
                    <a href="{{ route('payroll.show', $stats['lastRun']) }}" class="btn-secondary btn-sm mt-3">Open</a>
                </div>
            @endif

            <div class="card card-body">
                <h2>Birthdays in {{ now()->format('F') }}</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    @forelse ($birthdays as $e)
                        <li class="flex justify-between"><span>{{ $e->full_name }}</span><span class="text-ink/60">{{ $e->date_of_birth->format('j M') }}</span></li>
                    @empty
                        <li class="text-ink/60">No birthdays this month.</li>
                    @endforelse
                </ul>
            </div>

            @unless ($myEmployee)
                @if (! $isHr)
                    <div class="card card-body text-sm text-ink/70">Your login is not linked to an employee record yet. Ask HR to link it so you can apply for leave and see payslips.</div>
                @endif
            @endunless
        </div>
    </div>
</x-app-layout>
