<x-app-layout>
    <x-slot name="title">{{ $memo->title }}</x-slot>
    <x-slot name="header"><a href="{{ route('memos.feed') }}" class="text-sm underline text-ink/60">← All memos</a></x-slot>
    <div class="max-w-3xl">@include('memos._card', ['memo' => $memo, 'read' => $read, 'compact' => false])</div>
</x-app-layout>
