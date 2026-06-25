<?php

namespace App\Service;

use App\Entity\Bakery;
use App\Entity\CakeOrder;
use App\Enum\OrderStatus;

class OrderService
{
    private const MIN_QUALITY = 25.0;
    private const REPUTATION_PENALTY = 10;

    public function fulfill(CakeOrder $order, Bakery $bakery): void
    {
        $cakes = $order->getCakes();

        if ($cakes->isEmpty() || $cakes->exists(fn($_, $cake) => !$cake->isBaked())) {
            throw new \LogicException('All cakes must be baked before fulfilling an order.');
        }

        $avgQuality = array_sum(
            $cakes->map(fn($cake) => $cake->getQualityScore())->toArray()
        ) / $cakes->count();

        if ($avgQuality < self::MIN_QUALITY) {
            $this->fail($order, $bakery);
            return;
        }

        $scale = $avgQuality / 100.0;

        $bakery->setMoney($bakery->getMoney() + ($order->getPayout() * $scale));
        $bakery->setReputation(min(100, $bakery->getReputation() + (int) round($order->getHappinessBonus() * $scale)));
        $bakery->setOrdersCompleted($bakery->getOrdersCompleted() + 1);

        $order->setStatus(OrderStatus::FULFILLED);
    }

    public function fail(CakeOrder $order, Bakery $bakery): void
    {
        $bakery->setReputation(max(0, $bakery->getReputation() - self::REPUTATION_PENALTY));
        $bakery->setOrdersFailed($bakery->getOrdersFailed() + 1);

        $order->setStatus(OrderStatus::FAILED);
    }
}
