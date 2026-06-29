<?php

namespace App\Controller;

use App\Config;
use App\Enum\CakeBuildPhase;
use App\Enum\Upgrade;
use App\Repository\BakeryRepository;
use App\Repository\CakeOrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    public function __construct(
        private readonly BakeryRepository $bakeryRepository,
        private readonly CakeOrderRepository $cakeOrderRepository,
    ) {}

    #[Route('/api/notifications', name: 'api_notifications', methods: ['GET'])]
    public function notifications(Request $request): JsonResponse
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->json(['newOrders' => [], 'doneCakes' => []]);
        }

        $since     = $request->query->getInt('since', 0);
        $sinceDate = (new \DateTimeImmutable())->setTimestamp($since);
        $now       = new \DateTimeImmutable();

        $newOrders = [];
        if ($bakery->hasUpgrade(Upgrade::DOORBELL) && $since > 0) {
            foreach ($this->cakeOrderRepository->findOrdersSpawnedBetween($sinceDate, $now) as $order) {
                $newOrders[] = [
                    'id'           => $order->getId(),
                    'customerName' => $order->getCustomerName(),
                    'avatar'       => $order->getAvatar(),
                ];
            }
        }

        $doneCakes = [];
        if ($bakery->hasUpgrade(Upgrade::OVEN_ALARM)) {
            $bakingSeconds = Config::bakingSecondsForLevel($bakery->getUpgradeLevel(Upgrade::FASTER_OVEN));
            foreach ($this->cakeOrderRepository->findActiveOrders() as $order) {
                $cake = $order->getCake();
                if ($cake === null || $cake->getBuildPhase() !== CakeBuildPhase::BAKING) {
                    continue;
                }
                $startedAt = $cake->getBakingStartedAt();
                if ($startedAt === null) {
                    continue;
                }
                if ($startedAt->modify("+{$bakingSeconds} seconds") <= $now) {
                    $doneCakes[] = [
                        'orderId'      => $order->getId(),
                        'cakeId'       => $cake->getId(),
                        'customerName' => $order->getCustomerName(),
                        'avatar'       => $order->getAvatar(),
                    ];
                }
            }
        }

        return $this->json(['newOrders' => $newOrders, 'doneCakes' => $doneCakes]);
    }
}
