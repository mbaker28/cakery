<?php

namespace App\Controller;

use App\Config;
use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\FrostingFlavor;
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

        // If the day timer has expired (player was away / tab was closed), lock orders now
        if ($this->isDayExpired($bakery)) {
            $this->failDayOrders($bakery);
            $this->em->flush();
        }

        // Ensure day timer is set for new/migrated games
        if ($bakery->getDayEndsAt() === null) {
            $bakery->setDayEndsAt($this->newDayEndsAt());
            $this->em->flush();
        }

        $now        = new \DateTimeImmutable();
        $dayExpired = $this->isDayExpired($bakery);

        $blockingOrders = $dayExpired
            ? []
            : $this->cakeOrderRepository->findBlockingOrders($now);

        if ($this->isGameOver($blockingOrders, $bakery)) {
            return $this->redirectToRoute('game_over');
        }

        $orders = $this->cakeOrderRepository->findActiveOrders();

        return $this->render('game/index.html.twig', [
            'bakery'         => $bakery,
            'orders'         => $orders,
            'restockables'   => [...Ingredient::cases(), ...FrostingFlavor::cases(), ...Topping::cases()],
            'blockingOrders' => $blockingOrders,
            'dayExpired'     => $dayExpired,
            'dayEndsAt'      => $bakery->getDayEndsAt()->getTimestamp(),
            'secondsPerDay'  => Config::SECONDS_PER_DAY,
            'serverNow'      => $now->getTimestamp(),
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

        $dayEndsAt = $this->newDayEndsAt();

        $bakery = (new Bakery())
            ->setName($request->request->getString('name', 'My Bakery'))
            ->setMoney(50.0)
            ->setReputation(20)
            ->setDay(1)
            ->setOrdersCompleted(0)
            ->setOrdersFailed(0)
            ->setDayEndsAt($dayEndsAt);

        $this->em->persist($bakery);
        $this->spawnOrdersForDay($bakery, $dayEndsAt);
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

        $now = new \DateTimeImmutable();

        // Day already expired — orders are already failed; just start the new day
        if ($this->isDayExpired($bakery)) {
            $this->failDayOrders($bakery);
            $this->startNewDay($bakery);
            $this->em->flush();

            $blockingOrders = $this->cakeOrderRepository->findBlockingOrders($now);
            if ($this->isGameOver($blockingOrders, $bakery)) {
                return $this->redirectToRoute('game_over');
            }

            return $this->redirectToRoute('game_index');
        }

        $blockingOrders = $this->cakeOrderRepository->findBlockingOrders($now);

        if (!empty($blockingOrders)) {
            if ($this->isGameOver($blockingOrders, $bakery)) {
                return $this->redirectToRoute('game_over');
            }

            $this->addFlash('danger', 'You must fulfil all visible orders before advancing the day.');
            return $this->redirectToRoute('game_index');
        }

        // Remove unspawned orders silently (player advanced early; no penalty)
        foreach ($this->cakeOrderRepository->findUnspawned($now) as $order) {
            $this->em->remove($order);
        }

        $this->startNewDay($bakery);
        $this->em->flush();

        return $this->redirectToRoute('game_index');
    }

    #[Route('/day-expired', name: 'game_day_expired', methods: ['POST'])]
    public function dayExpired(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        if ($this->isDayExpired($bakery)) {
            $this->failDayOrders($bakery);
            $this->em->flush();
        }

        $this->addFlash('danger', "⏰ Time's up! The day is over.");

        return $this->redirectToRoute('game_index');
    }

    #[Route('/game-over', name: 'game_over', methods: ['GET'])]
    public function gameOver(): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        return $this->render('game/game_over.html.twig', [
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

    /** @param CakeOrder[] $blockingOrders */
    private function isGameOver(array $blockingOrders, Bakery $bakery): bool
    {
        if (empty($blockingOrders)) {
            return false;
        }

        foreach ($blockingOrders as $order) {
            if ($this->canFulfillOrder($order, $bakery)) {
                return false;
            }
        }

        return true;
    }

    private function canFulfillOrder(CakeOrder $order, Bakery $bakery): bool
    {
        $fakeCake = (new Cake())
            ->setSize($order->getRequiredSize())
            ->setLayers($order->getRequiredLayers())
            ->setFrostingFlavor($order->getRequiredFrostingFlavor())
            ->setToppings($order->getRequiredToppings() ?? []);

        if ($this->inventoryService->canBake($fakeCake, $bakery)) {
            return true;
        }

        $requirements = $this->inventoryService->getRequirements($fakeCake);
        $inventory    = $bakery->getInventory();
        $deficit      = 0.0;

        foreach ($requirements as $key => $needed) {
            $shortfall = max(0, $needed - ($inventory[$key] ?? 0));
            if ($shortfall > 0) {
                $deficit += $shortfall * $this->costForKey($key);
            }
        }

        return $bakery->getMoney() >= $deficit;
    }

    private function costForKey(string $key): float
    {
        $item = Ingredient::tryFrom($key) ?? FrostingFlavor::tryFrom($key) ?? Topping::tryFrom($key);

        return $item?->costPerUnit() ?? 0.0;
    }

    private function isDayExpired(Bakery $bakery): bool
    {
        return $bakery->getDayEndsAt() !== null
            && $bakery->getDayEndsAt() <= new \DateTimeImmutable();
    }

    private function failDayOrders(Bakery $bakery): void
    {
        foreach ($this->cakeOrderRepository->findActiveOrders() as $order) {
            $this->orderService->fail($order, $bakery);
        }
    }

    private function startNewDay(Bakery $bakery): void
    {
        $bakery->setDay($bakery->getDay() + 1);
        $dayEndsAt = $this->newDayEndsAt();
        $bakery->setDayEndsAt($dayEndsAt);
        $this->spawnOrdersForDay($bakery, $dayEndsAt);
    }

    private function spawnOrdersForDay(Bakery $bakery, \DateTimeImmutable $dayEndsAt): void
    {
        $dayStartedAt = $dayEndsAt->modify('-' . Config::SECONDS_PER_DAY . ' seconds');

        foreach (Config::SPAWN_DELAYS as $delay) {
            $order = $this->orderGeneratorService->generate($bakery->getReputation());
            $order->setSpawnAt($dayStartedAt->modify('+' . $delay . ' seconds'));
            $this->em->persist($order);
        }
    }

    private function newDayEndsAt(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->modify('+' . Config::SECONDS_PER_DAY . ' seconds');
    }

    #[Route('/shop/restock', name: 'game_restock', methods: ['POST'])]
    public function restock(Request $request): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        $value     = $request->request->getString('ingredient');
        $item      = Ingredient::tryFrom($value) ?? FrostingFlavor::tryFrom($value) ?? Topping::tryFrom($value);
        $quantity  = max(1, $request->request->getInt('quantity', 5));
        $restocked = false;

        if ($item !== null) {
            try {
                $this->inventoryService->restock($item, $quantity, $bakery);
                $this->em->flush();
                $restocked = true;
            } catch (\RuntimeException) {
                // Not enough money — silently ignore, UI already shows cost
            }
        }

        $params = [
            'bakery'          => $bakery,
            'restockables'    => [...Ingredient::cases(), ...FrostingFlavor::cases(), ...Topping::cases()],
            'toastIngredient' => $restocked ? $item : null,
            'toastQuantity'   => $quantity,
            'builderContext'  => null,
            'orderId'         => null,
            'cakeId'          => null,
        ];

        $orderId = $request->request->getInt('order_id') ?: null;
        $cakeId  = $request->request->getInt('cake_id') ?: null;

        $params['orderId'] = $orderId;
        $params['cakeId']  = $cakeId;

        if ($restocked && $orderId && $cakeId) {
            $order = $this->cakeOrderRepository->find($orderId);
            $cake  = $order?->getCake();
            if ($cake !== null && $cake->getId() === $cakeId) {
                try {
                    $requirements = $this->inventoryService->getRequirements($cake);
                } catch (\LogicException) {
                    $requirements = null;
                }
                $params['builderContext'] = [
                    'order'        => $order,
                    'cake'         => $cake,
                    'bakery'       => $bakery,
                    'requirements' => $requirements,
                    'canBake'      => $this->inventoryService->canBake($cake, $bakery),
                ];
            }
        }

        return new \Symfony\Component\HttpFoundation\Response(
            $this->renderView('game/_restock.stream.html.twig', $params),
            200,
            ['Content-Type' => 'text/vnd.turbo-stream.html; charset=utf-8']
        );
    }
}
