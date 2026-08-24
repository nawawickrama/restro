<?php

namespace Tests\Feature;

use App\Enums\CateringStatus;
use App\Enums\CateringUnit;
use App\Models\CateringBooking;
use App\Models\CateringItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catering diary: who the kitchen is cooking for, how much of what, and when.
 */
class CateringModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin2()
    {
        return tap($this->admin(), fn ($u) => $u->givePermissionTo(Permissions::MANAGE_CATERING));
    }

    public function test_a_booking_records_the_customer_the_guests_the_sheet_and_the_date(): void
    {
        $admin = $this->admin2();
        $date = today()->addDays(14)->toDateString();

        $this->actingAs($admin)->post(route('catering.store'), [
            'event_date' => $date,
            'event_time' => '19:30',
            'customer_name' => 'Nimal Perera',
            'customer_phone' => '0771234567',
            'pax' => 250,
            'venue' => 'Kelaniya Community Hall',
            'status' => CateringStatus::Confirmed->value,
            'note' => 'Deliver by 6pm',
            'items' => [
                ['name' => 'Malu Ambul Thiyal', 'quantity' => '5', 'unit' => 'kg', 'unit_price' => '2800'],
                ['name' => 'Fried Rice', 'quantity' => '250', 'unit' => 'portion'],
            ],
        ])->assertRedirect();

        $booking = CateringBooking::query()->with('items')->sole();

        $this->assertSame('Nimal Perera', $booking->customer_name);
        $this->assertSame(250, $booking->pax);
        $this->assertSame($date, $booking->event_date->toDateString());
        $this->assertSame('7:30 PM', $booking->timeLabel());
        $this->assertSame(CateringStatus::Confirmed, $booking->status);
        $this->assertStringStartsWith('CAT-', $booking->reference);

        $this->assertCount(2, $booking->items);
        $this->assertSame('5 kg', $booking->items[0]->amount());
        $this->assertSame('250 portions', $booking->items[1]->amount());

        // Only the priced line contributes to the total.
        $this->assertSame('14000.00', $booking->total);
    }

    /**
     * The unit belongs to the line: one kitchen sends curry out by the kilo and
     * rice by the portion on the same sheet.
     */
    public function test_each_line_carries_its_own_unit(): void
    {
        $admin = $this->admin2();

        $this->actingAs($admin)->post(route('catering.store'), [
            'event_date' => today()->addDay()->toDateString(),
            'status' => CateringStatus::Enquiry->value,
            'items' => [
                ['name' => 'Chicken Curry', 'quantity' => '7.5', 'unit' => 'kg'],
                ['name' => 'Faluda', 'quantity' => '30', 'unit' => 'l'],
                ['name' => 'Watalappan', 'quantity' => '1', 'unit' => 'tray'],
            ],
        ])->assertRedirect();

        $amounts = CateringBooking::query()->sole()->items->map->amount()->all();

        // Singular where the quantity is one, so "1 trays" never appears.
        $this->assertSame(['7.5 kg', '30 litres', '1 tray'], $amounts);
    }

    public function test_a_booking_without_a_date_is_refused(): void
    {
        $this->actingAs($this->admin2())->post(route('catering.store'), [
            'status' => CateringStatus::Enquiry->value,
            'customer_name' => 'Nobody',
        ])->assertSessionHasErrors('event_date');

        $this->assertSame(0, CateringBooking::query()->count());
    }

    /** A booking taken with a number is filed against the customer record. */
    public function test_a_phone_number_files_the_booking_against_a_customer(): void
    {
        $admin = $this->admin2();

        $this->actingAs($admin)->post(route('catering.store'), [
            'event_date' => today()->addDay()->toDateString(),
            'status' => CateringStatus::Enquiry->value,
            'customer_name' => 'Nimal',
            'customer_phone' => '077 123 4567',
        ])->assertRedirect();

        $customer = Customer::query()->sole();
        $this->assertSame('0771234567', $customer->phone_digits);
        $this->assertSame($customer->id, CateringBooking::query()->sole()->customer_id);
    }

    /** Saving replaces the sheet: whatever the form submits is what it now says. */
    public function test_saving_replaces_the_sheet_rather_than_adding_to_it(): void
    {
        $admin = $this->admin2();
        $booking = CateringBooking::factory()->create(['user_id' => $admin->id]);

        $payload = fn (array $items) => [
            'event_date' => $booking->event_date->toDateString(),
            'status' => $booking->status->value,
            'items' => $items,
        ];

        $this->actingAs($admin)->put(route('catering.update', $booking), $payload([
            ['name' => 'Chicken Curry', 'quantity' => '5', 'unit' => 'kg', 'unit_price' => '2000'],
            ['name' => 'Dhal', 'quantity' => '3', 'unit' => 'kg', 'unit_price' => '1000'],
        ]))->assertRedirect();

        $this->assertCount(2, $booking->fresh()->items);
        $this->assertSame('13000.00', $booking->fresh()->total);

        // The second save drops one line; the total follows.
        $this->actingAs($admin)->put(route('catering.update', $booking), $payload([
            ['name' => 'Chicken Curry', 'quantity' => '5', 'unit' => 'kg', 'unit_price' => '2000'],
        ]))->assertRedirect();

        $this->assertCount(1, $booking->fresh()->items);
        $this->assertSame('10000.00', $booking->fresh()->total);
    }

    /** A job can be booked before anybody has priced it. */
    public function test_a_sheet_without_prices_is_allowed(): void
    {
        $admin = $this->admin2();

        $this->actingAs($admin)->post(route('catering.store'), [
            'event_date' => today()->addDay()->toDateString(),
            'status' => CateringStatus::Enquiry->value,
            'items' => [['name' => 'Rice & Curry', 'quantity' => '100', 'unit' => 'portion']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $booking = CateringBooking::query()->sole();
        $this->assertSame('0.00', $booking->total);
        $this->assertNull($booking->items->first()->unit_price);
    }

    public function test_the_calendar_shows_the_month_and_its_bookings(): void
    {
        $admin = $this->admin2();
        $date = today()->startOfMonth()->addDays(9);

        CateringBooking::factory()->create([
            'user_id' => $admin->id,
            'customer_name' => 'Wedding Party',
            'event_date' => $date,
            'pax' => 300,
        ]);

        // A booking in a different month must not appear.
        CateringBooking::factory()->create([
            'user_id' => $admin->id,
            'customer_name' => 'Other Month',
            'event_date' => today()->startOfMonth()->addMonths(2)->addDay(),
        ]);

        $this->actingAs($admin)->get(route('catering.index', ['month' => $date->format('Y-m')]))
            ->assertOk()
            ->assertSee('Wedding Party')
            ->assertSee('300 pax')
            ->assertDontSee('Other Month');
    }

    public function test_the_calendar_counts_only_live_bookings(): void
    {
        $admin = $this->admin2();
        $date = today()->startOfMonth()->addDays(5);

        CateringBooking::factory()->create(['user_id' => $admin->id, 'event_date' => $date, 'pax' => 100]);
        CateringBooking::factory()->status(CateringStatus::Cancelled)
            ->create(['user_id' => $admin->id, 'event_date' => $date, 'pax' => 900]);

        $summary = $this->actingAs($admin)
            ->get(route('catering.index', ['month' => $date->format('Y-m')]))
            ->assertOk()
            ->viewData('summary');

        $this->assertSame(1, $summary['bookings']);
        $this->assertSame(100, $summary['pax']);
    }

    /** The popup renders from data sent with the page, so it opens instantly. */
    public function test_the_calendar_carries_each_booking_for_the_popup(): void
    {
        $admin = $this->admin2();
        $booking = CateringBooking::factory()->create([
            'user_id' => $admin->id,
            'event_date' => today(),
            'customer_name' => 'Nimal Perera',
            'pax' => 120,
        ]);
        $booking->items()->create([
            'name' => 'Malu Ambul Thiyal', 'quantity' => 5, 'unit' => CateringUnit::Kilogram->value, 'line_total' => 0,
        ]);

        $payload = $this->actingAs($admin)->get(route('catering.index'))->assertOk()->viewData('payload');

        $this->assertArrayHasKey($booking->id, $payload);
        $this->assertSame('Nimal Perera', $payload[$booking->id]['customer']);
        $this->assertSame(120, $payload[$booking->id]['pax']);
        $this->assertSame('5 kg', $payload[$booking->id]['items'][0]['amount']);
    }

    public function test_the_kitchen_sheet_prints_what_to_cook(): void
    {
        $admin = $this->admin2();
        $booking = CateringBooking::factory()->create([
            'user_id' => $admin->id,
            'customer_name' => 'Nimal Perera',
            'pax' => 250,
            'venue' => 'Kelaniya Community Hall',
        ]);
        $booking->items()->create([
            'name' => 'Malu Ambul Thiyal', 'quantity' => 5, 'unit' => 'kg', 'line_total' => 0,
        ]);

        $this->actingAs($admin)->get(route('catering.show', $booking))
            ->assertOk()
            ->assertSee('Catering sheet')
            ->assertSee($booking->reference)
            ->assertSee('Nimal Perera')
            ->assertSee('Kelaniya Community Hall')
            ->assertSee('Malu Ambul Thiyal')
            ->assertSee('5 kg');
    }

    public function test_the_catalogue_is_separate_from_the_restaurant_menu(): void
    {
        $admin = $this->admin2();

        $this->actingAs($admin)->post(route('catering-items.store'), [
            'name' => 'Malu Ambul Thiyal',
            'default_unit' => CateringUnit::Kilogram->value,
            'price' => 2800,
            'is_active' => '1',
        ])->assertRedirect();

        $item = CateringItem::query()->sole();
        $this->assertSame(CateringUnit::Kilogram, $item->default_unit);
        $this->assertSame('2800.00', $item->price);

        // It is a catering item, not a menu item the till can sell.
        $this->assertSame(0, MenuItem::query()->count());

        // And it does not reach the public menu.
        $this->get(route('menu'))->assertOk()->assertDontSee('Malu Ambul Thiyal');
    }

    public function test_a_catalogue_item_used_on_a_booking_is_switched_off_not_deleted(): void
    {
        $admin = $this->admin2();
        $item = CateringItem::factory()->create(['name' => 'Watalappan']);
        $booking = CateringBooking::factory()->create(['user_id' => $admin->id]);

        $booking->items()->create([
            'catering_item_id' => $item->id, 'name' => $item->name,
            'quantity' => 2, 'unit' => 'tray', 'line_total' => 0,
        ]);

        $this->actingAs($admin)->delete(route('catering-items.destroy', $item))->assertRedirect();

        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_catering_is_behind_its_own_permission(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('catering.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('catering-items.index'))->assertForbidden();

        $cashier->givePermissionTo(Permissions::MANAGE_CATERING);

        $this->actingAs($cashier)->get(route('catering.index'))->assertOk();
    }
}
