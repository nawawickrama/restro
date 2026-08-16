<x-layouts.app title="Settings">
    <x-slot:header>Settings</x-slot:header>
    <x-slot:subheader>Restaurant details, currency and what prints on the receipt</x-slot:subheader>

    <x-card>
        <form action="{{ route('settings.update') }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            {{-- Grouped the way someone thinks about them: who the restaurant
                 is, then how money is shown, then what prints. --}}
            <fieldset class="space-y-5">
                <legend class="text-sm font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                    Restaurant
                </legend>

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                    <x-field label="Restaurant name" name="restaurant_name" required class="xl:col-span-2">
                        <x-input name="restaurant_name" :value="old('restaurant_name', $values['restaurant_name'])" required/>
                    </x-field>

                    <x-field label="Phone number" name="restaurant_phone" class="xl:col-span-2">
                        <x-input name="restaurant_phone" type="tel" :value="old('restaurant_phone', $values['restaurant_phone'])"/>
                    </x-field>

                    <x-field label="Address" name="restaurant_address" class="sm:col-span-2 xl:col-span-2">
                        <x-input name="restaurant_address" :value="old('restaurant_address', $values['restaurant_address'])"/>
                    </x-field>
                </div>
            </fieldset>

            <fieldset class="space-y-5 border-t border-slate-100 pt-6 dark:border-slate-800">
                <legend class="text-sm font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                    Money
                </legend>

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                    <x-field label="Currency symbol" name="currency_symbol" required hint="Shown before every amount, e.g. Rs."
                             class="xl:col-span-2">
                        <x-input name="currency_symbol" :value="old('currency_symbol', $values['currency_symbol'])" required/>
                    </x-field>

                    <x-field label="Tax percentage"
                             name="tax_percentage"
                             required
                             hint="Applied after any discount. Set to 0 to switch tax off entirely."
                             class="xl:col-span-4">
                        <x-input name="tax_percentage"
                                 type="number"
                                 step="0.01"
                                 min="0"
                                 max="100"
                                 inputmode="decimal"
                                 :value="old('tax_percentage', $values['tax_percentage'])"
                                 required/>
                    </x-field>
                </div>
            </fieldset>

            <fieldset class="space-y-5 border-t border-slate-100 pt-6 dark:border-slate-800">
                <legend class="text-sm font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                    Receipt
                </legend>

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-6">
                    <x-field label="Receipt footer" name="receipt_footer"
                             hint="The thank-you line at the bottom of the receipt."
                             class="xl:col-span-3">
                        <x-input name="receipt_footer" :value="old('receipt_footer', $values['receipt_footer'])"/>
                    </x-field>

                    <x-field label="Software credit" name="software_credit"
                             hint="Printed in small type at the very bottom. Leave blank to omit it."
                             class="xl:col-span-3">
                        <x-input name="software_credit" :value="old('software_credit', $values['software_credit'] ?? '')"/>
                    </x-field>
                </div>
            </fieldset>

            <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                <x-btn size="lg" class="w-full sm:w-44">Save settings</x-btn>
            </div>
        </form>
    </x-card>
</x-layouts.app>
