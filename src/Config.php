<?php

namespace App;

class Config
{
    public const SECONDS_PER_DAY = 180;
    public const ORDERS_PER_DAY  = 5;

    /** Seconds from day start when each order spawns (index = spawn slot) */
    public const SPAWN_DELAYS = [0, 0, 60, 90, 120];
}
