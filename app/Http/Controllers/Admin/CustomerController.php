<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Queries\CustomerFilters;
use App\Queries\CustomerQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerQuery $customers) {}

    public function index(Request $request): View
    {
        $filters = CustomerFilters::fromRequest($request);

        return view('admin.customers.index', [
            'filters' => $filters,
            'customers' => $this->customers->paginate($filters),
            'summary' => $this->customers->summary($filters),
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.form', ['customer' => new Customer]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create([
            ...$request->safe()->only(['name', 'phone', 'phone_digits', 'note']),
            // Added from this screen rather than met over the counter.
            'source' => CustomerSource::Manual,
        ]);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer added.');
    }

    /** Their details, what they have spent, and the orders behind it. */
    public function show(Customer $customer): View
    {
        $customer->load(['orders' => fn ($query) => $query->with('table')->latest()->limit(20)]);

        return view('admin.customers.show', [
            'customer' => $customer,
            'stats' => [
                'orders' => $customer->orders()->completed()->count(),
                'spent' => (float) $customer->orders()->completed()->sum('total'),
                'last' => $customer->orders()->completed()->max('created_at'),
            ],
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.form', compact('customer'));
    }

    /**
     * The source is deliberately not editable: it records where this customer
     * came from, and rewriting it would destroy the only answer to "where do
     * our regulars come from?".
     */
    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->safe()->only(['name', 'phone', 'phone_digits', 'note']));

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    /**
     * Deleting a customer keeps their orders. The name and number typed at the
     * time stay on each order, so receipts and reports are untouched — only the
     * link between them is dropped.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $orders = $customer->orders()->count();

        $customer->delete();

        return redirect()->route('customers.index')->with(
            'status',
            $orders > 0
                ? "Customer deleted. Their {$orders} ".str('order')->plural($orders).' were kept.'
                : 'Customer deleted.',
        );
    }
}
