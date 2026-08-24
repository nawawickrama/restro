<?php

namespace App\Enums;

/**
 * How a catering quantity is measured.
 *
 * A kitchen quotes "Malu Ambul Thiyal — 5 kg" but also "Fried Rice — 200
 * portions" and "Faluda — 30 litres", so the unit belongs to the line rather
 * than being baked into the item's name. Each catering item carries the unit
 * it is usually ordered in; a booking can override it.
 */
enum CateringUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Litre = 'l';
    case Millilitre = 'ml';
    case Portion = 'portion';
    case Plate = 'plate';
    case Piece = 'piece';
    case Packet = 'packet';
    case Bottle = 'bottle';
    case Tray = 'tray';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'kg',
            self::Gram => 'g',
            self::Litre => 'litres',
            self::Millilitre => 'ml',
            self::Portion => 'portions',
            self::Plate => 'plates',
            self::Piece => 'pieces',
            self::Packet => 'packets',
            self::Bottle => 'bottles',
            self::Tray => 'trays',
        };
    }

    /** "5 kg", "200 portions" — trimmed so 1 does not read as "1 portions". */
    public function format(float $quantity): string
    {
        $number = rtrim(rtrim(number_format($quantity, 3, '.', ','), '0'), '.');

        $unit = match (true) {
            $quantity == 1.0 => rtrim($this->label(), 's'),
            default => $this->label(),
        };

        return "{$number} {$unit}";
    }
}
