<?php

namespace App\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Enum\Ingredient;

class InventoryService
{
    private const BASE_REQUIREMENTS = [
        'flour'  => 2,
        'butter' => 1,
        'eggs'   => 1,
        'sugar'  => 1,
        'milk'   => 1,
    ];

    private const SIZE_MULTIPLIER = [
        'cupcake' => 0.5,
        '6"'      => 1.0,
        '9"'      => 1.5,
        'tiered'  => 2.5,
    ];

    /** @return array<string, int> */
    public function getRequirements(Cake $cake): array
    {
        if ($cake->getSize() === null || $cake->getLayers() === null) {
            throw new \LogicException('Cannot calculate requirements for a cake without size and layers.');
        }

        $multiplier = self::SIZE_MULTIPLIER[$cake->getSize()->value] * $cake->getLayers();

        $requirements = [];
        foreach (self::BASE_REQUIREMENTS as $ingredient => $base) {
            $requirements[$ingredient] = (int) ceil($base * $multiplier);
        }

        if ($cake->getFrostingFlavor() !== null) {
            $requirements[$cake->getFrostingFlavor()->value] = 1;
        }

        foreach ($cake->getToppings() ?? [] as $topping) {
            $requirements[$topping->value] = 1;
        }

        return $requirements;
    }

    public function canBake(Cake $cake, Bakery $bakery): bool
    {
        if ($cake->getSize() === null || $cake->getLayers() === null) {
            return false;
        }

        $inventory = $bakery->getInventory();

        foreach ($this->getRequirements($cake) as $ingredient => $needed) {
            if (($inventory[$ingredient] ?? 0) < $needed) {
                return false;
            }
        }

        return true;
    }

    public function deduct(Cake $cake, Bakery $bakery): void
    {
        if (!$this->canBake($cake, $bakery)) {
            throw new \RuntimeException('Insufficient ingredients to bake this cake.');
        }

        $inventory = $bakery->getInventory();

        foreach ($this->getRequirements($cake) as $ingredient => $needed) {
            $inventory[$ingredient] -= $needed;
        }

        $bakery->setInventory($inventory);
    }

    public function restock(Ingredient $ingredient, int $quantity, Bakery $bakery): void
    {
        $cost = $ingredient->costPerUnit() * $quantity;

        if ($bakery->getMoney() < $cost) {
            throw new \RuntimeException('Insufficient funds to restock.');
        }

        $bakery->setMoney($bakery->getMoney() - $cost);

        $inventory = $bakery->getInventory();
        $inventory[$ingredient->value] = ($inventory[$ingredient->value] ?? 0) + $quantity;
        $bakery->setInventory($inventory);
    }
}
