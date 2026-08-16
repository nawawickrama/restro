<?php

namespace App\Queries;

use App\Enums\CustomerSource;
use Illuminate\Http\Request;

/**
 * The state of the customer list, parsed once from the query string.
 *
 * Sort columns and page sizes come straight off the URL, so they are matched
 * against whitelists rather than interpolated.
 */
final class CustomerFilters
{
    /** Sortable columns, mapped from the name shown in the URL. */
    public const SORTS = [
        'name' => 'name',
        'added' => 'created_at',
        'orders' => 'orders_count',
        'spent' => 'orders_sum_total',
        'last' => 'last_order_at',
    ];

    public const PER_PAGE = [25, 50, 100];

    public function __construct(
        public readonly ?CustomerSource $source,
        public readonly string $search,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::SORTS) ? $sort : 'added';

        $perPage = (int) $request->integer('per_page');

        return new self(
            source: CustomerSource::tryFrom($request->string('source')->toString()),
            search: trim($request->string('search')->toString()),
            sort: $sort,
            direction: $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
            perPage: in_array($perPage, self::PER_PAGE, true) ? $perPage : self::PER_PAGE[0],
        );
    }

    public function sortColumn(): string
    {
        return self::SORTS[$this->sort];
    }

    public function isFiltered(): bool
    {
        return $this->source !== null || $this->search !== '';
    }

    /**
     * The filters as query-string parameters, for links that keep the view.
     *
     * @return array<string, string|int|null>
     */
    public function toQuery(array $overrides = []): array
    {
        return array_merge([
            'source' => $this->source?->value,
            'search' => $this->search ?: null,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
            'page' => null,
        ], $overrides);
    }
}
