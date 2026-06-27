<?php

namespace App\Controller;

use App\Config;
use App\Entity\Bakery;
use App\Entity\CakeOrder;
use App\Enum\FrostingFlavor;
use App\Enum\GamePhase;
use App\Enum\Ingredient;
use App\Enum\OrderStatus;
use App\Enum\Topping;
use App\Repository\BakeryRepository;
use App\Repository\CakeOrderRepository;
use App\Service\InventoryService;
use App\Service\OrderGeneratorService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

class GameController extends AbstractController
{
    public function __construct(
        private readonly BakeryRepository $bakeryRepository,
        private readonly CakeOrderRepository $cakeOrderRepository,
        private readonly OrderGeneratorService $orderGeneratorService,
        private readonly OrderService $orderService,
        private readonly InventoryService $inventoryService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'game_index', methods: ['GET'])]
    public function index(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        if ($bakery->getPhase() === GamePhase::SHOP) {
            return $this->redirectToRoute('game_shop');
        }

        $now    = new \DateTimeImmutable();
        $orders = $this->cakeOrderRepository->findActiveOrders();

        return $this->render('game/index.html.twig', [
            'bakery'         => $bakery,
            'orders'         => $orders,
            'serverNow'      => $now->getTimestamp(),
            'bakingDuration' => Config::BAKING_SECONDS,
        ]);
    }

    #[Route('/new', name: 'game_new', methods: ['GET'])]
    public function new(): Response
    {
        if ($this->bakeryRepository->findOneBy([]) !== null) {
            return $this->redirectToRoute('game_index');
        }

        return $this->render('game/new.html.twig');
    }

    #[Route('/new', name: 'game_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if ($this->bakeryRepository->findOneBy([]) !== null) {
            return $this->redirectToRoute('game_index');
        }

        $maxDays = $request->request->getInt('max_days', 7);
        if (!in_array($maxDays, [7, 14, 30], true)) {
            $maxDays = 7;
        }

        $bakery = (new Bakery())
            ->setName($request->request->getString('name', 'My Bakery'))
            ->setMoney(50.0)
            ->setReputation(20)
            ->setDay(1)
            ->setMaxDays($maxDays)
            ->setOrdersCompleted(0)
            ->setOrdersFailed(0)
            ->setPhase(GamePhase::DAY);

        $this->em->persist($bakery);
        $this->spawnOrdersForDay($bakery);
        $this->em->flush();

        return $this->redirectToRoute('game_index');
    }

    #[Route('/advance-day', name: 'game_advance_day', methods: ['POST'])]
    public function advanceDay(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        $activeOrders = $this->cakeOrderRepository->findActiveOrders();

        if (!empty($activeOrders)) {
            $this->addFlash('danger', 'You must complete all orders before ending the day.');
            return $this->redirectToRoute('game_index');
        }

        if ($bakery->getDay() >= $bakery->getMaxDays()) {
            return $this->redirectToRoute('game_results');
        }

        $bakery->setPhase(GamePhase::SHOP);
        $this->em->flush();

        return $this->redirectToRoute('game_shop');
    }

    #[Route('/end-day-early', name: 'game_end_day_early', methods: ['POST'])]
    public function endDayEarly(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        if ($bakery->getPhase() !== GamePhase::DAY) {
            return $this->redirectToRoute('game_index');
        }

        foreach ($this->cakeOrderRepository->findActiveOrders() as $order) {
            $this->orderService->fail($order, $bakery);
        }

        $this->em->flush();

        if ($bakery->getDay() >= $bakery->getMaxDays()) {
            return $this->redirectToRoute('game_results');
        }

        $bakery->setPhase(GamePhase::SHOP);
        $this->em->flush();

        return $this->redirectToRoute('game_shop');
    }

    #[Route('/shop', name: 'game_shop', methods: ['GET'])]
    public function shop(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        if ($bakery->getPhase() === GamePhase::DAY) {
            return $this->redirectToRoute('game_index');
        }

        return $this->render('game/shop.html.twig', [
            'bakery'        => $bakery,
            'restockables'  => [...Ingredient::cases(), ...FrostingFlavor::cases(), ...Topping::cases()],
            'nextDayOrders' => Config::ordersForDay($bakery->getReputation()),
        ]);
    }

    #[Route('/start-day', name: 'game_start_day', methods: ['POST'])]
    public function startDay(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        if ($bakery->getPhase() === GamePhase::DAY) {
            return $this->redirectToRoute('game_index');
        }

        $bakery->setDay($bakery->getDay() + 1);
        $bakery->setPhase(GamePhase::DAY);
        $this->spawnOrdersForDay($bakery);
        $this->em->flush();

        return $this->redirectToRoute('game_index');
    }

    #[Route('/order/{id}/expire', name: 'order_expire', methods: ['POST'])]
    public function expireOrder(CakeOrder $order): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery !== null
            && $order->getFailsAt() !== null
            && $order->getFailsAt() <= new \DateTimeImmutable()
            && in_array($order->getStatus(), [OrderStatus::PENDING, OrderStatus::IN_PROGRESS], true)
        ) {
            $this->orderService->fail($order, $bakery);
            $this->em->flush();
            $this->addFlash('danger', sprintf('😤 %s ran out of patience and left!', $order->getCustomerName()));
        }

        return $this->redirectToRoute('game_index');
    }

    #[Route('/shop/restock', name: 'game_restock', methods: ['POST'])]
    public function restock(Request $request): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null || $bakery->getPhase() !== GamePhase::SHOP) {
            return $this->redirectToRoute('game_index');
        }

        $value    = $request->request->getString('ingredient');
        $item     = Ingredient::tryFrom($value) ?? FrostingFlavor::tryFrom($value) ?? Topping::tryFrom($value);
        $quantity = max(1, $request->request->getInt('quantity', 5));

        $message = null;
        $success = false;

        if ($item !== null) {
            try {
                $this->inventoryService->restock($item, $quantity, $bakery);
                $this->em->flush();
                $message = sprintf('Restocked %d× %s.', $quantity, $item->label());
                $success = true;
            } catch (\RuntimeException) {
                $message = 'Not enough money.';
            }
        }

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('game/_restock.stream.html.twig', [
                'bakery'   => $bakery,
                'item'     => $item,
                'success'  => $success,
                'message'  => $message,
                'quantity' => $quantity,
            ]);
        }

        if ($message !== null) {
            $this->addFlash($success ? 'success' : 'danger', $message);
        }

        return $this->redirectToRoute('game_shop');
    }

    #[Route('/results', name: 'game_results', methods: ['GET'])]
    public function results(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        return $this->render('game/results.html.twig', [
            'bakery' => $bakery,
        ]);
    }

    #[Route('/restart', name: 'game_restart', methods: ['POST'])]
    public function restart(): Response
    {
        $this->em->createQuery('DELETE FROM App\Entity\Cake c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\CakeOrder co')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Bakery b')->execute();

        return $this->redirectToRoute('game_new');
    }

    private function spawnOrdersForDay(Bakery $bakery): void
    {
        $count = Config::ordersForDay($bakery->getReputation());
        $now   = new \DateTimeImmutable();

        for ($i = 0; $i < $count; $i++) {
            $spawnAt = $now->modify('+' . ($i * Config::SPAWN_INTERVAL) . ' seconds');
            $failsAt = $spawnAt->modify('+' . Config::SECONDS_PER_ORDER . ' seconds');

            $order = $this->orderGeneratorService->generate($bakery->getReputation());
            $order->setSpawnAt($spawnAt);
            $order->setFailsAt($failsAt);
            $this->em->persist($order);
        }
    }
}
