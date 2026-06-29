<?php

namespace App\Enum;

enum Topping: string
{
    case TOPPING_SPRINKLES       = 'topping_sprinkles';
    case TOPPING_CHOCOLATE_CHIPS = 'topping_chocolate_chips';
    case TOPPING_STRAWBERRIES    = 'topping_strawberries';

    public function label(): string
    {
        return match($this) {
            self::TOPPING_SPRINKLES       => 'Sprinkles',
            self::TOPPING_CHOCOLATE_CHIPS => 'Choco Chips',
            self::TOPPING_STRAWBERRIES    => 'Strawberries',
        };
    }
}
