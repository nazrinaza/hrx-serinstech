<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo-light.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
<div class="min-h-screen grid lg:grid-cols-2">
    <div class="hidden lg:flex flex-col justify-between bg-ink p-12 text-white">
        <img src="{{ asset('images/logo-dark.png') }}" alt="Serinstech" class="h-12 w-auto self-start">
        <div>
            <p class="text-3xl font-bold leading-tight">People &amp; payroll,<br><span class="text-ember">in one place.</span></p>
            <p class="mt-4 text-white/70 max-w-sm">Employees, leave and payroll for every Serinstech company.</p>
        </div>
        <p class="text-xs text-white/50">&copy; {{ date('Y') }} Serinstech</p>
    </div>
    <div class="flex flex-col items-center justify-center px-6 py-12 bg-mist">
        <img src="{{ asset('images/logo-light.png') }}" alt="Serinstech" class="h-14 mb-8 lg:hidden">
        <div class="w-full max-w-sm card card-body">
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
