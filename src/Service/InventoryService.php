<?php

namespace App\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Enum\Restockable;

class InventoryService
{
    private const BASE_REQUIREMENTS = [
        'flour'  => 1.0,  // bags
        'butter' => 1.0,  // sticks
        'eggs'   => 4.0,  // eggs
        'sugar'  => 1.0,  // bags
        'milk'   => 1.0,  // gallons
    ];

    private const SIZE_MULTIPLIER = [
        'cupcake' => 0.25,
        '6"'      => 0.5,
        '9"'      => 1.0,
        'tiered'  => 1.5,
    ];

    /** @return array<string, float> */
    public function getRequirements(Cake $cake): array
    {
        if ($cake->getSize() === null || $cake->getLayers() === null) {
            throw new \LogicException('Cannot calculate requirements for a cake without size and layers.');
        }

        $multiplier = self::SIZE_MULTIPLIER[$cake->getSize()->value] * $cake->getLayers();

        $requirements = [];
        foreach (self::BASE_REQUIREMENTS as $ingredient => $base) {
            $requirements[$ingredient] = round($base * $multiplier, 4);
        }

        if ($cake->getFrostingFlavor() !== null) {
            $requirements[$cake->getFrostingFlavor()->inventoryKey()] = 1;
        }

        foreach ($cake->getToppings() ?? [] as $topping) {
            $requirements[$topping->inventoryKey()] = 1;
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

    public function restock(Restockable $item, int $quantity, Bakery $bakery): void
    {
        $cost = $item->costPerUnit() * $quantity;

        if ($bakery->getMoney() < $cost) {
            throw new \RuntimeException('Insufficient funds to restock.');
        }

        $bakery->setMoney($bakery->getMoney() - $cost);

        $inventory = $bakery->getInventory();
        $inventory[$item->inventoryKey()] = ($inventory[$item->inventoryKey()] ?? 0) + $quantity;
        $bakery->setInventory($inventory);
    }
}
