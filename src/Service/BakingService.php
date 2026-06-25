<?php

namespace App\Service;

use App\Entity\Cake;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\Topping;

class BakingService
{
    public function bake(Cake $cake): float
    {
        $score = 50.0
            + $this->layerScore($cake)
            + $this->toppingScore($cake);

        $quality = (float) max(0, min(100, $score));

        $cake->setQualityScore($quality);
        $cake->setIsBaked(true);

        return $quality;
    }

    private function layerScore(Cake $cake): float
    {
        $layers = $cake->getLayers() ?? 0;

        // ideal is a float so that two adjacent layer counts can both be "perfect"
        [$ideal, $steepness] = match($cake->getSize()) {
            CakeSize::CUPCAKE   => [1.0, 15.0], // strict — a 3-layer cupcake is absurd
            CakeSize::SIX_INCH  => [2.0,  8.0],
            CakeSize::NINE_INCH => [2.5,  7.0], // 2 or 3 layers both great
            CakeSize::TIERED    => [3.5,  6.0], // 3 or 4 layers both great
            default             => [2.0,  8.0],
        };

        return 30.0 - (abs($layers - $ideal) * $steepness);
    }

    private const COMBOS = [
        [Topping::STRAWBERRIES,   FrostingFlavor::CREAM_CHEESE,  20.0],
        [Topping::CHOCOLATE_CHIPS, FrostingFlavor::CHOCOLATE,    15.0],
        [Topping::SPRINKLES,      FrostingFlavor::VANILLA,       10.0],
        [Topping::STRAWBERRIES,   Topping::SPRINKLES,            -8.0],
        [Topping::CHOCOLATE_CHIPS, FrostingFlavor::CREAM_CHEESE, -5.0],
    ];

    private function toppingScore(Cake $cake): float
    {
        $toppings = $cake->getToppings() ?? [];
        $frosting = $cake->getFrostingFlavor();
        $bonus = 0.0;

        foreach (self::COMBOS as [$a, $b, $modifier]) {
            $hasA = $a instanceof Topping
                ? in_array($a, $toppings, true)
                : $frosting === $a;
            $hasB = $b instanceof Topping
                ? in_array($b, $toppings, true)
                : $frosting === $b;

            if ($hasA && $hasB) {
                $bonus += $modifier;
            }
        }

        if (count($toppings) >= 3) {
            $bonus -= 15.0;
        }

        return $bonus;
    }
}
