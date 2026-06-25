<?php

namespace App\Tests\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\OrderStatus;
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
            ->setHappinessBonus(20);
    }

    public function testFulfillScalesPayoutByQuality(): void
    {
        $cake = (new Cake())->setIsBaked(true)->setQualityScore(100.0);
        $this->order->addCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(150.0, $this->bakery->getMoney());
        $this->assertSame(70, $this->bakery->getReputation());
        $this->assertSame(1, $this->bakery->getOrdersCompleted());
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testFulfillWithPartialQualityScalesPayout(): void
    {
        $cake = (new Cake())->setIsBaked(true)->setQualityScore(50.0);
        $this->order->addCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(125.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testFulfillBelowMinQualityFails(): void
    {
        $cake = (new Cake())->setIsBaked(true)->setQualityScore(10.0);
        $this->order->addCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100.0, $this->bakery->getMoney());
        $this->assertSame(40, $this->bakery->getReputation());
        $this->assertSame(1, $this->bakery->getOrdersFailed());
        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
    }

    public function testFulfillAveragesQualityAcrossMultipleCakes(): void
    {
        $this->order->addCake((new Cake())->setIsBaked(true)->setQualityScore(100.0));
        $this->order->addCake((new Cake())->setIsBaked(true)->setQualityScore(0.0));

        $this->service->fulfill($this->order, $this->bakery);

        // avg quality = 50, scale = 0.5
        $this->assertEqualsWithDelta(125.0, $this->bakery->getMoney(), 0.01);
    }

    public function testFulfillThrowsIfCakesNotBaked(): void
    {
        $this->order->addCake((new Cake())->setIsBaked(false));

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
        $cake = (new Cake())->setIsBaked(true)->setQualityScore(100.0);
        $this->order->addCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100, $this->bakery->getReputation());
    }
}
