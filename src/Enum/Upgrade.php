<?php

namespace App\Enum;

enum Upgrade: string
{
    case FASTER_OVEN = 'faster_oven';
    case DOORBELL    = 'doorbell';
    case OVEN_ALARM  = 'oven_alarm';

    public function label(): string
    {
        return match($this) {
            self::FASTER_OVEN => 'Faster Oven',
            self::DOORBELL    => 'Doorbell',
            self::OVEN_ALARM  => 'Oven Alarm',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::FASTER_OVEN => 'Upgrade your oven to reduce baking time.',
            self::DOORBELL    => 'Get notified when a new order arrives.',
            self::OVEN_ALARM  => 'Get notified when a cake is done baking.',
        };
    }

    public function maxLevel(): int
    {
        return match($this) {
            self::FASTER_OVEN => 3,
            self::DOORBELL    => 1,
            self::OVEN_ALARM  => 1,
        };
    }

    public function costForLevel(int $nextLevel): float
    {
        return match($this) {
            self::FASTER_OVEN => match($nextLevel) {
                1       => 15.0,
                2       => 25.0,
                3       => 40.0,
                default => 0.0,
            },
            self::DOORBELL => 10.0,
            self::OVEN_ALARM => 15.0,
        };
    }

    public function effectForLevel(int $level): string
    {
        return match($this) {
            self::FASTER_OVEN => match($level) {
                1       => '10s bake time',
                2       => '7s bake time',
                3       => '5s bake time',
                default => '',
            },
            default => '',
        };
    }
}
