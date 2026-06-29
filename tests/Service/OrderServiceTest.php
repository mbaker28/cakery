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
            ->setOrdersFailed(0)
            ->setPerfectOrders(0);

        $this->order = (new CakeOrder())
            ->setStatus(OrderStatus::IN_PROGRESS)
            ->setPayout(100.0)
            ->setHappinessBonus(10)
            ->setRequiredSize(CakeSize::SIX_INCH)
            ->setRequiredFrostingFlavor(FrostingFlavor::FROSTING_VANILLA)
            ->setRequiredLayers(2)
            ->setRequiredToppings([Topping::TOPPING_SPRINKLES]);
    }

    private function matchingCake(): Cake
    {
        return (new Cake())
            ->setIsBaked(true)
            ->setQualityScore(100.0)
            ->setSize(CakeSize::SIX_INCH)
            ->setFrostingFlavor(FrostingFlavor::FROSTING_VANILLA)
            ->setLayers(2)
            ->setToppings([Topping::TOPPING_SPRINKLES]);
    }

    // Perfect match

    public function testPerfectMatchPaysFullPayout(): void
    {
        // No timer -> fraction = 0 -> excited -> no patience penalty
        $this->order->setCake($this->matchingCake());

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(200.0, $this->bakery->getMoney());
        $this->assertSame(60, $this->bakery->getReputation());
        $this->assertSame(1, $this->bakery->getOrdersCompleted());
        $this->assertSame(1, $this->bakery->getPerfectOrders());
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    // Mismatch penalties (quality-based pass/fail)

    public function testWrongSizeFailsOrder(): void
    {
        $cake = $this->matchingCake()->setSize(CakeSize::CUPCAKE); // -50 -> quality 50 < 60
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100.0, $this->bakery->getMoney());
        $this->assertSame(40, $this->bakery->getReputation());
        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
    }

    public function testWrongFrostingReducesPayoutButPasses(): void
    {
        // -25 -> quality 75 >= 60, payout = 100 * 0.75 * 1.0 = 75
        $cake = $this->matchingCake()->setFrostingFlavor(FrostingFlavor::FROSTING_CHOCOLATE);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(175.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testWrongLayersReducesPayout(): void
    {
        // 2 layers off, -20 -> quality 80, payout = 100 * 0.80 * 1.0 = 80
        $cake = $this->matchingCake()->setLayers(4);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(180.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testMissingRequiredToppingReducesPayout(): void
    {
        // -15 -> quality 85, payout = 100 * 0.85 * 1.0 = 85
        $cake = $this->matchingCake()->setToppings([]);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(185.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testExtraToppingAppliesSmallPenaltyButPasses(): void
    {
        // required: sprinkles; extra: strawberries, -5 -> quality 95, payout = 100 * 0.95 * 1.0 = 95
        $cake = $this->matchingCake()->setToppings([Topping::TOPPING_SPRINKLES, Topping::TOPPING_STRAWBERRIES]);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(195.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(OrderStatus::FULFILLED, $this->order->getStatus());
    }

    public function testCombinedMismatchesBelowThresholdFailsOrder(): void
    {
        // -50 (size) -25 (frosting) = 25 quality, below 60 threshold
        $cake = $this->matchingCake()
            ->setSize(CakeSize::CUPCAKE)
            ->setFrostingFlavor(FrostingFlavor::FROSTING_CHOCOLATE);
        $this->order->setCake($cake);

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
        $this->assertSame(100.0, $this->bakery->getMoney());
    }

    // Time multiplier

    public function testFastFulfillmentAppliesBonus(): void
    {
        $now = new \DateTimeImmutable();
        $this->order
            ->setCake($this->matchingCake())
            ->setSpawnAt($now->modify('-20 seconds'))   // 17% elapsed, excited (no penalty) + fast time bonus
            ->setFailsAt($now->modify('+100 seconds'));

        $earned = $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(120.0, $earned, 0.01); // 100 * 1.0 quality * 1.2 time
        $this->assertEqualsWithDelta(220.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(1, $this->bakery->getPerfectOrders());
    }

    public function testSlowFulfillmentAppliesPenalty(): void
    {
        $now = new \DateTimeImmutable();
        $this->order
            ->setCake($this->matchingCake())
            ->setSpawnAt($now->modify('-100 seconds'))  // 83% elapsed, impatient (-25 quality) + slow time
            ->setFailsAt($now->modify('+20 seconds'));

        $earned = $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(63.75, $earned, 0.01); // 100 * 0.75 quality * 0.85 time
        $this->assertEqualsWithDelta(163.75, $this->bakery->getMoney(), 0.01);
        $this->assertSame(0, $this->bakery->getPerfectOrders());
    }

    public function testNormalSpeedWithHappyCustomerReducesPayout(): void
    {
        $now = new \DateTimeImmutable();
        $this->order
            ->setCake($this->matchingCake())
            ->setSpawnAt($now->modify('-60 seconds'))   // 50% elapsed, waiting (-15 quality) + normal time
            ->setFailsAt($now->modify('+60 seconds'));

        $earned = $this->service->fulfill($this->order, $this->bakery);

        $this->assertEqualsWithDelta(85.0, $earned, 0.01); // 100 * 0.85 quality * 1.0 time
        $this->assertEqualsWithDelta(185.0, $this->bakery->getMoney(), 0.01);
        $this->assertSame(0, $this->bakery->getPerfectOrders());
    }

    public function testPatiencePenaltyCanCauseOrderToFail(): void
    {
        $now = new \DateTimeImmutable();
        // Wrong frosting brings base quality to 75, impatient penalty (-25) drops it to 50, below 60
        $cake = $this->matchingCake()
            ->setFrostingFlavor(FrostingFlavor::FROSTING_CHOCOLATE);
        $this->order
            ->setCake($cake)
            ->setSpawnAt($now->modify('-90 seconds'))   // 75% elapsed, impatient (-25)
            ->setFailsAt($now->modify('+30 seconds'));

        $this->service->fulfill($this->order, $this->bakery);

        // base quality 75 - patience penalty 25 = 50, below 60 threshold
        $this->assertSame(OrderStatus::FAILED, $this->order->getStatus());
        $this->assertSame(100.0, $this->bakery->getMoney());
        $this->assertSame(0, $this->bakery->getPerfectOrders());
    }

    public function testNoTimerDefaultsToFullPayout(): void
    {
        // No spawnAt/failsAt, defaults to 1.0 multiplier and no patience penalty
        $this->order->setCake($this->matchingCake());

        $earned = $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100.0, $earned);
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

    // Edge cases

    public function testFulfillClampsReputationAt100(): void
    {
        $this->bakery->setReputation(95);
        $this->order->setCake($this->matchingCake());

        $this->service->fulfill($this->order, $this->bakery);

        $this->assertSame(100, $this->bakery->getReputation());
    }

    public function testFulfillThrowsIfCakeNotBaked(): void
    {
        $this->order->setCake((new Cake())->setIsBaked(false));

        $this->expectException(\LogicException::class);
        $this->service->fulfill($this->order, $this->bakery);
    }

    public function testFulfillThrowsIfNoCake(): void
    {
        $this->expectException(\LogicException::class);
        $this->service->fulfill($this->order, $this->bakery);
    }
}
