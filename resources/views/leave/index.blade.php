<x-app-layout>
    <x-slot name="title">Leave requests</x-slot>
    <x-slot name="header"><h1>Leave requests</h1></x-slot>
    <div class="grid gap-6 xl:grid-cols-4">
        <div class="xl:col-span-3 space-y-4">
            <form class="flex flex-wrap gap-2">
                <select name="status" class="input w-auto">@foreach (['pending', 'approved', 'rejected', 'cancelled', 'all'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                <select name="employee_id" class="input w-auto"><option value="">All employees</option>@foreach ($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->full_name }}</option>@endforeach</select>
                <input type="date" name="from" value="{{ request('from') }}" class="input w-auto" title="From">
                <input type="date" name="to" value="{{ request('to') }}" class="input w-auto" title="To">
                <button class="btn-dark">Filter</button>
            </form>
            @include('leave._history', ['history' => $requests, 'showEmployee' => true, 'cancelRoute' => 'leave.cancel'])
            {{ $requests->links() }}
            @if ($status === 'pending' && $requests->isNotEmpty())<p class="text-sm"><a class="underline" href="{{ route('approvals.index') }}">Approve or reject pending requests →</a></p>@endif
        </div>
        <div>@include('leave._form', ['action' => route('leave.store'), 'types' => $types, 'employees' => $employees])</div>
    </div>
</x-app-layout>
