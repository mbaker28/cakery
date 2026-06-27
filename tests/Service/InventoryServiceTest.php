<?php

namespace App\Tests\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\Ingredient;
use App\Enum\Topping;
use App\Service\InventoryService;
use PHPUnit\Framework\TestCase;

class InventoryServiceTest extends TestCase
{
    private InventoryService $service;
    private Bakery $bakery;

    protected function setUp(): void
    {
        $this->service = new InventoryService();

        $this->bakery = (new Bakery())
            ->setMoney(100.0)
            ->setInventory([
                'flour'  => 20,
                'butter' => 20,
                'eggs'   => 20,
                'sugar'  => 20,
                'milk'   => 20,
            ]);
    }

    private function cake(CakeSize $size, int $layers): Cake
    {
        return (new Cake())->setSize($size)->setLayers($layers);
    }

    public function testCupcakeOneLayerRequiresOneOfEach(): void
    {
        $requirements = $this->service->getRequirements($this->cake(CakeSize::CUPCAKE, 1));

        $this->assertSame(['flour' => 1, 'butter' => 1, 'eggs' => 1, 'sugar' => 1, 'milk' => 1], $requirements);
    }

    public function testSixInchTwoLayersRequiresCorrectAmounts(): void
    {
        $requirements = $this->service->getRequirements($this->cake(CakeSize::SIX_INCH, 2));

        $this->assertSame(['flour' => 2, 'butter' => 4, 'eggs' => 1, 'sugar' => 1, 'milk' => 1], $requirements);
    }

    public function testLargerCakesRequireMoreIngredients(): void
    {
        $cupcake = $this->service->getRequirements($this->cake(CakeSize::CUPCAKE, 1));
        $tiered  = $this->service->getRequirements($this->cake(CakeSize::TIERED, 4));

        foreach (array_keys($cupcake) as $ingredient) {
            $this->assertGreaterThan($cupcake[$ingredient], $tiered[$ingredient]);
        }
    }

    public function testMoreLayersRequireMoreIngredients(): void
    {
        $two  = $this->service->getRequirements($this->cake(CakeSize::SIX_INCH, 2));
        $four = $this->service->getRequirements($this->cake(CakeSize::SIX_INCH, 4));

        foreach (array_keys($two) as $ingredient) {
            $this->assertGreaterThan($two[$ingredient], $four[$ingredient]);
        }
    }

    public function testFrostingAddedToRequirements(): void
    {
        $cake = $this->cake(CakeSize::CUPCAKE, 1)->setFrostingFlavor(FrostingFlavor::FROSTING_VANILLA);
        $requirements = $this->service->getRequirements($cake);

        $this->assertArrayHasKey('frosting_vanilla', $requirements);
        $this->assertSame(1, $requirements['frosting_vanilla']);
    }

    public function testToppingAddedToRequirements(): void
    {
        $cake = $this->cake(CakeSize::CUPCAKE, 1)->setToppings([Topping::TOPPING_SPRINKLES, Topping::TOPPING_STRAWBERRIES]);
        $requirements = $this->service->getRequirements($cake);

        $this->assertSame(1, $requirements['topping_sprinkles']);
        $this->assertSame(1, $requirements['topping_strawberries']);
    }

    public function testCanBakeChecksFrostingStock(): void
    {
        $cake = $this->cake(CakeSize::CUPCAKE, 1)->setFrostingFlavor(FrostingFlavor::FROSTING_VANILLA);
        $this->bakery->setInventory([
            'flour' => 20, 'butter' => 20, 'eggs' => 20, 'sugar' => 20, 'milk' => 20,
            'frosting_vanilla' => 0,
        ]);

        $this->assertFalse($this->service->canBake($cake, $this->bakery));
    }

    public function testCanBakeReturnsTrueWithSufficientInventory(): void
    {
        $this->assertTrue($this->service->canBake($this->cake(CakeSize::SIX_INCH, 2), $this->bakery));
    }

    public function testCanBakeReturnsFalseWithInsufficientInventory(): void
    {
        $this->bakery->setInventory(['flour' => 0, 'butter' => 20, 'eggs' => 20, 'sugar' => 20, 'milk' => 20]);

        $this->assertFalse($this->service->canBake($this->cake(CakeSize::SIX_INCH, 2), $this->bakery));
    }

    public function testDeductReducesInventory(): void
    {
        $cake = $this->cake(CakeSize::SIX_INCH, 2);
        $requirements = $this->service->getRequirements($cake);

        $this->service->deduct($cake, $this->bakery);

        foreach ($requirements as $ingredient => $needed) {
            $this->assertSame(20 - $needed, $this->bakery->getInventory()[$ingredient]);
        }
    }

    public function testDeductThrowsWithInsufficientInventory(): void
    {
        $this->bakery->setInventory(['flour' => 0, 'butter' => 0, 'eggs' => 0, 'sugar' => 0, 'milk' => 0]);

        $this->expectException(\RuntimeException::class);
        $this->service->deduct($this->cake(CakeSize::SIX_INCH, 2), $this->bakery);
    }

    public function testRestockIncreasesInventoryAndDeductsMoney(): void
    {
        $this->service->restock(Ingredient::FLOUR, 10, $this->bakery);

        $this->assertSame(30, $this->bakery->getInventory()['flour']);
        $this->assertEqualsWithDelta(100.0 - (10 * Ingredient::FLOUR->costPerUnit()), $this->bakery->getMoney(), 0.01);
    }

    public function testRestockThrowsWithInsufficientFunds(): void
    {
        $this->bakery->setMoney(0.0);

        $this->expectException(\RuntimeException::class);
        $this->service->restock(Ingredient::BUTTER, 10, $this->bakery);
    }

    public function testRestockExactFundsSucceeds(): void
    {
        $cost = Ingredient::FLOUR->costPerUnit() * 10;
        $this->bakery->setMoney($cost);

        $this->service->restock(Ingredient::FLOUR, 10, $this->bakery);

        $this->assertEqualsWithDelta(0.0, $this->bakery->getMoney(), 0.01);
    }
}
