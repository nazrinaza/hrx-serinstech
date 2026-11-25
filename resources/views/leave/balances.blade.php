<x-app-layout>
    <x-slot name="title">Leave balances</x-slot>
    <x-slot name="header">
        <h1>Leave balances {{ $year }}</h1>
        <form class="flex gap-2"><input type="number" name="year" value="{{ $year }}" class="input w-28"><button class="btn-dark">Show</button></form>
    </x-slot>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Employee</th>@foreach ($types as $t)<th class="num" title="{{ $t->name }}">{{ $t->code }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><a class="font-medium" href="{{ route('employees.show', [$r['employee'], 'tab' => 'leave', 'year' => $year]) }}">{{ $r['employee']->full_name }}</a></td>
                    @foreach ($types as $t)
                        @php $b = $r['balances'][$t->id]; @endphp
                        <td class="num" title="{{ $b ? "Entitled {$b['entitled']} · taken {$b['taken']} · pending {$b['pending']}" : '' }}">
                            {!! $b ? '<span class="font-semibold">'.e($b['balance']).'</span><span class="text-ink/50"> / '.e($b['entitled']).'</span>' : '<span class="text-ink/30">—</span>' !!}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="help mt-2">Balance / total entitlement. Hover a cell for details. {{ $types->map(fn ($t) => $t->code.' = '.$t->name)->implode(' · ') }}</p>
</x-app-layout>
