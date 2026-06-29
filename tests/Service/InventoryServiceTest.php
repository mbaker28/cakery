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
                'flour'  => 10.0,
                'butter' => 10.0,
                'eggs'   => 20.0,
                'sugar'  => 10.0,
                'milk'   => 10.0,
            ]);
    }

    private function cake(CakeSize $size, int $layers): Cake
    {
        return (new Cake())->setSize($size)->setLayers($layers);
    }

    public function testCupcakeOneLayerRequiresCorrectAmounts(): void
    {
        $requirements = $this->service->getRequirements($this->cake(CakeSize::CUPCAKE, 1));

        // 1.0 base * 0.25 size multiplier * 1 layer
        $this->assertEqualsWithDelta(0.25, $requirements['flour'], 0.001);
        $this->assertEqualsWithDelta(0.25, $requirements['butter'], 0.001);
        $this->assertEqualsWithDelta(1.0,  $requirements['eggs'], 0.001);
        $this->assertEqualsWithDelta(0.25, $requirements['sugar'], 0.001);
        $this->assertEqualsWithDelta(0.25, $requirements['milk'], 0.001);
    }

    public function testSixInchTwoLayersRequiresCorrectAmounts(): void
    {
        $requirements = $this->service->getRequirements($this->cake(CakeSize::SIX_INCH, 2));

        // 1.0 base * 0.5 size multiplier * 2 layers = 1.0 for most; eggs = 4.0 * 0.5 * 2 = 4.0
        $this->assertEqualsWithDelta(1.0, $requirements['flour'], 0.001);
        $this->assertEqualsWithDelta(1.0, $requirements['butter'], 0.001);
        $this->assertEqualsWithDelta(4.0, $requirements['eggs'], 0.001);
        $this->assertEqualsWithDelta(1.0, $requirements['sugar'], 0.001);
        $this->assertEqualsWithDelta(1.0, $requirements['milk'], 0.001);
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
        $before = $this->bakery->getInventory();

        $this->service->deduct($cake, $this->bakery);

        foreach ($requirements as $ingredient => $needed) {
            $this->assertSame($before[$ingredient] - $needed, $this->bakery->getInventory()[$ingredient]);
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
        $before = $this->bakery->getInventory()['flour'];
        $this->service->restock(Ingredient::FLOUR, 2, $this->bakery);

        $this->assertEqualsWithDelta($before + 2, $this->bakery->getInventory()['flour'], 0.001);
        $this->assertEqualsWithDelta(100.0 - (2 * Ingredient::FLOUR->costPerUnit()), $this->bakery->getMoney(), 0.01);
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
