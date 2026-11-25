<x-app-layout>
    <x-slot name="title">Manage memos</x-slot>
    <x-slot name="header">
        <h1>Manage memos</h1>
        <a href="{{ route('memos.create') }}" class="btn-primary">+ New memo</a>
    </x-slot>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Title</th><th>Audience</th><th>Publish</th><th>Expires</th><th>Status</th><th class="num">Read</th><th class="num">Acknowledged</th><th></th></tr></thead>
            <tbody>
            @forelse ($memos as $m)
                @php $st = $m->status(); @endphp
                <tr>
                    <td class="font-medium">
                        @if ($m->is_pinned)<span title="Pinned">📌</span>@endif
                        <a href="{{ route('memos.read', $m) }}">{{ $m->title }}</a>
                        @if ($m->priority === 'important')<span class="badge bg-ember text-ink ml-1">Important</span>@endif
                    </td>
                    <td>{{ $m->isForEveryone() ? 'All staff' : collect($m->department_ids)->map(fn ($id) => $departments[$id] ?? '?')->implode(', ') }}</td>
                    <td class="whitespace-nowrap">{{ $m->publish_at->format('d M Y H:i') }}</td>
                    <td class="whitespace-nowrap">{{ $m->expires_at?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge {{ ['live' => 'bg-green-100 text-green-800', 'scheduled' => 'bg-amber-100 text-amber-800', 'expired' => 'bg-slate/50 text-ink'][$st] }}">{{ ucfirst($st) }}</span></td>
                    <td class="num">{{ $m->reads_count }}</td>
                    <td class="num">{{ $m->requires_ack ? $m->acknowledged_count : '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('memos.readers', $m) }}" class="btn-secondary btn-sm">Who read</a>
                        <a href="{{ route('memos.edit', $m) }}" class="btn-secondary btn-sm">Edit</a>
                        <form method="POST" action="{{ route('memos.destroy', $m) }}" class="inline" onsubmit="return confirm('Delete this memo?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-ink/60">No memos yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $memos->links() }}</div>
</x-app-layout>
