<?php

namespace App\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\OrderStatus;
use App\Enum\Upgrade;

class OrderService
{
    private const MIN_QUALITY        = 60.0;
    private const REPUTATION_PENALTY = 10;

    // Elapsed fraction thresholds mirroring order_timer_controller.js patience levels
    private const EXCITED_THRESHOLD   = 0.25; // remaining > 0.75
    private const HAPPY_THRESHOLD     = 0.50; // remaining > 0.50
    private const WAITING_THRESHOLD   = 0.70; // remaining > 0.30
    private const IMPATIENT_THRESHOLD = 0.85; // remaining > 0.15

    public function fulfill(CakeOrder $order, Bakery $bakery): float
    {
        $cake = $order->getCake();

        if ($cake === null || !$cake->isBaked()) {
            throw new \LogicException('The cake must be baked before fulfilling an order.');
        }

        $fraction = $this->elapsedFraction($order) ?? 0.0;
        $quality  = max(0.0, $this->matchQuality($cake, $order) - $this->patiencePenalty($fraction));

        if ($quality < self::MIN_QUALITY) {
            $this->fail($order, $bakery);
            return 0.0;
        }

        $excited = $fraction < self::EXCITED_THRESHOLD;
        $earned  = $order->getPayout() * ($quality / 100.0) * $this->timeMultiplier($order);

        if ($quality === 100.0 && $excited && $bakery->hasUpgrade(Upgrade::DISPLAY_CASE)) {
            $earned *= 1.25;
        }

        $bakery->setMoney($bakery->getMoney() + $earned);
        $bakery->setReputation(min(100, $bakery->getReputation() + ($order->getHappinessBonus() ?? 5)));
        $bakery->setOrdersCompleted($bakery->getOrdersCompleted() + 1);

        if ($excited) {
            $bakery->setPerfectOrders($bakery->getPerfectOrders() + 1);
        }

        $order->setStatus(OrderStatus::FULFILLED);

        return $earned;
    }

    public function fail(CakeOrder $order, Bakery $bakery): void
    {
        $bakery->setReputation(max(0, $bakery->getReputation() - self::REPUTATION_PENALTY));
        $bakery->setOrdersFailed($bakery->getOrdersFailed() + 1);

        $order->setStatus(OrderStatus::FAILED);
    }

    /**
     * Returns 0–100 based purely on how closely the cake matches the order.
     * 100 = perfect match. Penalties applied for mismatches and unwanted extras.
     */
    private function matchQuality(Cake $cake, CakeOrder $order): float
    {
        $quality = 100.0;

        if ($cake->getSize() !== $order->getRequiredSize()) {
            $quality -= 50.0;
        }

        if ($cake->getFlavor() !== $order->getRequiredFlavor()) {
            $quality -= 25.0;
        }

        if ($cake->getFrostingFlavor() !== $order->getRequiredFrostingFlavor()) {
            $quality -= 25.0;
        }

        $layerDiff = abs(($cake->getLayers() ?? 1) - $order->getRequiredLayers());
        $quality  -= $layerDiff * 10.0;

        $required = $order->getRequiredToppings() ?? [];
        $actual   = $cake->getToppings() ?? [];

        foreach ($required as $topping) {
            if (!in_array($topping, $actual, true)) {
                $quality -= 15.0; // missing a requested topping
            }
        }

        foreach ($actual as $topping) {
            if (!in_array($topping, $required, true)) {
                $quality -= 5.0; // added a topping that wasn't asked for
            }
        }

        return max(0.0, $quality);
    }

    /**
     * Bonus for fast fulfillment, penalty for slow.
     * Thresholds are fractions of the order's total timer.
     */
    private function timeMultiplier(CakeOrder $order): float
    {
        $fraction = $this->elapsedFraction($order);

        if ($fraction === null) {
            return 1.0;
        }

        return match (true) {
            $fraction < 0.33 => 1.2,  // fast: +20%
            $fraction > 0.75 => 0.85, // slow: -15%
            default          => 1.0,
        };
    }

    /**
     * Quality penalty based on customer patience at fulfillment time.
     * Mirrors the patience thresholds in order_timer_controller.js.
     */
    private function patiencePenalty(float $fraction): float
    {
        return match (true) {
            $fraction < self::EXCITED_THRESHOLD   => 0.0,  // 😊 Excited
            $fraction < self::HAPPY_THRESHOLD     => 5.0,  // 🙂 Happy
            $fraction < self::WAITING_THRESHOLD   => 15.0, // 😐 Waiting
            $fraction < self::IMPATIENT_THRESHOLD => 25.0, // 😤 Impatient
            default                               => 40.0, // 😡 Leaving soon
        };
    }

    private function elapsedFraction(CakeOrder $order): ?float
    {
        if ($order->getSpawnAt() === null || $order->getFailsAt() === null) {
            return null;
        }

        $total   = $order->getFailsAt()->getTimestamp() - $order->getSpawnAt()->getTimestamp();
        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $order->getSpawnAt()->getTimestamp();

        if ($total <= 0) {
            return null;
        }

        return max(0.0, min(1.0, $elapsed / $total));
    }
}
