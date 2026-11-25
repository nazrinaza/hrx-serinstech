<x-app-layout>
    <x-slot name="title">Employees</x-slot>
    <x-slot name="header">
        <h1>Employees</h1>
        <div class="flex gap-2">
            <a href="{{ route('employees.import') }}" class="btn-secondary">Import CSV</a>
            <a href="{{ route('employees.create') }}" class="btn-primary">+ Add employee</a>
        </div>
    </x-slot>

    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" class="input w-64" placeholder="Search name, no. or IC">
        <select name="department_id" class="input w-auto">
            <option value="">All departments</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <select name="status" class="input w-auto">
            @foreach (['active' => 'Active', 'resigned' => 'Left', 'all' => 'All'] as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach
        </select>
        <button class="btn-dark">Filter</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>No.</th><th>Name</th><th>Department</th><th>Position</th><th>Manager</th><th>Joined</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($employees as $e)
                <tr class="cursor-pointer" onclick="location='{{ route('employees.show', $e) }}'">
                    <td class="tabular-nums">{{ $e->employee_no }}</td>
                    <td class="font-medium"><a href="{{ route('employees.show', $e) }}">{{ $e->full_name }}</a></td>
                    <td>{{ $e->department?->name ?? '—' }}</td>
                    <td>{{ $e->position?->name ?? '—' }}</td>
                    <td>{{ $e->manager?->full_name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $e->join_date->format('d M Y') }}</td>
                    <td>@if ($e->isActive())<span class="badge-active">Active</span>@else<span class="badge-resigned">Left</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-ink/60">No employees found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>
</x-app-layout>
