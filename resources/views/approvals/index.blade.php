<x-app-layout>
    <x-slot name="title">Approvals</x-slot>
    <x-slot name="header"><h1>Leave approvals</h1></x-slot>
    @if ($isHr)<p class="mb-4 text-sm text-ink/70">As HR you can see and decide every pending request in this company.</p>@endif
    <div class="space-y-4">
        @forelse ($pending as $l)
            <div class="card card-body grid gap-4 lg:grid-cols-3" x-data="{ reject: false }">
                <div class="lg:col-span-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $l->employee->full_name }}</span>
                        <span class="badge bg-mist text-ink">{{ $l->leaveType->name }}</span>
                        @if ($l->from_self_service)<span class="text-xs text-ink/50">via self service</span>@endif
                    </div>
                    <p class="mt-1 text-sm">{{ $l->start_date->format('D, d M Y') }}{{ $l->end_date->ne($l->start_date) ? ' – '.$l->end_date->format('D, d M Y') : '' }}{{ $l->half_day ? ' ('.strtoupper($l->half_day).')' : '' }} · <strong>{{ $l->days + 0 }} day(s)</strong></p>
                    @if ($l->reason)<p class="mt-1 text-sm text-ink/70">“{{ $l->reason }}”</p>@endif
                    @if ($l->attachment_path)<a href="{{ route('leave.attachment', $l) }}" class="mt-1 inline-block text-sm underline">View attachment</a>@endif
                    <p class="mt-1 text-xs text-ink/50">Approver: {{ $l->approver?->name ?? 'HR' }} · requested {{ $l->created_at->diffForHumans() }}</p>
                </div>
                @if ($l->employee->user_id !== auth()->id())
                    <form method="POST" action="{{ route('approvals.decide', $l) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" rows="2" class="input" placeholder="Note (required to reject)" x-show="reject" x-cloak></textarea>
                        <div class="flex gap-2 justify-end">
                            <button name="decision" value="approve" class="btn-primary" x-show="!reject">Approve</button>
                            <button type="button" class="btn-danger" x-show="!reject" @click="reject = true">Reject…</button>
                            <button name="decision" value="reject" class="btn-danger" x-show="reject" x-cloak>Confirm reject</button>
                            <button type="button" class="btn-secondary" x-show="reject" x-cloak @click="reject = false">Back</button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-ink/60 text-right">Your own request — another approver must decide.</p>
                @endif
            </div>
        @empty
            <div class="card card-body text-ink/60">Nothing waiting for approval.</div>
        @endforelse
    </div>
</x-app-layout>
