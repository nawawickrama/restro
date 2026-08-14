<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head', ['title' => 'Sign in'])
</head>
<body class="flex min-h-full items-center justify-center p-4">
<div class="w-full max-w-md space-y-6">
    <div class="text-center">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $settings->restaurantName() }}</h1>
        <p class="mt-2 text-base text-slate-500 dark:text-slate-400">Sign in to start taking orders</p>
    </div>

    <x-card>
        <form action="{{ route('login') }}" method="POST" class="space-y-5">
            @csrf

            <x-field label="Email" name="email" required>
                <x-input name="email"
                         type="email"
                         :value="old('email')"
                         autocomplete="username"
                         inputmode="email"
                         required
                         autofocus/>
            </x-field>

            <x-field label="Password" name="password" required>
                <x-input name="password" type="password" autocomplete="current-password" required/>
            </x-field>

            <label class="flex items-center gap-3 text-base text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="remember" value="1" class="field-check">
                Keep me signed in on this terminal
            </label>

            <x-btn size="lg" class="w-full">Sign in</x-btn>
        </form>
    </x-card>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-base font-medium text-rose-800
                    dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300" role="alert">
            {{ $errors->first() }}
        </div>
    @endif
</div>
</body>
</html>
