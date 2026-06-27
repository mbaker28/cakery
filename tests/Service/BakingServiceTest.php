<?php

namespace App\Tests\Service;

use App\Entity\Cake;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\Topping;
use App\Service\BakingService;
use PHPUnit\Framework\TestCase;

class BakingServiceTest extends TestCase
{
    private BakingService $service;

    protected function setUp(): void
    {
        $this->service = new BakingService();
    }

    public function testBakeSetsIsBaked(): void
    {
        $cake = (new Cake())
            ->setSize(CakeSize::SIX_INCH)
            ->setLayers(2)
            ->setFrostingFlavor(FrostingFlavor::FROSTING_VANILLA);

        $this->service->bake($cake);

        $this->assertTrue($cake->isBaked());
    }

    public function testBakeSetsQualityScoreTo100(): void
    {
        $cake = (new Cake())
            ->setSize(CakeSize::CUPCAKE)
            ->setLayers(1)
            ->setFrostingFlavor(FrostingFlavor::FROSTING_CHOCOLATE)
            ->setToppings([Topping::TOPPING_SPRINKLES]);

        $this->service->bake($cake);

        $this->assertSame(100.0, $cake->getQualityScore());
    }
}
