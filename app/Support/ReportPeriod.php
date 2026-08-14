<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The stretch of time a report covers.
 *
 * One object shared by the report screen, the printable version and the CSV,
 * so all three always describe the same window — a report whose heading and
 * figures disagree is worse than no report.
 */
final class ReportPeriod
{
    /** Presets, in the order they are offered. */
    public const PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'This week',
        'last_week' => 'Last week',
        'month' => 'This month',
        'last_month' => 'Last month',
    ];

    public function __construct(
        public readonly string $key,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $key = $request->string('period')->toString();

        // Explicit dates win: they are what the person typed.
        if (filled($request->query('from')) || filled($request->query('to'))) {
            $from = self::parse($request->string('from')->toString()) ?? today();
            $to = self::parse($request->string('to')->toString()) ?? today();

            // Tolerate a backwards range rather than returning nothing.
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }

            return new self('custom', $from->startOfDay(), $to->endOfDay());
        }

        return self::preset(array_key_exists($key, self::PRESETS) ? $key : 'today');
    }

    public static function preset(string $key): self
    {
        [$from, $to] = match ($key) {
            'yesterday' => [today()->subDay(), today()->subDay()],
            'week' => [today()->startOfWeek(), today()->endOfWeek()],
            'last_week' => [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()],
            'month' => [today()->startOfMonth(), today()->endOfMonth()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            default => [today(), today()],
        };

        return new self($key, $from->copy()->startOfDay(), $to->copy()->endOfDay());
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

    public function isSingleDay(): bool
    {
        return $this->from->isSameDay($this->to);
    }

    /** "Wednesday, 12 August 2026" or "1 – 31 August 2026". */
    public function label(): string
    {
        if ($this->isSingleDay()) {
            return $this->from->format('l, j F Y');
        }

        if ($this->from->isSameMonth($this->to)) {
            return $this->from->format('j').' – '.$this->to->format('j F Y');
        }

        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /** What the preset is called, for a heading. */
    public function name(): string
    {
        return self::PRESETS[$this->key] ?? 'Custom period';
    }

    /** Days covered, used to decide whether a day-by-day table is worth showing. */
    public function days(): int
    {
        return $this->from->diffInDays($this->to) + 1;
    }

    public function filename(string $restaurant): string
    {
        $slug = str($restaurant)->slug()->value() ?: 'restro';

        $suffix = $this->isSingleDay()
            ? $this->from->format('Y-m-d')
            : $this->from->format('Y-m-d').'_to_'.$this->to->format('Y-m-d');

        return "{$slug}-sales-{$suffix}.csv";
    }

    /** @return array<string, string|null> */
    public function toQuery(array $overrides = []): array
    {
        return array_merge([
            'period' => $this->key,
            'from' => $this->key === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->key === 'custom' ? $this->to->toDateString() : null,
        ], $overrides);
    }
}
