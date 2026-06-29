<?php

namespace App\Enum;

enum FrostingFlavor: string
{
    case FROSTING_CHOCOLATE    = 'frosting_chocolate';
    case FROSTING_VANILLA      = 'frosting_vanilla';
    case FROSTING_CREAM_CHEESE = 'frosting_cream_cheese';
    case FROSTING_STRAWBERRY   = 'frosting_strawberry';
    case FROSTING_LEMON        = 'frosting_lemon';

    public function label(): string
    {
        return match($this) {
            self::FROSTING_CHOCOLATE    => 'Chocolate',
            self::FROSTING_VANILLA      => 'Vanilla',
            self::FROSTING_CREAM_CHEESE => 'Cream Cheese',
            self::FROSTING_STRAWBERRY   => 'Strawberry',
            self::FROSTING_LEMON        => 'Lemon',
        };
    }

    public function requiredRecipeLevel(): int
    {
        return match($this) {
            self::FROSTING_STRAWBERRY => 1,
            self::FROSTING_LEMON      => 2,
            default                   => 0,
        };
    }
}
