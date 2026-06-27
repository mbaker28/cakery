<?php

namespace App\Enum;

enum Topping: string implements Restockable
{
    case TOPPING_SPRINKLES       = 'topping_sprinkles';
    case TOPPING_CHOCOLATE_CHIPS = 'topping_chocolate_chips';
    case TOPPING_STRAWBERRIES    = 'topping_strawberries';

    public function inventoryKey(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match($this) {
            self::TOPPING_SPRINKLES       => 'Sprinkles',
            self::TOPPING_CHOCOLATE_CHIPS => 'Choco Chips',
            self::TOPPING_STRAWBERRIES    => 'Strawberries',
        };
    }

    public function unit(): string
    {
        return match($this) {
            self::TOPPING_SPRINKLES       => 'bottle',
            self::TOPPING_CHOCOLATE_CHIPS => 'bag',
            self::TOPPING_STRAWBERRIES    => 'pint',
        };
    }

    public function costPerUnit(): float
    {
        return 1.00;
    }

    public function group(): string
    {
        return 'Toppings';
    }
}
