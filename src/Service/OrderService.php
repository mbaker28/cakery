<?php

namespace App\Service;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\OrderStatus;

class OrderService
{
    private const MIN_QUALITY = 25.0;
    private const REPUTATION_PENALTY = 10;

    private const MISMATCH_PENALTIES = [
        'size'    => 40.0,
        'frosting' => 20.0,
        'layers'  => 15.0,
        'topping' => 10.0, // per missing topping
    ];

    public function fulfill(CakeOrder $order, Bakery $bakery): float
    {
        $cakes = $order->getCakes();

        if ($cakes->isEmpty() || $cakes->exists(fn($_, $cake) => !$cake->isBaked())) {
            throw new \LogicException('All cakes must be baked before fulfilling an order.');
        }

        $avgQuality = array_sum(
            $cakes->map(fn($cake) => $this->effectiveQuality($cake, $order))->toArray()
        ) / $cakes->count();

        if ($avgQuality < self::MIN_QUALITY) {
            $this->fail($order, $bakery);
            return 0.0;
        }

        $scale = $avgQuality / 100.0;
        $earned = $order->getPayout() * $scale;

        $bakery->setMoney($bakery->getMoney() + $earned);
        $bakery->setReputation(min(100, $bakery->getReputation() + (int) round($order->getHappinessBonus() * $scale)));
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

    private function effectiveQuality(Cake $cake, CakeOrder $order): float
    {
        $penalty = 0.0;

        if ($cake->getSize() !== $order->getRequiredSize()) {
            $penalty += self::MISMATCH_PENALTIES['size'];
        }

        if ($cake->getFrostingFlavor() !== $order->getRequiredFrostingFlavor()) {
            $penalty += self::MISMATCH_PENALTIES['frosting'];
        }

        if ($cake->getLayers() !== $order->getRequiredLayers()) {
            $penalty += self::MISMATCH_PENALTIES['layers'];
        }

        $requiredToppings = $order->getRequiredToppings() ?? [];
        $cakeToppings = $cake->getToppings() ?? [];
        foreach ($requiredToppings as $topping) {
            if (!in_array($topping, $cakeToppings, true)) {
                $penalty += self::MISMATCH_PENALTIES['topping'];
            }
        }

        return max(0.0, $cake->getQualityScore() - $penalty);
    }
}
