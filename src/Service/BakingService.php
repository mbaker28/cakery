<?php

namespace App\Service;

use App\Entity\Cake;
use App\Enum\CakeSize;
use App\Enum\Ingredient;

class BakingService
{
    private const COMBOS = [
        [Ingredient::TOPPING_STRAWBERRIES,    Ingredient::FROSTING_CREAM_CHEESE,   20.0],
        [Ingredient::TOPPING_CHOCOLATE_CHIPS, Ingredient::FROSTING_CHOCOLATE,      15.0],
        [Ingredient::TOPPING_SPRINKLES,       Ingredient::FROSTING_VANILLA,        10.0],
        [Ingredient::TOPPING_CHOCOLATE_CHIPS, Ingredient::FROSTING_VANILLA,         8.0],
        [Ingredient::TOPPING_STRAWBERRIES,    Ingredient::TOPPING_SPRINKLES,       -8.0],
        [Ingredient::TOPPING_CHOCOLATE_CHIPS, Ingredient::FROSTING_CREAM_CHEESE,   -5.0],
    ];

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

        [$ideal, $steepness] = match($cake->getSize()) {
            CakeSize::CUPCAKE   => [1.0, 18.0],
            CakeSize::SIX_INCH  => [2.0,  8.0],
            CakeSize::NINE_INCH => [2.5,  7.0],
            CakeSize::TIERED    => [3.5,  6.0],
            default             => [2.0,  8.0],
        };

        return 30.0 - (abs($layers - $ideal) * $steepness);
    }

    private function toppingScore(Cake $cake): float
    {
        $toppings = $cake->getToppings() ?? [];
        $frosting = $cake->getFrostingFlavor();
        $bonus = 0.0;

        foreach (self::COMBOS as [$a, $b, $modifier]) {
            $hasA = in_array($a, $toppings, true) || $frosting === $a;
            $hasB = in_array($b, $toppings, true) || $frosting === $b;

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
