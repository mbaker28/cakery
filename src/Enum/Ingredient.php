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
            self::BUTTER  => 'stick',
            self::EGGS    => 'egg',
            self::SUGAR   => 'bag',
            self::MILK    => 'gallon',
        };
    }

    public function costPerUnit(): float
    {
        return match($this) {
            self::FLOUR   => 1.50,  // per bag
            self::BUTTER  => 1.50,  // per stick
            self::EGGS    => 0.35,  // per egg
            self::SUGAR   => 1.25,  // per bag
            self::MILK    => 3.00,  // per gallon
        };
    }

    public function spoilagePerDay(): float
    {
        return match($this) {
            self::EGGS   => 2.0,
            self::MILK   => 0.5,
            self::BUTTER => 0.5,
            self::FLOUR  => 0.0,
            self::SUGAR  => 0.0,
        };
    }

    public function group(): string
    {
        return 'Base Ingredients';
    }
}
