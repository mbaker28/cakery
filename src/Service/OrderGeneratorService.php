<?php

namespace App\Service;

use App\Entity\CakeOrder;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\OrderStatus;
use App\Enum\Topping;

class OrderGeneratorService
{
    private const CUSTOMER_NAMES = [
        'Alice', 'Bob', 'Clara', 'David', 'Eva',
        'Frank', 'Grace', 'Henry', 'Iris', 'Jack',
    ];

    private const SIZES_BY_TIER = [
        1 => [CakeSize::CUPCAKE, CakeSize::SIX_INCH],
        2 => [CakeSize::SIX_INCH, CakeSize::NINE_INCH],
        3 => [CakeSize::NINE_INCH, CakeSize::TIERED],
    ];

    private const LAYERS_BY_TIER = [
        1 => [1, 2],
        2 => [2, 3],
        3 => [3, 4],
    ];

    private const MAX_TOPPINGS_BY_TIER = [
        1 => 1,
        2 => 2,
        3 => 3,
    ];

    private const BASE_PAYOUT_BY_SIZE = [
        'cupcake' => 6.0,
        '6"'      => 12.0,
        '9"'      => 20.0,
        'tiered'  => 35.0,
    ];

    private const PAYOUT_PER_LAYER   = 2.0;
    private const PAYOUT_PER_TOPPING = 3.0;
    private const PAYOUT_PER_TIER    = 3.0;

    private const HAPPINESS_BONUS_BY_TIER = [
        1 => [5, 10],
        2 => [10, 15],
        3 => [15, 20],
    ];

    public function generate(int $reputation): CakeOrder
    {
        $tier     = $this->tier($reputation);
        $size     = $this->pick(self::SIZES_BY_TIER[$tier]);
        $layers   = random_int(...self::LAYERS_BY_TIER[$tier]);
        $frosting = $this->pick(FrostingFlavor::cases());
        $toppings = $this->randomToppings($tier);

        $payout = self::BASE_PAYOUT_BY_SIZE[$size->value]
            + ($layers * self::PAYOUT_PER_LAYER)
            + (count($toppings) * self::PAYOUT_PER_TOPPING)
            + ($tier * self::PAYOUT_PER_TIER);

        [$minBonus, $maxBonus] = self::HAPPINESS_BONUS_BY_TIER[$tier];

        return (new CakeOrder())
            ->setStatus(OrderStatus::PENDING)
            ->setCustomerName($this->pick(self::CUSTOMER_NAMES))
            ->setAvatar(strtolower($this->pick(self::CUSTOMER_NAMES)))
            ->setRequiredSize($size)
            ->setRequiredFrostingFlavor($frosting)
            ->setRequiredLayers($layers)
            ->setRequiredToppings($toppings ?: null)
            ->setPayout($payout)
            ->setHappinessBonus(random_int($minBonus, $maxBonus));
    }

    private function tier(int $reputation): int
    {
        return match(true) {
            $reputation >= 67 => 3,
            $reputation >= 34 => 2,
            default           => 1,
        };
    }

    private function randomToppings(int $tier): array
    {
        $all   = Topping::cases();
        $max   = self::MAX_TOPPINGS_BY_TIER[$tier];
        $count = random_int(0, min($max, count($all)));

        if ($count === 0) {
            return [];
        }

        $keys = array_rand($all, $count);
        return array_map(fn($k) => $all[$k], (array) $keys);
    }

    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
