{{-- Params: $history, $showEmployee (bool), $cancelRoute (route name or null) --}}
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr>
            @if ($showEmployee)<th>Employee</th>@endif
            <th>Type</th><th>Dates</th><th class="num">Days</th><th>Reason</th><th>Status</th><th>Approver</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($history as $l)
            <tr>
                @if ($showEmployee)<td class="font-medium">{{ $l->employee->full_name }}</td>@endif
                <td>{{ $l->leaveType->name }}</td>
                <td class="whitespace-nowrap">{{ $l->start_date->format('d M Y') }}{{ $l->end_date->ne($l->start_date) ? ' – '.$l->end_date->format('d M Y') : '' }}{{ $l->half_day ? ' ('.strtoupper($l->half_day).')' : '' }}</td>
                <td class="num">{{ $l->days + 0 }}</td>
                <td class="max-w-xs">{{ $l->reason }}
                    @if ($l->attachment_path)<a href="{{ route('leave.attachment', $l) }}" class="underline text-xs">attachment</a>@endif
                    @if ($l->decision_note)<div class="text-xs text-ink/60">Note: {{ $l->decision_note }}</div>@endif
                </td>
                <td><span class="badge-{{ $l->status }}">{{ ucfirst($l->status) }}</span></td>
                <td>{{ $l->approver?->name ?? '—' }}</td>
                <td class="text-right">
                    @if ($cancelRoute && in_array($l->status, ['pending', 'approved']))
                        <form method="POST" action="{{ route($cancelRoute, $l) }}" onsubmit="return confirm('Cancel this leave?')">@csrf<button class="btn-danger btn-sm">Cancel</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-ink/60">No leave requests.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
