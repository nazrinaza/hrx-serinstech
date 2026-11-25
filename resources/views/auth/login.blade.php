<x-guest-layout>
    <h1 class="mb-1">Sign in</h1>
    <p class="mb-6 text-sm text-ink/60">Use the email your HR team registered for you.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" class="input" type="password" name="password" required autocomplete="current-password">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" class="rounded border-slate text-ember focus:ring-ember"> Remember me
            </label>
            <a class="text-sm underline text-ink/70 hover:text-ink" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button class="btn-primary w-full justify-center">Sign in</button>
    </form>
</x-guest-layout>
