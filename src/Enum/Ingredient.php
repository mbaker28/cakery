<?php

namespace App\Enum;

enum Ingredient: string implements Restockable
{
    case FLOUR  = 'flour';
    case BUTTER = 'butter';
    case EGGS   = 'eggs';
    case SUGAR  = 'sugar';
    case MILK   = 'milk';

    public function inventoryKey(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match($this) {
            self::FLOUR   => 'Flour',
            self::BUTTER  => 'Butter',
            self::EGGS    => 'Eggs',
            self::SUGAR   => 'Sugar',
            self::MILK    => 'Milk',
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
        };
    }

    public function group(): string
    {
        return 'Base Ingredients';
    }
}
