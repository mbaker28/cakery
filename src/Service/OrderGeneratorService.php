<?php

namespace App\Service;

use App\Config;
use App\Entity\CakeOrder;
use App\Enum\CakeFlavor;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\OrderStatus;
use App\Enum\Topping;

class OrderGeneratorService
{
    private const CUSTOMERS = [
        'Alice' => '👩‍🦰',
        'Bob'   => '👨‍🦲',
        'Clara' => '👩‍🦱',
        'David' => '👨‍🦳',
        'Eva'   => '👧',
        'Frank' => '👴',
        'Grace' => '👩‍🦳',
        'Henry' => '👦',
        'Iris'  => '👩',
        'Jack'  => '👨',
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

    public function generate(int $reputation, int $recipeBookLevel = 0, bool $isVip = false): CakeOrder
    {
        $tier     = $this->tier($reputation);
        $size     = $this->pick(self::SIZES_BY_TIER[$tier]);
        $layers   = $size === CakeSize::CUPCAKE ? 1 : random_int(...self::LAYERS_BY_TIER[$tier]);
        $flavor   = $this->pick(CakeFlavor::cases());
        $frosting = $this->pick(array_values(array_filter(
            FrostingFlavor::cases(),
            fn($f) => $f->requiredRecipeLevel() <= $recipeBookLevel
        )));
        $toppings = $this->randomToppings($tier, $recipeBookLevel);

        $payout = self::BASE_PAYOUT_BY_SIZE[$size->value]
            + ($layers * self::PAYOUT_PER_LAYER)
            + (count($toppings) * self::PAYOUT_PER_TOPPING)
            + ($tier * self::PAYOUT_PER_TIER);

        if ($isVip) {
            $payout *= Config::VIP_PAYOUT_MULTIPLIER;
        }

        [$minBonus, $maxBonus] = self::HAPPINESS_BONUS_BY_TIER[$tier];

        return (new CakeOrder())
            ->setStatus(OrderStatus::PENDING)
            ->setCustomerName($name = $this->pickKey(self::CUSTOMERS))
            ->setAvatar(self::CUSTOMERS[$name])
            ->setRequiredFlavor($flavor)
            ->setRequiredSize($size)
            ->setRequiredFrostingFlavor($frosting)
            ->setRequiredLayers($layers)
            ->setRequiredToppings($toppings ?: null)
            ->setPayout($payout)
            ->setHappinessBonus(random_int($minBonus, $maxBonus))
            ->setIsVip($isVip);
    }

    // Weights per [tier1, tier2, tier3] at each max tier.
    // Higher max tier unlocks harder orders but simpler orders still appear.
    private const TIER_WEIGHTS = [
        1 => [100, 0,  0],
        2 => [60,  40, 0],
        3 => [50,  30, 20],
    ];

    private function tier(int $reputation): int
    {
        $maxTier = match(true) {
            $reputation >= 67 => 3,
            $reputation >= 34 => 2,
            default           => 1,
        };

        $weights = self::TIER_WEIGHTS[$maxTier];
        $roll    = random_int(1, 100);
        $sum     = 0;
        foreach ($weights as $tier => $weight) {
            $sum += $weight;
            if ($roll <= $sum) {
                return $tier + 1;
            }
        }

        return $maxTier;
    }

    private function randomToppings(int $tier, int $recipeBookLevel = 0): array
    {
        $all   = array_values(array_filter(
            Topping::cases(),
            fn($t) => $t->requiredRecipeLevel() <= $recipeBookLevel
        ));
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

    private function pickKey(array $map): string
    {
        $keys = array_keys($map);
        return $keys[array_rand($keys)];
    }
}
