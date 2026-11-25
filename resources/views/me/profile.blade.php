<x-app-layout>
    <x-slot name="title">My profile</x-slot>
    <x-slot name="header"><h1>My profile</h1></x-slot>
    @if ($employee)
        @include('employees._profile', ['employee' => $employee])
        <p class="help mt-4">Something wrong? Contact HR to update your details.</p>
    @else
        @include('me._nolink')
    @endif
</x-app-layout>
