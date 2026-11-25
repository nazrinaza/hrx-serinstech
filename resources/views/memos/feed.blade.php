<x-app-layout>
    <x-slot name="title">Company memos</x-slot>
    <x-slot name="header">
        <h1>Company memos</h1>
        @if (in_array($currentRole, \App\Models\User::HR_ROLES, true))
            <a href="{{ route('memos.index') }}" class="btn-secondary">Manage memos</a>
        @endif
    </x-slot>
    <div class="max-w-3xl space-y-4">
        @forelse ($memos as $memo)
            @include('memos._card', ['memo' => $memo, 'read' => $reads->get($memo->id), 'compact' => true])
        @empty
            <div class="card card-body text-ink/60">No memos at the moment.</div>
        @endforelse
        {{ $memos->links() }}
    </div>
</x-app-layout>
