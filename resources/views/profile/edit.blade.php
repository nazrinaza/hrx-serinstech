<x-app-layout>
    <x-slot name="title">Account</x-slot>
    <x-slot name="header"><h1>Account &amp; password</h1></x-slot>
    <div class="grid gap-6 lg:grid-cols-2 max-w-5xl">
        <div class="card card-body">@include('profile.partials.update-profile-information-form')</div>
        <div class="card card-body">@include('profile.partials.update-password-form')</div>
    </div>
</x-app-layout>
