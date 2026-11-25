<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/logo-light.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" x-data="{ nav: false }">
<div class="min-h-screen lg:flex">
    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 w-64 transform bg-ink transition lg:static lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-16 items-center px-5 border-b border-ink-700">
            <a href="{{ route('dashboard') }}"><img src="{{ asset('images/logo-dark.png') }}" alt="Serinstech" class="h-9"></a>
            <span class="ml-2 rounded bg-ember px-1.5 text-[10px] font-bold text-ink">HR</span>
        </div>
        @include('layouts.navigation')
    </aside>
    <div class="fixed inset-0 z-20 bg-ink/40 lg:hidden" x-show="nav" x-cloak @click="nav = false"></div>

    <div class="flex-1 min-w-0">
        {{-- Top bar --}}
        <header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-slate/60 bg-white px-4 lg:px-8">
            <button class="lg:hidden text-ink" @click="nav = true" aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            @isset($currentCompany)
                <form method="POST" action="{{ route('company.switch') }}" class="flex items-center gap-2">
                    @csrf
                    <label for="company_id" class="hidden sm:block text-xs font-semibold uppercase text-ink/60">Company</label>
                    <select id="company_id" name="company_id" class="input py-1.5 w-auto min-w-48" onchange="this.form.submit()">
                        @foreach ($myCompanies as $c)
                            <option value="{{ $c->id }}" @selected($c->id === $currentCompany->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </form>
                <span class="hidden sm:inline badge bg-mist text-ink">{{ ucfirst($currentRole) }}</span>
            @endisset

            <div class="ml-auto" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2 text-sm font-medium">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ember text-ink font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-4 mt-2 w-48 card py-1 text-sm">
                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-mist">Account &amp; password</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="w-full text-left px-4 py-2 hover:bg-mist">Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="px-4 py-6 lg:px-8">
            @isset($header)
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">{{ $header }}</div>
            @endisset

            @if (session('status'))
                <div class="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
