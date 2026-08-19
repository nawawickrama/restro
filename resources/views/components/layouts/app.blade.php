@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-full">
{{-- The back office: menu, tables, users, history and reports. The POS itself
     uses its own full-screen layout. --}}
<div x-data="{ nav: false }" class="min-h-screen lg:flex">
    <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/90
                   px-4 py-3 backdrop-blur lg:hidden dark:border-slate-800 dark:bg-slate-900/90">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-auto shrink-0">
            <span class="truncate text-lg font-bold text-slate-900 dark:text-white">
                {{ $settings->restaurantName() }}
            </span>
        </a>

        <x-btn type="button" variant="ghost" size="sm" x-on:click="nav = !nav" aria-label="Toggle navigation">
            <x-icon name="pos"/>
        </x-btn>
    </header>

    {{-- Collapsed behind a button on phones, always open from lg up: the
         `lg:flex!` wins over the inline display Alpine sets.

         From lg it stays put while the page scrolls — a long order history or
         a full menu list should never scroll the navigation off the screen.
         `self-start` stops the flex row stretching it to the page height,
         which is what sticky needs in order to have anywhere to stick. --}}
    <nav x-show="nav"
         x-cloak
         class="w-full shrink-0 border-b border-slate-200 bg-white p-4 lg:flex! lg:sticky lg:top-0 lg:h-dvh
                lg:w-72 lg:flex-col lg:self-start lg:overflow-y-auto lg:border-r lg:border-b-0
                dark:border-slate-800 dark:bg-slate-900">
        <div class="hidden lg:block">
            <a href="{{ route('dashboard') }}" class="block px-2 pt-2 pb-6 text-center">
                <img src="{{ asset('images/logo.png') }}"
                     alt="{{ $settings->restaurantName() }}"
                     class="mx-auto w-40 max-w-full">
                <span class="mt-3 block text-sm font-medium text-slate-500 dark:text-slate-400">
                    Point of sale
                </span>
            </a>
        </div>

        <div class="space-y-1 lg:flex-1">
            <x-nav-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-nav-link>

            @can(\App\Support\Permissions::VIEW_POS)
                <x-nav-link :href="route('pos.home')" icon="pos" :active="request()->routeIs('pos.*')">
                    POS
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::VIEW_ORDERS)
                <x-nav-link :href="route('orders.index')" icon="receipt" :active="request()->routeIs('orders.*')">
                    Orders
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::VIEW_REPORTS)
                <x-nav-link :href="route('reports.index')" icon="chart" :active="request()->routeIs('reports.*')">
                    Reports
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::MANAGE_MENU)
                <p class="px-4 pt-5 pb-2 text-xs font-bold tracking-wider text-slate-400 uppercase">Manage</p>

                <x-nav-link :href="route('menu-items.index')" icon="menu-book" :active="request()->routeIs('menu-items.*')">
                    Menu items
                </x-nav-link>
                <x-nav-link :href="route('categories.index')" icon="menu-book" :active="request()->routeIs('categories.*')">
                    Categories
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::MANAGE_TABLES)
                <x-nav-link :href="route('tables.index')" icon="table" :active="request()->routeIs('tables.*')">
                    Tables
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::MANAGE_CUSTOMERS)
                <x-nav-link :href="route('customers.index')" icon="users" :active="request()->routeIs('customers.*')">
                    Customers
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::MANAGE_USERS)
                <x-nav-link :href="route('users.index')" icon="users" :active="request()->routeIs('users.*')">
                    Users
                </x-nav-link>
            @endcan

            @can(\App\Support\Permissions::MANAGE_SETTINGS)
                <x-nav-link :href="route('settings.edit')" icon="settings" :active="request()->routeIs('settings.*')">
                    Settings
                </x-nav-link>
            @endcan
        </div>

        <div class="mt-6 shrink-0 border-t border-slate-200 pt-4 dark:border-slate-800">
            <div class="flex items-center justify-between gap-2 px-2">
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-slate-800 dark:text-slate-100">
                        {{ auth()->user()->name }}
                    </p>
                    <p class="truncate text-sm text-slate-500 dark:text-slate-400">
                        {{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role' }}
                    </p>
                </div>

                <button type="button"
                        x-data="themeToggle"
                        x-on:click="toggle()"
                        class="touch-target rounded-xl p-2 text-slate-500 hover:bg-slate-200/70 dark:text-slate-400 dark:hover:bg-slate-800"
                        aria-label="Toggle dark mode">
                    <x-icon name="sun" class="size-6" x-show="dark"/>
                    <x-icon name="moon" class="size-6" x-show="!dark"/>
                </button>
            </div>

            <form action="{{ route('logout') }}" method="POST" class="mt-3">
                @csrf
                <x-btn variant="ghost" size="sm" class="w-full justify-start">
                    <x-icon name="logout" class="size-5"/>
                    Sign out
                </x-btn>
            </form>
        </div>
    </nav>

    <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-6xl space-y-6">
            @isset($header)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $header }}</h1>
                        @isset($subheader)
                            <p class="mt-1 text-base text-slate-500 dark:text-slate-400">{{ $subheader }}</p>
                        @endisset
                    </div>

                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
                    @endisset
                </div>
            @endisset

            <x-flash/>

            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
