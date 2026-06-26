<?php

namespace App\Enum;

enum Ingredient: string
{
    case FLOUR  = 'flour';
    case BUTTER = 'butter';
    case EGGS   = 'eggs';
    case SUGAR  = 'sugar';
    case MILK   = 'milk';

    case FROSTING_CHOCOLATE    = 'frosting_chocolate';
    case FROSTING_VANILLA      = 'frosting_vanilla';
    case FROSTING_CREAM_CHEESE = 'frosting_cream_cheese';

    case TOPPING_SPRINKLES       = 'topping_sprinkles';
    case TOPPING_CHOCOLATE_CHIPS = 'topping_chocolate_chips';
    case TOPPING_STRAWBERRIES    = 'topping_strawberries';

    public function label(): string
    {
        return match($this) {
            self::FLOUR   => 'Flour',
            self::BUTTER  => 'Butter',
            self::EGGS    => 'Eggs',
            self::SUGAR   => 'Sugar',
            self::MILK    => 'Milk',
            self::FROSTING_CHOCOLATE    => 'Chocolate',
            self::FROSTING_VANILLA      => 'Vanilla',
            self::FROSTING_CREAM_CHEESE => 'Cream Cheese',
            self::TOPPING_SPRINKLES       => 'Sprinkles',
            self::TOPPING_CHOCOLATE_CHIPS => 'Choco Chips',
            self::TOPPING_STRAWBERRIES    => 'Strawberries',
        };
    }

    public function costPerUnit(): float
    {
        return match($this) {
            self::FLOUR   => 0.50,
            self::BUTTER  => 1.20,
            self::EGGS    => 0.25,
            self::SUGAR   => 0.40,
            self::MILK    => 0.80,
            self::FROSTING_CHOCOLATE, self::FROSTING_VANILLA, self::FROSTING_CREAM_CHEESE => 1.50,
            self::TOPPING_SPRINKLES, self::TOPPING_CHOCOLATE_CHIPS, self::TOPPING_STRAWBERRIES => 1.00,
        };
    }

    public function group(): string
    {
        return match($this) {
            self::FLOUR, self::BUTTER, self::EGGS, self::SUGAR, self::MILK => 'Base Ingredients',
            self::FROSTING_CHOCOLATE, self::FROSTING_VANILLA, self::FROSTING_CREAM_CHEESE => 'Frostings',
            self::TOPPING_SPRINKLES, self::TOPPING_CHOCOLATE_CHIPS, self::TOPPING_STRAWBERRIES => 'Toppings',
        };
    }

    /** @return self[] */
    public static function frostings(): array
    {
        return array_values(array_filter(self::cases(), fn($i) => $i->group() === 'Frostings'));
    }

    /** @return self[] */
    public static function toppings(): array
    {
        return array_values(array_filter(self::cases(), fn($i) => $i->group() === 'Toppings'));
    }
}
