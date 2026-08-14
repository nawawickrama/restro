<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The state of the order history screen, parsed once from the query string.
 *
 * Everything here is validated against a whitelist. Sort columns and page
 * sizes come straight off the URL, so they are matched against known values
 * rather than interpolated into SQL.
 */
final class OrderHistoryFilters
{
    /** Sortable columns, mapped from the name shown in the URL. */
    public const SORTS = [
        'date' => 'created_at',
        'number' => 'order_number',
        'total' => 'total',
    ];

    public const PER_PAGE = [25, 50, 100];

    /** Quick ranges offered above the table. 'all' removes the date bound. */
    public const RANGES = ['today', 'yesterday', 'week', 'month', 'all', 'custom'];

    public function __construct(
        public readonly string $range,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly ?OrderType $type,
        public readonly ?OrderStatus $status,
        public readonly ?PaymentStatus $paymentStatus,
        public readonly string $search,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = $request->string('range')->toString();
        $range = in_array($range, self::RANGES, true) ? $range : 'today';

        // Typing a date means a custom range, whatever preset was selected
        // before. The alternative — a hidden field forcing 'custom' on every
        // submit — silently threw away the preset the moment you changed any
        // other filter.
        if (filled($request->query('from')) || filled($request->query('to'))) {
            $range = 'custom';
        }

        [$from, $to] = self::resolveDates($request, $range);

        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::SORTS) ? $sort : 'date';

        $perPage = (int) $request->integer('per_page');

        return new self(
            range: $range,
            from: $from,
            to: $to,
            type: OrderType::tryFrom($request->string('type')->toString()),
            status: OrderStatus::tryFrom($request->string('status')->toString()),
            paymentStatus: PaymentStatus::tryFrom($request->string('payment_status')->toString()),
            search: trim($request->string('search')->toString()),
            sort: $sort,
            direction: $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
            perPage: in_array($perPage, self::PER_PAGE, true) ? $perPage : self::PER_PAGE[0],
        );
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private static function resolveDates(Request $request, string $range): array
    {
        return match ($range) {
            'today' => [today()->startOfDay(), today()->endOfDay()],
            'yesterday' => [today()->subDay()->startOfDay(), today()->subDay()->endOfDay()],
            'week' => [today()->subDays(6)->startOfDay(), today()->endOfDay()],
            'month' => [today()->startOfMonth(), today()->endOfDay()],
            'all' => [null, null],
            'custom' => [
                self::parse($request->string('from')->toString())?->startOfDay(),
                self::parse($request->string('to')->toString())?->endOfDay() ?? today()->endOfDay(),
            ],
        };
    }

    private static function parse(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function sortColumn(): string
    {
        return self::SORTS[$this->sort];
    }

    /** True when any filter narrows the list beyond the default view. */
    public function isFiltered(): bool
    {
        return $this->activeChips() !== [] || $this->range !== 'today';
    }

    /**
     * The narrowing filters currently applied, as label => query key.
     *
     * The screen shows one removable chip per entry, so a cashier can see at a
     * glance why the list looks short and undo a single filter without having
     * to reset everything.
     *
     * @return array<string, string>
     */
    public function activeChips(): array
    {
        $chips = [];

        if ($this->search !== '') {
            $chips['search'] = '“'.$this->search.'”';
        }

        if ($this->type) {
            $chips['type'] = $this->type->label();
        }

        if ($this->status) {
            $chips['status'] = $this->status->label();
        }

        if ($this->paymentStatus) {
            $chips['payment_status'] = $this->paymentStatus->label().' orders';
        }

        return $chips;
    }

    /** How many filters the "Filters" button should advertise. */
    public function activeCount(): int
    {
        return count($this->activeChips());
    }

    /**
     * The filters as query-string parameters, for building links that keep the
     * current view.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(array $overrides = []): array
    {
        return array_merge([
            'range' => $this->range,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'payment_status' => $this->paymentStatus?->value,
            'search' => $this->search ?: null,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
            'page' => null,
        ], $overrides);
    }
}
