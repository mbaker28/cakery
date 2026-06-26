<?php

namespace App\Tests\Service;

use App\Enum\CakeSize;
use App\Enum\OrderStatus;
use App\Enum\Topping;
use App\Service\OrderGeneratorService;
use PHPUnit\Framework\TestCase;

class OrderGeneratorServiceTest extends TestCase
{
    private OrderGeneratorService $service;

    protected function setUp(): void
    {
        $this->service = new OrderGeneratorService();
    }

    public function testGeneratedOrderHasCorrectInitialStatus(): void
    {
        $order = $this->service->generate(50);

        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
    }

    public function testTier1OnlyProducesCupcakeOrSixInch(): void
    {
        $allowed = [CakeSize::CUPCAKE, CakeSize::SIX_INCH];

        for ($i = 0; $i < 50; $i++) {
            $order = $this->service->generate(10);
            $this->assertContains($order->getRequiredSize(), $allowed);
        }
    }

    public function testTier2OnlyProducesSixInchOrNineInch(): void
    {
        $allowed = [CakeSize::SIX_INCH, CakeSize::NINE_INCH];

        for ($i = 0; $i < 50; $i++) {
            $order = $this->service->generate(50);
            $this->assertContains($order->getRequiredSize(), $allowed);
        }
    }

    public function testTier3OnlyProducesNineInchOrTiered(): void
    {
        $allowed = [CakeSize::NINE_INCH, CakeSize::TIERED];

        for ($i = 0; $i < 50; $i++) {
            $order = $this->service->generate(80);
            $this->assertContains($order->getRequiredSize(), $allowed);
        }
    }

    public function testTier1LayersAreWithinRange(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $order = $this->service->generate(10);
            $this->assertGreaterThanOrEqual(1, $order->getRequiredLayers());
            $this->assertLessThanOrEqual(2, $order->getRequiredLayers());
        }
    }

    public function testTier3LayersAreWithinRange(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $order = $this->service->generate(80);
            $this->assertGreaterThanOrEqual(3, $order->getRequiredLayers());
            $this->assertLessThanOrEqual(4, $order->getRequiredLayers());
        }
    }

    public function testTier1HasAtMostOneToppingRequired(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $order = $this->service->generate(10);
            $this->assertLessThanOrEqual(1, count($order->getRequiredToppings() ?? []));
        }
    }

    public function testTier3HasAtMostThreeToppingsRequired(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $order = $this->service->generate(80);
            $this->assertLessThanOrEqual(3, count($order->getRequiredToppings() ?? []));
        }
    }

    public function testRequiredToppingsAreValidEnumCases(): void
    {
        $valid = Topping::cases();

        for ($i = 0; $i < 20; $i++) {
            $order = $this->service->generate(80);
            foreach ($order->getRequiredToppings() ?? [] as $topping) {
                $this->assertContains($topping, $valid);
            }
        }
    }

    public function testHigherTierPayoutIsGreaterThanLowerTier(): void
    {
        $tier1Total = 0;
        $tier3Total = 0;
        $runs = 50;

        for ($i = 0; $i < $runs; $i++) {
            $tier1Total += $this->service->generate(10)->getPayout();
            $tier3Total += $this->service->generate(80)->getPayout();
        }

        $this->assertGreaterThan($tier1Total / $runs, $tier3Total / $runs);
    }

    public function testGeneratedOrderHasCustomerName(): void
    {
        $order = $this->service->generate(50);

        $this->assertNotEmpty($order->getCustomerName());
    }

    public function testGeneratedOrderHasAvatar(): void
    {
        $order = $this->service->generate(50);

        $this->assertNotEmpty($order->getAvatar());
    }

    public function testPayoutIsPositive(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertGreaterThan(0, $this->service->generate(50)->getPayout());
        }
    }
}
