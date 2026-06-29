<?php

namespace App\Controller;

use App\Config;
use App\Entity\Bakery;
use App\Entity\CakeOrder;
use App\Enum\GamePhase;
use App\Enum\OrderStatus;
use App\Repository\BakeryRepository;
use App\Repository\CakeOrderRepository;
use App\Service\OrderGeneratorService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    public function __construct(
        private readonly BakeryRepository $bakeryRepository,
        private readonly CakeOrderRepository $cakeOrderRepository,
        private readonly OrderGeneratorService $orderGeneratorService,
        private readonly OrderService $orderService,
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
        $this->recordDayStart($bakery);
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

        return $this->redirectToRoute('game_day_summary');
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

        return $this->redirectToRoute('game_day_summary');
    }

    #[Route('/day-summary', name: 'game_day_summary', methods: ['GET'])]
    public function daySummary(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        if ($bakery->getPhase() === GamePhase::DAY) {
            return $this->redirectToRoute('game_index');
        }

        $total     = $bakery->getDayTotalOrders() ?? 0;
        $completed = $bakery->getOrdersCompleted() - ($bakery->getDayStartOrdersCompleted() ?? 0);
        $failed    = $bakery->getOrdersFailed()    - ($bakery->getDayStartOrdersFailed()    ?? 0);
        $earned    = $bakery->getMoney()            - ($bakery->getDayStartMoney()           ?? $bakery->getMoney());
        $repChange = $bakery->getReputation()       - ($bakery->getDayStartReputation()      ?? $bakery->getReputation());

        $rating = match(true) {
            $total > 0 && $completed === $total          => 'perfect',
            $total > 0 && $completed / $total >= 0.75   => 'great',
            $total > 0 && $completed / $total >= 0.4    => 'decent',
            default                                      => 'rough',
        };

        return $this->render('game/day_summary.html.twig', [
            'bakery'    => $bakery,
            'total'     => $total,
            'completed' => $completed,
            'failed'    => $failed,
            'earned'    => $earned,
            'repChange' => $repChange,
            'rating'    => $rating,
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
        $this->recordDayStart($bakery);
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

    private function recordDayStart(Bakery $bakery): void
    {
        $bakery->setDayStartMoney($bakery->getMoney());
        $bakery->setDayStartReputation($bakery->getReputation());
        $bakery->setDayStartOrdersCompleted($bakery->getOrdersCompleted());
        $bakery->setDayStartOrdersFailed($bakery->getOrdersFailed());
        $bakery->setDayTotalOrders(Config::ordersForDay($bakery->getReputation()));
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
