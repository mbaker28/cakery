<?php

namespace App\Enum;

enum Ingredient: string
{
    case FLOUR = 'flour';
    case BUTTER = 'butter';
    case EGGS = 'eggs';
    case SUGAR = 'sugar';
    case MILK = 'milk';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function costPerUnit(): float
    {
        return match($this) {
            self::FLOUR => 0.50,
            self::BUTTER => 1.20,
            self::EGGS => 0.25,
            self::SUGAR => 0.40,
            self::MILK => 0.80
        };
    }
}