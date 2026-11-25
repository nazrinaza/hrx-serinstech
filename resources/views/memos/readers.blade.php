<x-app-layout>
    <x-slot name="title">Who read · {{ $memo->title }}</x-slot>
    <x-slot name="header">
        <div><a href="{{ route('memos.index') }}" class="text-sm underline text-ink/60">← Manage memos</a><h1>{{ $memo->title }}</h1></div>
    </x-slot>
    @php
        $total = $audience->count();
        $readCount = $audience->filter(fn ($r) => $r['read'])->count();
        $ackCount = $audience->filter(fn ($r) => $r['read']?->acknowledged_at)->count();
    @endphp
    <p class="mb-4 text-sm">{{ $readCount }} of {{ $total }} opened it{{ $memo->requires_ack ? " · {$ackCount} acknowledged" : '' }}.</p>
    <div class="card overflow-x-auto max-w-3xl">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Opened</th>@if ($memo->requires_ack)<th>Acknowledged</th>@endif</tr></thead>
            <tbody>
            @foreach ($audience as $r)
                <tr>
                    <td class="font-medium">{{ $r['user']->name }}</td>
                    <td>{{ $r['user']->email }}</td>
                    <td>{{ $r['read']?->created_at->format('d M Y H:i') ?? '—' }}</td>
                    @if ($memo->requires_ack)<td>{!! $r['read']?->acknowledged_at ? e($r['read']->acknowledged_at->format('d M Y H:i')) : '<span class="badge-pending">Not yet</span>' !!}</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="help mt-2">Lists everyone with a login in the memo's audience.</p>
</x-app-layout>
