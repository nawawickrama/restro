@php($editing = $customer->exists)

<x-layouts.app :title="$editing ? 'Edit customer' : 'New customer'">
    <x-slot:header>{{ $editing ? 'Edit customer' : 'New customer' }}</x-slot:header>

    <x-slot:actions>
        <x-btn :href="$editing ? route('customers.show', $customer) : route('customers.index')" variant="secondary">
            <x-icon name="arrow-left"/>
            Back
        </x-btn>
    </x-slot:actions>

    <x-card>
        <form action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}"
              method="POST"
              class="space-y-6">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                <x-field label="Mobile number" name="phone_digits" required
                         hint="The number identifies the customer, so it has to be unique."
                         class="xl:col-span-2">
                    {{-- Named `phone` on the way in; the digits-only form is
                         derived server-side and is what the error attaches to. --}}
                    <x-input name="phone"
                             id="phone_digits"
                             type="tel"
                             inputmode="tel"
                             :value="old('phone', $customer->phone)"
                             autocomplete="off"
                             required
                             autofocus/>
                </x-field>

                <x-field label="Name" name="name" hint="Optional." class="xl:col-span-4">
                    <x-input name="name" :value="old('name', $customer->name)" autocomplete="off"/>
                </x-field>

                <x-field label="Note" name="note"
                         hint="Optional. Allergies, a usual order, anything worth remembering."
                         class="sm:col-span-2 xl:col-span-6">
                    <x-textarea name="note" rows="2">{{ old('note', $customer->note) }}</x-textarea>
                </x-field>
            </div>

            @if ($editing)
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Added {{ $customer->created_at->format('d M Y') }} ·
                    {{ $customer->source->label() }}. How a customer was first met is kept as a record
                    and cannot be changed.
                </p>
            @endif

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row-reverse sm:justify-start
                        dark:border-slate-800">
                <x-btn size="lg" class="sm:w-44">{{ $editing ? 'Save changes' : 'Add customer' }}</x-btn>
                <x-btn :href="$editing ? route('customers.show', $customer) : route('customers.index')"
                       variant="secondary" size="lg" class="sm:w-44">Cancel</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
