<?php

namespace App\Enum;

enum CakeFlavor: string
{
    case VANILLA    = 'vanilla';
    case CHOCOLATE  = 'chocolate';
    case RED_VELVET = 'red_velvet';

    public function label(): string
    {
        return match($this) {
            self::VANILLA    => 'Vanilla',
            self::CHOCOLATE  => 'Chocolate',
            self::RED_VELVET => 'Red Velvet',
        };
    }
}
