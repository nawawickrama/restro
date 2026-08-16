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

{{-- The receipt itself: 80mm thermal width, monospaced figures, no colour. --}}
<div class="print-sheet mx-auto max-w-md bg-white p-6 text-slate-900 shadow-lg print:shadow-none">
    <header class="text-center">
        <h1 class="text-xl font-bold uppercase">{{ $settings->restaurantName() }}</h1>

        @if ($settings->get('restaurant_address'))
            <p class="mt-1 text-sm">{{ $settings->get('restaurant_address') }}</p>
        @endif

        @if ($settings->get('restaurant_phone'))
            <p class="text-sm">Tel: {{ $settings->get('restaurant_phone') }}</p>
        @endif
    </header>

    <div class="my-4 border-t border-dashed border-slate-400"></div>

    <dl class="space-y-1 text-sm">
        <div class="flex justify-between">
            <dt class="font-semibold">Order</dt>
            <dd class="font-mono">{{ $order->order_number }}</dd>
        </div>
        <div class="flex justify-between">
            <dt class="font-semibold">Date</dt>
            <dd>{{ ($order->completed_at ?? $order->created_at)->format('d M Y, g:i A') }}</dd>
        </div>
        <div class="flex justify-between">
            <dt class="font-semibold">Type</dt>
            <dd>{{ $order->type->label() }}</dd>
        </div>

        @if ($order->table)
            <div class="flex justify-between">
                <dt class="font-semibold">Table</dt>
                <dd>{{ $order->table->name }}</dd>
            </div>
        @endif

        {{-- Either detail can stand alone: a caller may give only a number, a
             walk-in only a name. --}}
        @if ($order->customer_name)
            <div class="flex justify-between">
                <dt class="font-semibold">Customer</dt>
                <dd>{{ $order->customer_name }}</dd>
            </div>
        @endif

        @if ($order->customer_phone)
            <div class="flex justify-between">
                <dt class="font-semibold">Mobile</dt>
                <dd class="font-mono">{{ $order->customer_phone }}</dd>
            </div>
        @endif

        <div class="flex justify-between">
            <dt class="font-semibold">Served by</dt>
            <dd>{{ $order->user->name }}</dd>
        </div>
    </dl>

    <div class="my-4 border-t border-dashed border-slate-400"></div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="pb-1 font-semibold">Item</th>
                <th class="pb-1 text-center font-semibold">Qty</th>
                <th class="pb-1 text-right font-semibold">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr class="align-top">
                    <td class="py-1 pr-2">
                        {{ $item->name }}
                        <span class="block text-xs text-slate-600">@ {{ money($item->unit_price) }}</span>
                        @if ($item->note)
                            <span class="block text-xs italic text-slate-600">{{ $item->note }}</span>
                        @endif
                    </td>
                    <td class="py-1 text-center font-mono">{{ $item->quantity }}</td>
                    <td class="py-1 text-right font-mono">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="my-3 border-t border-dashed border-slate-400"></div>

    <dl class="space-y-1 text-sm">
        <div class="flex justify-between">
            <dt>Subtotal</dt>
            <dd class="font-mono">{{ money($order->subtotal) }}</dd>
        </div>

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

        <div class="flex justify-between border-t border-slate-400 pt-2 text-lg font-bold">
            <dt>Total</dt>
            <dd class="font-mono">{{ money($order->total) }}</dd>
        </div>
    </dl>

    @forelse ($order->payments as $payment)
        <div class="mt-3 space-y-1 text-sm">
            <div class="flex justify-between">
                <dt class="font-semibold">Paid by</dt>
                <dd>{{ $payment->method->label() }}</dd>
            </div>

            @if ($payment->tendered !== null)
                <div class="flex justify-between">
                    <dt>Cash received</dt>
                    <dd class="font-mono">{{ money($payment->tendered) }}</dd>
                </div>
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
        </div>
    @empty
        <p class="mt-3 text-center text-sm font-bold uppercase">Unpaid</p>
    @endforelse

    @if ($order->status === App\Enums\OrderStatus::Cancelled)
        <p class="mt-3 text-center text-sm font-bold uppercase">*** Cancelled ***</p>
    @endif

    <div class="my-4 border-t border-dashed border-slate-400"></div>

    <footer class="text-center text-sm">
        @if ($settings->get('receipt_footer'))
            <p>{{ $settings->get('receipt_footer') }}</p>
        @endif
        <p class="mt-2 text-xs text-slate-600">{{ now()->format('d M Y, g:i A') }}</p>
    </footer>
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
