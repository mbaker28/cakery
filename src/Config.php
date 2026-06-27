<?php

namespace App;

class Config
{
    /** Seconds between each successive order spawning */
    public const SPAWN_INTERVAL = 15;

    /** Seconds the player has to fulfil an order after it spawns */
    public const SECONDS_PER_ORDER = 75;

    public const MIN_ORDERS_PER_DAY = 2;
    public const MAX_ORDERS_PER_DAY = 6;

    /** How many orders spawn today, scaling with reputation (2 → 6). */
    public static function ordersForDay(int $reputation): int
    {
        return min(
            self::MAX_ORDERS_PER_DAY,
            self::MIN_ORDERS_PER_DAY + (int) floor($reputation / 25)
        );
    }
}
