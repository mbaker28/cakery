<?php

namespace App\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\OrderStatus;

class OrderService
{
    private const MIN_QUALITY        = 60.0;
    private const REPUTATION_PENALTY = 10;

    public function fulfill(CakeOrder $order, Bakery $bakery): float
    {
        $cake = $order->getCake();

        if ($cake === null || !$cake->isBaked()) {
            throw new \LogicException('The cake must be baked before fulfilling an order.');
        }

        $quality = $this->matchQuality($cake, $order);

        if ($quality < self::MIN_QUALITY) {
            $this->fail($order, $bakery);
            return 0.0;
        }

        $earned = $order->getPayout() * ($quality / 100.0) * $this->timeMultiplier($order);

        $bakery->setMoney($bakery->getMoney() + $earned);
        $bakery->setReputation(min(100, $bakery->getReputation() + ($order->getHappinessBonus() ?? 5)));
        $bakery->setOrdersCompleted($bakery->getOrdersCompleted() + 1);

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
        if ($order->getSpawnAt() === null || $order->getFailsAt() === null) {
            return 1.0;
        }

        $total   = $order->getFailsAt()->getTimestamp() - $order->getSpawnAt()->getTimestamp();
        $elapsed = (new \DateTimeImmutable())->getTimestamp() - $order->getSpawnAt()->getTimestamp();

        if ($total <= 0) {
            return 1.0;
        }

        $fraction = max(0.0, min(1.0, $elapsed / $total));

        return match (true) {
            $fraction < 0.33 => 1.2,  // fast: +20%
            $fraction > 0.75 => 0.85, // slow: -15%
            default          => 1.0,
        };
    }
}
