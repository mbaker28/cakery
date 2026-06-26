<?php

namespace App\Tests\Service;

use App\Entity\Cake;
use App\Enum\CakeSize;
use App\Enum\Ingredient;
use App\Service\BakingService;
use PHPUnit\Framework\TestCase;

class BakingServiceTest extends TestCase
{
    private BakingService $service;

    protected function setUp(): void
    {
        $this->service = new BakingService();
    }

    private function cake(CakeSize $size, int $layers, Ingredient $frosting, array $toppings = []): Cake
    {
        return (new Cake())
            ->setSize($size)
            ->setLayers($layers)
            ->setFrostingFlavor($frosting)
            ->setToppings($toppings);
    }

    public function testBakeSetsIsBakedAndQualityScore(): void
    {
        $cake = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA);

        $score = $this->service->bake($cake);

        $this->assertTrue($cake->isBaked());
        $this->assertSame($score, $cake->getQualityScore());
    }

    public function testQualityScoreIsClamped(): void
    {
        $cake = $this->cake(CakeSize::CUPCAKE, 4, Ingredient::FROSTING_VANILLA);

        $score = $this->service->bake($cake);

        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(100.0, $score);
    }

    public function testPerfectCupcakeScoresHigh(): void
    {
        $cake = $this->cake(CakeSize::CUPCAKE, 1, Ingredient::FROSTING_VANILLA, [Ingredient::TOPPING_SPRINKLES]);

        $score = $this->service->bake($cake);

        $this->assertGreaterThan(80.0, $score);
    }

    public function testAbsurdCupcakeScoresLow(): void
    {
        $cake = $this->cake(CakeSize::CUPCAKE, 4, Ingredient::FROSTING_VANILLA);

        $score = $this->service->bake($cake);

        $this->assertLessThan(30.0, $score);
    }

    public function testIdealLayersForSixInchScoresHigherThanOneLayers(): void
    {
        $good = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA);
        $bad  = $this->cake(CakeSize::SIX_INCH, 1, Ingredient::FROSTING_VANILLA);

        $this->assertGreaterThan($this->service->bake($bad), $this->service->bake($good));
    }

    public function testNineInchTwoAndThreeLayersBothScoreEqually(): void
    {
        $two   = $this->cake(CakeSize::NINE_INCH, 2, Ingredient::FROSTING_VANILLA);
        $three = $this->cake(CakeSize::NINE_INCH, 3, Ingredient::FROSTING_VANILLA);

        $this->assertEqualsWithDelta($this->service->bake($two), $this->service->bake($three), 0.01);
    }

    public function testTieredThreeAndFourLayersBothScoreEqually(): void
    {
        $three = $this->cake(CakeSize::TIERED, 3, Ingredient::FROSTING_VANILLA);
        $four  = $this->cake(CakeSize::TIERED, 4, Ingredient::FROSTING_VANILLA);

        $this->assertEqualsWithDelta($this->service->bake($three), $this->service->bake($four), 0.01);
    }

    public function testStrawberriesWithCreamCheeseBoostsScore(): void
    {
        $with    = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_CREAM_CHEESE, [Ingredient::TOPPING_STRAWBERRIES]);
        $without = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_CREAM_CHEESE);

        $this->assertGreaterThan($this->service->bake($without), $this->service->bake($with));
    }

    public function testStrawberriesWithSprinklesPenalisesScore(): void
    {
        $with    = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA, [Ingredient::TOPPING_STRAWBERRIES, Ingredient::TOPPING_SPRINKLES]);
        $without = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA, [Ingredient::TOPPING_SPRINKLES]);

        $this->assertLessThan($this->service->bake($without), $this->service->bake($with));
    }

    public function testAllThreeToppingsPenalisesScore(): void
    {
        $all = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA, [Ingredient::TOPPING_SPRINKLES, Ingredient::TOPPING_CHOCOLATE_CHIPS, Ingredient::TOPPING_STRAWBERRIES]);
        $one = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA, [Ingredient::TOPPING_SPRINKLES]);

        $this->assertLessThan($this->service->bake($one), $this->service->bake($all));
    }

    public function testNoToppingsIsNeutral(): void
    {
        $cake = $this->cake(CakeSize::SIX_INCH, 2, Ingredient::FROSTING_VANILLA, []);

        $score = $this->service->bake($cake);

        $this->assertEqualsWithDelta(80.0, $score, 0.01);
    }
}
