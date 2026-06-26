<?php

namespace App\Tests\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\OrderStatus;
use App\Enum\Topping;
use App\Service\OrderService;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private Bakery $bakery;
    private CakeOrder $order;

    protected function setUp(): void
    {
        $this->service = new OrderService();

        $this->bakery = (new Bakery())
            ->setMoney(100.0)
            ->setReputation(50)
            ->setOrdersCompleted(0)
            ->setOrdersFailed(0);

        $this->order = (new CakeOrder())
            ->setStatus(OrderStatus::IN_PROGRESS)
            ->setPayout(50.0)
            ->setHappinessBonus(20)
            ->setRequiredSize(CakeSize::SIX_INCH)
            ->setRequiredFrostingFlavor(FrostingFlavor::VANILLA)
            ->setRequiredLayers(2)
            ->setRequiredToppings([Topping::SPRINKLES]);
    }

    private function matchingCake(float $qualityScore = 100.0): Cake
    {
        return (new Cake())
            ->setIsBaked(true)
            ->setQualityScore($qualityScore)
            ->setSize(CakeSize::SIX_INCH)
            ->setFrostingFlavor(FrostingFlavor::VANILLA)
            ->setLayers(2)
            ->setToppings([Topping::SPRINKLES]);
    }

    public function testFulfillScalesPayoutByQuality(): void
    {
        $this->order->setCake($this->matchingCake(100.0));

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(150.0, $this->bakery->getMoney());
        $this->assertSame(70, $this->bakery->getReputation());
        $this->assertSame(1, $this->bakery->getOrdersCompleted());
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testFulfillWithPartialQualityScalesPayout(): void
    {
        $this->order->setCake($this->matchingCake(50.0));

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(125.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testFulfillBelowMinQualityFails(): void
    {
        $this->order->setCake($this->matchingCake(10.0));

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100.0, $this->bakery->getMoney());
        $this->assertSame(40, $this->bakery->getReputation());
        $this->assertSame(1, $this->bakery->getOrdersFailed());
        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
    }

    public function testWrongSizePenalisesQuality(): void
    {
        $cake = $this->matchingCake(100.0)->setSize(CakeSize::CUPCAKE);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        // effective quality = 100 - 40 = 60, scale = 0.6
        $this->assertEqualsWithDelta(130.0, $this->bakery->getMoney(), 0.01);
    }

    public function testWrongFrostingPenalisesQuality(): void
    {
        $cake = $this->matchingCake(100.0)->setFrostingFlavor(FrostingFlavor::CHOCOLATE);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        // effective quality = 100 - 20 = 80, scale = 0.8
        $this->assertEqualsWithDelta(140.0, $this->bakery->getMoney(), 0.01);
    }

    public function testWrongLayersPenalisesQuality(): void
    {
        $cake = $this->matchingCake(100.0)->setLayers(4);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        // effective quality = 100 - 15 = 85, scale = 0.85
        $this->assertEqualsWithDelta(142.5, $this->bakery->getMoney(), 0.01);
    }

    public function testMissingToppingPenalisesQuality(): void
    {
        $cake = $this->matchingCake(100.0)->setToppings([]);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        // effective quality = 100 - 10 = 90, scale = 0.9
        $this->assertEqualsWithDelta(145.0, $this->bakery->getMoney(), 0.01);
    }

    public function testAllMismatchesCombineAndClampToZero(): void
    {
        $cake = $this->matchingCake(60.0)
            ->setSize(CakeSize::CUPCAKE)
            ->setFrostingFlavor(FrostingFlavor::CHOCOLATE)
            ->setLayers(4)
            ->setToppings([]);
        $this->order->setCake($cake);

        // effective quality = 60 - 40 - 20 - 15 - 10 = -25, clamped to 0 → fails
        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
    }

    public function testExtraToppingsNotPenalised(): void
    {
        $cake = $this->matchingCake(100.0)->setToppings([Topping::SPRINKLES, Topping::STRAWBERRIES]);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        // no penalty — required topping is present, extra is fine
        $this->assertSame(150.0, $this->bakery->getMoney());
    }

    public function testFulfillThrowsIfCakesNotBaked(): void
    {
        $this->order->setCake((new Cake())->setIsBaked(false));

        $this->expectException(\LogicException::class);
        $this->service->fulfill($this->order, $this->bakery);
    }

    public function testFulfillThrowsIfNoCakes(): void
    {
        $this->expectException(\LogicException::class);
        $this->service->fulfill($this->order, $this->bakery);
    }

    public function testFailDeductsReputationAndIncrementsOrdersFailed(): void
    {
        $this->service->fail($this->order, $this->bakery);

        $this->assertSame(40, $this->bakery->getReputation());
        $this->assertSame(1, $this->bakery->getOrdersFailed());
        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
    }

    public function testFailClampsReputationAtZero(): void
    {
        $this->bakery->setReputation(5);

        $this->service->fail($this->order, $this->bakery);

        $this->assertSame(0, $this->bakery->getReputation());
    }

    public function testFulfillClampsReputationAt100(): void
    {
        $this->bakery->setReputation(95);
        $this->order->setCake($this->matchingCake(100.0));

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100, $this->bakery->getReputation());
    }
}
