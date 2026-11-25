<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="header"><h1>{{ $title }}</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <table class="table">
                <thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="{{ $row->date->isPast() ? 'text-ink/50' : '' }}">
                        <td class="whitespace-nowrap">{{ $row->date->format('d M Y') }}</td>
                        <td>{{ $row->date->format('D') }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('setup.destroy', [$kind, $row->id]) }}" onsubmit="return confirm('Delete this holiday?')">
                                @csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-ink/60">No holidays set. Add your state's public holidays so leave days are counted correctly.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('setup.store', $kind) }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2>Add holiday</h2>
            <div><label class="label">Date</label><input type="date" name="date" class="input" required></div>
            <div><label class="label">Name</label><input name="name" class="input" placeholder="e.g. Hari Raya Aidilfitri" required></div>
            <button class="btn-primary">Add</button>
        </form>
    </div>
</x-app-layout>
