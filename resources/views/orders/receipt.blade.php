@php
    $justCompleted = session()->has('status');
    $payment = $order->payments->last();

    // Only a sale that has just gone through belongs on the customer's screen.
    // Reprinting an old receipt from history must not throw a "Thank you" up
    // in front of whoever is standing at the counter now.
    $displayState = $justCompleted ? [
        'screen' => 'done',
        'completed' => [
            'paid' => money($order->total),
            'method' => $payment?->method->label(),
            'change' => money($payment?->change_amount ?? 0),
            'change_due' => (float) ($payment?->change_amount ?? 0) > 0,
            // Only a takeaway customer waits to be called.
            'collect' => $order->type->usesTable() ? null : $order->order_number,
        ],
    ] : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('layouts.partials.head', ['title' => 'Receipt '.$order->order_number])
</head>
<body class="min-h-full bg-slate-200 py-6 dark:bg-slate-950">

{{-- Handed to the customer's screen on load. Kept as a data island rather than
     inline script so it stays readable in the page — useful when setting a
     terminal up — and carries no executable code. --}}
@if ($displayState)
    <script type="application/json" id="customer-display-state">
        {!! json_encode($displayState, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif

{{-- Screen-only controls, kept on one horizontal row at every width so the
     receipt below never gets pushed down the page. Everything below prints. --}}
<div class="no-print mx-auto mb-6 flex max-w-md items-stretch gap-2 px-4">
    <x-btn :href="route('pos.home')" variant="secondary" size="md" class="flex-1 whitespace-nowrap px-3">
        <x-icon name="arrow-left" class="size-5 shrink-0"/>
        <span class="truncate">New order</span>
    </x-btn>

    @can(\App\Support\Permissions::VIEW_ORDERS)
        <x-btn :href="route('orders.show', $order)" variant="secondary" size="md"
               class="flex-1 whitespace-nowrap px-3">
            <x-icon name="receipt" class="size-5 shrink-0"/>
            <span class="truncate">Details</span>
        </x-btn>
    @endcan

    <x-btn type="button" size="md" class="flex-1 whitespace-nowrap px-3" onclick="window.print()">
        <x-icon name="printer" class="size-5 shrink-0"/>
        <span class="truncate">Print</span>
    </x-btn>
</div>

@if (session('status'))
    <div class="no-print mx-auto mb-6 max-w-md px-4">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-semibold
                    text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    </div>
@endif

{{-- The receipt itself: 80mm thermal width, monospaced figures, no colour.
     Set at the size of a thermal head's own font and spaced tight, because a
     till roll is paid for by the length and every slip is printed twice. --}}
<div class="print-sheet mx-auto max-w-md bg-white p-6 text-xs leading-snug text-slate-900 shadow-lg print:shadow-none">
    {{-- No logo: on a till roll it took more paper than anything else on the
         slip, and the name below says the same thing in one line. --}}
    <header class="text-center">
        <h1 class="text-base font-bold uppercase leading-tight">{{ $settings->restaurantName() }}</h1>

        @php
            $contact = array_filter([
                $settings->get('restaurant_address'),
                $settings->get('restaurant_phone') ? 'Tel: '.$settings->get('restaurant_phone') : null,
            ]);
        @endphp

        @if ($contact)
            <p>{{ implode(' · ', $contact) }}</p>
        @endif
    </header>

    <div class="my-1.5 border-t border-dashed border-slate-400"></div>

    {{-- Two facts to a line rather than one. Each wraps onto its own line if a
         long name will not fit beside the other. --}}
    <div>
        <div class="flex flex-wrap justify-between gap-x-2">
            <span class="font-mono font-semibold">{{ $order->order_number }}</span>
            <span>{{ ($order->completed_at ?? $order->created_at)->format('d M Y, g:i A') }}</span>
        </div>
        <div class="flex flex-wrap justify-between gap-x-2">
            <span>{{ $order->type->label() }}@if ($order->table) · {{ $order->table->name }}@endif</span>
            <span>Served by {{ $order->user->name }}</span>
        </div>

        {{-- Either detail can stand alone: a caller may give only a number, a
             walk-in only a name. --}}
        @if ($order->customer_name || $order->customer_phone)
            <div class="flex flex-wrap justify-between gap-x-2">
                <span>{{ $order->customer_name }}</span>
                <span class="font-mono">{{ $order->customer_phone }}</span>
            </div>
        @endif
    </div>

    <div class="my-1.5 border-t border-dashed border-slate-400"></div>

    <table class="w-full">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="font-semibold">Item</th>
                <th class="px-1 text-center font-semibold">Qty</th>
                <th class="text-right font-semibold">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr class="align-top">
                    <td class="pr-2">
                        {{ $item->name }}
                        {{-- With one of something the amount is the unit price,
                             so a second line would only repeat it. --}}
                        @if ($item->quantity > 1)
                            <span class="block text-slate-600">@ {{ money($item->unit_price) }}</span>
                        @endif
                        @if ($item->note)
                            <span class="block italic text-slate-600">{{ $item->note }}</span>
                        @endif
                    </td>
                    <td class="px-1 text-center font-mono">{{ $item->quantity }}</td>
                    <td class="text-right font-mono">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="my-1.5 border-t border-dashed border-slate-400"></div>

    <dl>
        {{-- With nothing taken off or added, the subtotal is the total and one
             line says it. --}}
        @if ((float) $order->discount_amount > 0 || (float) $order->tax_amount > 0)
            <div class="flex justify-between">
                <dt>Subtotal</dt>
                <dd class="font-mono">{{ money($order->subtotal) }}</dd>
            </div>
        @endif

        @if ((float) $order->discount_amount > 0)
            <div class="flex justify-between">
                <dt>Discount</dt>
                <dd class="font-mono">- {{ money($order->discount_amount) }}</dd>
            </div>
        @endif

        @if ((float) $order->tax_amount > 0)
            <div class="flex justify-between">
                <dt>Tax</dt>
                <dd class="font-mono">{{ money($order->tax_amount) }}</dd>
            </div>
        @endif

        <div class="flex justify-between text-sm font-bold">
            <dt>Total</dt>
            <dd class="font-mono">{{ money($order->total) }}</dd>
        </div>
    </dl>

    @forelse ($order->payments as $payment)
        {{-- What was handed over sits on the method's own line: "Paid by" and
             "Cash received" were two lines saying one thing. --}}
        <dl class="mt-1">
            <div class="flex justify-between">
                <dt>Paid by {{ $payment->method->label() }}</dt>
                <dd class="font-mono">{{ money($payment->tendered ?? $payment->amount) }}</dd>
            </div>

            @if ((float) $payment->change_amount > 0)
                <div class="flex justify-between font-semibold">
                    <dt>Change</dt>
                    <dd class="font-mono">{{ money($payment->change_amount) }}</dd>
                </div>
            @endif

            @if ($payment->reference)
                <div class="flex justify-between">
                    <dt>Reference</dt>
                    <dd class="font-mono">{{ $payment->reference }}</dd>
                </div>
            @endif
        </dl>
    @empty
        <p class="mt-1 text-center font-bold uppercase">Unpaid</p>
    @endforelse

    @if ($order->status === App\Enums\OrderStatus::Cancelled)
        <p class="mt-1 text-center font-bold uppercase">*** Cancelled ***</p>
    @endif

    @php
        // Straight off checkout the print time is the order time again; it only
        // says something new when a bill is printed later.
        $printedAt = $justCompleted ? null : now()->format('d M Y, g:i A');
    @endphp

    @if ($settings->get('receipt_footer') || $printedAt || $settings->get('software_credit'))
        <div class="my-1.5 border-t border-dashed border-slate-400"></div>

        <footer class="text-center">
            @if ($settings->get('receipt_footer'))
                <p>{{ $settings->get('receipt_footer') }}</p>
            @endif

            @if ($printedAt)
                <p class="text-slate-600">Printed {{ $printedAt }}</p>
            @endif

            @if ($settings->get('software_credit'))
                <p class="text-[10px] text-slate-600">{{ $settings->get('software_credit') }}</p>
            @endif
        </footer>
    @endif
</div>

{{-- The kitchen's copy, on the same print job as the customer's bill so one
     press of Print produces both slips. `break-before: page` is what makes the
     printer cut between them; without it the two run together on one length of
     paper. Deliberately plain — what to cook, how many, and when it was rung
     up. No logo, no address, no totals, nothing to read past. --}}
<div class="print-sheet kitchen-ticket mx-auto mt-6 max-w-md bg-white p-6 text-slate-900 shadow-lg print:shadow-none">
    {{-- The kitchen needs to match the food to an order, so the number and the
         place it is going stay on. Everything else about the sale does not. --}}
    <div class="flex flex-wrap items-baseline justify-between gap-x-2 text-sm font-bold">
        <h2 class="uppercase tracking-widest">Kitchen</h2>
        <p class="font-mono">{{ $order->order_number }}</p>
    </div>

    <div class="flex flex-wrap justify-between gap-x-2 text-xs leading-snug">
        <p class="font-semibold">
            {{ $order->type->label() }}@if ($order->table) · {{ $order->table->name }}@endif
        </p>
        <p>{{ ($order->completed_at ?? $order->created_at)->format('d M Y, g:i A') }}</p>
    </div>

    <div class="my-1.5 border-t border-dashed border-slate-400"></div>

    <table class="w-full text-sm leading-snug">
        <tbody>
            @foreach ($order->items as $item)
                <tr class="align-top">
                    <td class="py-0.5 pr-2 text-right font-mono font-bold">{{ $item->quantity }}</td>
                    <td class="py-0.5 pr-2 font-semibold">
                        {{ $item->name }}
                        {{-- A note is an instruction to the cook, so it belongs
                             here more than anywhere else on the roll. --}}
                        @if ($item->note)
                            <span class="block text-xs font-normal italic">{{ $item->note }}</span>
                        @endif
                    </td>
                    <td class="py-0.5 text-right font-mono">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($order->status === App\Enums\OrderStatus::Cancelled)
        <p class="mt-1.5 text-center text-sm font-bold uppercase">*** Cancelled — do not cook ***</p>
    @endif
</div>

@if ($justCompleted)
    {{-- Straight off the checkout screen: send it to the printer immediately,
         the way a counter terminal is expected to behave. --}}
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
</body>
</html>
