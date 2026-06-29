<?php

namespace App\Enum;

enum Topping: string
{
    case TOPPING_SPRINKLES       = 'topping_sprinkles';
    case TOPPING_CHOCOLATE_CHIPS = 'topping_chocolate_chips';
    case TOPPING_STRAWBERRIES    = 'topping_strawberries';
    case TOPPING_CARAMEL_DRIZZLE = 'topping_caramel_drizzle';
    case TOPPING_FRESH_FLOWERS   = 'topping_fresh_flowers';

    public function label(): string
    {
        return match($this) {
            self::TOPPING_SPRINKLES       => 'Sprinkles',
            self::TOPPING_CHOCOLATE_CHIPS => 'Choco Chips',
            self::TOPPING_STRAWBERRIES    => 'Strawberries',
            self::TOPPING_CARAMEL_DRIZZLE => 'Caramel Drizzle',
            self::TOPPING_FRESH_FLOWERS   => 'Fresh Flowers',
        };
    }

    public function requiredRecipeLevel(): int
    {
        return match($this) {
            self::TOPPING_CARAMEL_DRIZZLE => 1,
            self::TOPPING_FRESH_FLOWERS   => 2,
            default                       => 0,
        };
    }
}
