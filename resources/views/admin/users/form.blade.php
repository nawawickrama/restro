@php($editing = $user->exists)

<x-layouts.app :title="$editing ? 'Edit user' : 'New user'">
    <x-slot:header>{{ $editing ? 'Edit user' : 'New user' }}</x-slot:header>

    <x-slot:actions>
        <x-btn :href="route('users.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
              method="POST"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Name" name="name" required class="xl:col-span-3">
                    <x-input name="name" :value="old('name', $user->name)" required autofocus/>
                </x-field>

                <x-field label="Email" name="email" required class="xl:col-span-3">
                    <x-input name="email" type="email" :value="old('email', $user->email)" autocomplete="off" required/>
                </x-field>

                <x-field label="Role" name="role" required
                         hint="Permissions come from the role, so a role change takes effect immediately."
                         class="sm:col-span-2 xl:col-span-2">
                    <x-select name="role" required>
                        <option value="">Choose a role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('role', $currentRole) === $role->name)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                {{-- The two password fields belong next to each other: they are
                     compared by eye as well as by the validator. --}}
                <x-field label="Password"
                         name="password"
                         :required="! $editing"
                         :hint="$editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.'"
                         class="xl:col-span-2">
                    <x-input name="password" type="password" autocomplete="new-password" :required="! $editing"/>
                </x-field>

                <x-field label="Confirm password" name="password_confirmation" class="xl:col-span-2">
                    <x-input name="password_confirmation" type="password" autocomplete="new-password" :required="! $editing"/>
                </x-field>
            </div>

            <x-toggle name="is_active"
                      :checked="$user->is_active ?? true"
                      label="Can sign in"
                      hint="Deactivating ends any session this user has open."/>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Create user' }}</x-btn>
                <x-btn :href="route('users.index')" variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
