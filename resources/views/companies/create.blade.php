<x-app-layout>
    <x-slot name="title">New company</x-slot>
    <x-slot name="header"><h1>New company</h1></x-slot>
    <form method="POST" action="{{ route('companies.store') }}" class="card card-body max-w-xl space-y-4">
        @csrf
        <div>
            <label class="label" for="name">Company name</label>
            <input id="name" name="name" class="input" value="{{ old('name') }}" required>
        </div>
        <div>
            <label class="label" for="registration_no">SSM registration no.</label>
            <input id="registration_no" name="registration_no" class="input" value="{{ old('registration_no') }}">
        </div>
        <p class="help">The company starts with Employment Act leave types and common allowances. You'll be its owner.</p>
        <button class="btn-primary">Create company</button>
    </form>
</x-app-layout>
