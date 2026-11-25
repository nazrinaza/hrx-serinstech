<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="header"><h1>{{ $title }}</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <table class="table">
                <thead><tr><th>Name</th><th class="w-40"></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr x-data="{ edit: false }">
                        <td>
                            <span x-show="!edit">{{ $row->name }}</span>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('setup.update', [$kind, $row->id]) }}" class="flex gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $row->name }}" class="input py-1" required>
                                <button class="btn-primary btn-sm">Save</button>
                            </form>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="btn-secondary btn-sm" @click="edit = !edit">Edit</button>
                            <form method="POST" action="{{ route('setup.destroy', [$kind, $row->id]) }}" class="inline" onsubmit="return confirm('Delete {{ $row->name }}?')">
                                @csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-ink/60">Nothing here yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('setup.store', $kind) }}" class="card card-body space-y-3 self-start">
            @csrf
            <h2>Add</h2>
            <input name="name" class="input" placeholder="Name" required>
            <button class="btn-primary">Add</button>
        </form>
    </div>
</x-app-layout>
