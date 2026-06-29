<?php

namespace App\Enum;

enum FrostingFlavor: string
{
    case FROSTING_CHOCOLATE    = 'frosting_chocolate';
    case FROSTING_VANILLA      = 'frosting_vanilla';
    case FROSTING_CREAM_CHEESE = 'frosting_cream_cheese';

    public function label(): string
    {
        return match($this) {
            self::FROSTING_CHOCOLATE    => 'Chocolate',
            self::FROSTING_VANILLA      => 'Vanilla',
            self::FROSTING_CREAM_CHEESE => 'Cream Cheese',
        };
    }
}
