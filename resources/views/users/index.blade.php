<x-app-layout>
    <x-slot name="title">Users & roles</x-slot>
    <x-slot name="header"><h1>Users &amp; roles</h1></x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td class="font-medium">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>
                            <form method="POST" action="{{ route('users.update', $u) }}" class="flex gap-2">
                                @csrf @method('PUT')
                                <select name="role" class="input py-1 w-auto" onchange="this.form.submit()" @disabled($u->pivot->role === 'owner' && ! in_array('owner', $roles))>
                                    @foreach (array_unique(array_merge($roles, [$u->pivot->role])) as $r)
                                        <option value="{{ $r }}" @selected($u->pivot->role === $r)>{{ ucfirst($r) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="text-right">
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Remove {{ $u->name }}\'s access to this company?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger btn-sm">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('users.store') }}" class="card card-body space-y-4 self-start">
            @csrf
            <h2>Give someone access</h2>
            <div><label class="label">Name</label><input name="name" class="input" value="{{ old('name') }}" required></div>
            <div><label class="label">Email</label><input name="email" type="email" class="input" value="{{ old('email') }}" required></div>
            <div>
                <label class="label">Role</label>
                <select name="role" class="input">
                    @foreach ($roles as $r)<option value="{{ $r }}" @selected(old('role', 'employee') === $r)>{{ ucfirst($r) }}</option>@endforeach
                </select>
                <p class="help">Owner/Admin: everything · HR: employees, leave, payroll · Manager: approves their team's leave · Employee: self service only.</p>
            </div>
            <div>
                <label class="label">Link to employee (optional)</label>
                <select name="employee_id" class="input">
                    <option value="">—</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} ({{ $e->employee_no }})</option>@endforeach
                </select>
            </div>
            <p class="help">New users get an email with a link to set their own password.</p>
            <button class="btn-primary">Add user</button>
        </form>
    </div>
</x-app-layout>
