<x-app-layout>
    <x-slot name="title">My leave</x-slot>
    <x-slot name="header"><h1>My leave</h1></x-slot>
    @if (! $employee)
        @include('me._nolink')
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            @foreach ($balances as $b)
                <div class="card card-body">
                    <div class="text-xs font-semibold uppercase tracking-wide text-ink/60">{{ $b['type']->name }}</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums">{{ $b['balance'] }} <span class="text-base font-normal text-ink/50">/ {{ $b['entitled'] }}</span></div>
                    <div class="text-xs text-ink/60">taken {{ $b['taken'] }} · pending {{ $b['pending'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="grid gap-6 xl:grid-cols-4">
            <div class="xl:col-span-3">
                <h2 class="mb-3">My requests</h2>
                @include('leave._history', ['history' => $history, 'showEmployee' => false, 'cancelRoute' => 'me.leave.cancel'])
            </div>
            <div class="space-y-2">
                @include('leave._form', ['action' => route('me.leave.store'), 'types' => $types])
                <p class="help">Goes to <strong>{{ $approver?->name ?? 'HR' }}</strong> for approval. You'll get an email when it's decided.</p>
            </div>
        </div>
    @endif
</x-app-layout>
