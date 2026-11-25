<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="header"><h1>{{ $title }}</h1></x-slot>
    @php $flags = ['epf' => 'EPF', 'socso' => 'SOCSO', 'eis' => 'EIS', 'pcb' => 'PCB', 'hrdf' => 'HRDF']; @endphp
    <p class="mb-4 text-sm text-ink/70 max-w-3xl">Ticks show which contributions an item counts towards. For <strong>earnings</strong>, a tick adds it to that wage base. For <strong>deductions</strong>, a tick reduces that wage base (most deductions, like loan repayments, should have no ticks).</p>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Type</th>@foreach ($flags as $l)<th class="text-center">{{ $l }}</th>@endforeach<th></th></tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium">{{ $row->name }}
                            <form method="POST" action="{{ route('setup.update', [$kind, $row->id]) }}" id="pi{{ $row->id }}">@csrf @method('PUT')
                                <input type="hidden" name="name" value="{{ $row->name }}"><input type="hidden" name="type" value="{{ $row->type }}">
                            </form>
                        </td>
                        <td><span class="badge {{ $row->type === 'earning' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ ucfirst($row->type) }}</span></td>
                        @foreach ($flags as $f => $l)
                            <td class="text-center"><input form="pi{{ $row->id }}" type="checkbox" name="{{ $f }}_applicable" value="1" class="rounded border-slate text-ember focus:ring-ember" @checked($row->{$f.'_applicable'}) onchange="this.form.submit()"></td>
                        @endforeach
                        <td class="text-right">
                            <form method="POST" action="{{ route('setup.destroy', [$kind, $row->id]) }}" onsubmit="return confirm('Delete {{ $row->name }}?')">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('setup.store', $kind) }}" class="card card-body space-y-3 self-start" x-data="{ type: 'earning' }">
            @csrf
            <h2>Add item</h2>
            <div><label class="label">Name</label><input name="name" class="input" required></div>
            <div><label class="label">Type</label>
                <select name="type" class="input" x-model="type"><option value="earning">Earning</option><option value="deduction">Deduction</option></select></div>
            <div class="flex flex-wrap gap-3">
                @foreach ($flags as $f => $l)
                    <label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="{{ $f }}_applicable" value="1" class="rounded border-slate text-ember focus:ring-ember" :checked="type === 'earning'"> {{ $l }}</label>
                @endforeach
            </div>
            <button class="btn-primary">Add</button>
        </form>
    </div>
</x-app-layout>
