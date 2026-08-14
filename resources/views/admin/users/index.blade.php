<x-layouts.app title="Users">
    <x-slot:header>Users</x-slot:header>
    <x-slot:subheader>Staff accounts and what each of them is allowed to do</x-slot:subheader>

    <x-slot:actions>
        <x-btn :href="route('users.create')">
            <x-icon name="plus"/>
            New user
        </x-btn>
    </x-slot:actions>

    <div class="space-y-3">
        @foreach ($users as $user)
            <x-card class="flex flex-wrap items-center gap-4">
                <div class="min-w-48 flex-1">
                    <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $user->name }}</p>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                </div>

                @foreach ($user->roles as $role)
                    <x-badge classes="bg-brand-100 text-brand-800 dark:bg-brand-500/15 dark:text-brand-300">
                        {{ $role->name }}
                    </x-badge>
                @endforeach

                @if ($user->is_active)
                    <x-badge classes="bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                        Active
                    </x-badge>
                @else
                    <x-badge classes="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                        Deactivated
                    </x-badge>
                @endif

                <div class="ml-auto flex items-center gap-2">
                    <x-btn :href="route('users.edit', $user)" variant="secondary" size="sm">
                        <x-icon name="pencil" class="size-5"/>
                        Edit
                    </x-btn>

                    @unless ($user->is(auth()->user()))
                        <x-confirm-form :action="route('users.destroy', $user)"
                                        method="DELETE"
                                        title="Delete {{ $user->name }}?"
                                        message="Staff who have taken orders are deactivated instead, so their name stays on those orders."
                                        confirm="Delete user"
                                        size="sm">
                            <x-slot:trigger>
                                <x-icon name="trash" class="size-5"/>
                            </x-slot:trigger>
                        </x-confirm-form>
                    @endunless
                </div>
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
