<x-app-layout>
    <x-slot name="title">All companies</x-slot>
    <x-slot name="header">
        <h1>All companies</h1>
        <a href="{{ route('companies.create') }}" class="btn-primary">+ New company</a>
    </x-slot>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Registration no.</th><th class="num">Employees</th><th class="num">Users</th><th></th></tr></thead>
            <tbody>
            @foreach ($companies as $c)
                <tr>
                    <td class="font-medium">{{ $c->name }}</td>
                    <td>{{ $c->registration_no ?: '—' }}</td>
                    <td class="num">{{ $c->employees_count }}</td>
                    <td class="num">{{ $c->users_count }}</td>
                    <td class="text-right">
                        <form method="POST" action="{{ route('company.switch') }}">@csrf
                            <input type="hidden" name="company_id" value="{{ $c->id }}">
                            <button class="btn-secondary btn-sm">Open</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
