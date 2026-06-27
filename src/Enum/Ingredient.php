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

    public function unit(): string
    {
        return match($this) {
            self::FLOUR   => 'bag',
            self::BUTTER  => 'tbsp',
            self::EGGS    => 'egg',
            self::SUGAR   => 'bag',
            self::MILK    => 'cup',
        };
    }

    public function costPerUnit(): float
    {
        return match($this) {
            self::FLOUR   => 0.30,  // per bag
            self::BUTTER  => 0.15,  // per tablespoon
            self::EGGS    => 0.35,  // per egg
            self::SUGAR   => 0.25,  // per bag
            self::MILK    => 0.60,  // per cup
        };
    }

    public function group(): string
    {
        return 'Base Ingredients';
    }
}
