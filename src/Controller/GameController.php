<?php

namespace App\Controller;

use App\Entity\Bakery;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\Ingredient;
use App\Enum\OrderStatus;
use App\Repository\BakeryRepository;
use App\Repository\CakeOrderRepository;
use App\Service\InventoryService;
use App\Service\OrderGeneratorService;
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

        $blockingOrders = $this->cakeOrderRepository->findBlockingOrders($bakery->getDay());

        if ($this->isGameOver($blockingOrders, $bakery)) {
            return $this->redirectToRoute('game_over');
        }

        $orders = $this->cakeOrderRepository->findActiveOrders();

        return $this->render('game/index.html.twig', [
            'bakery'          => $bakery,
            'orders'          => $orders,
            'ingredients'     => Ingredient::cases(),
            'blockingOrders'  => $blockingOrders,
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

        $bakery = (new Bakery())
            ->setName($request->request->getString('name', 'My Bakery'))
            ->setMoney(50.0)
            ->setReputation(20)
            ->setDay(1)
            ->setOrdersCompleted(0)
            ->setOrdersFailed(0);

        $this->em->persist($bakery);

        for ($i = 0; $i < 3; $i++) {
            $order = $this->orderGeneratorService->generate($bakery->getReputation(), $bakery->getDay());
            $this->em->persist($order);
        }

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

        $blockingOrders = $this->cakeOrderRepository->findBlockingOrders($bakery->getDay());

        if (!empty($blockingOrders)) {
            if ($this->isGameOver($blockingOrders, $bakery)) {
                return $this->redirectToRoute('game_over');
            }

            $this->addFlash('danger', 'You must fulfill all orders due today before advancing.');
            return $this->redirectToRoute('game_index');
        }

        $bakery->setDay($bakery->getDay() + 1);

        // Top up to max orders
        $activeCount = count($this->cakeOrderRepository->findActiveOrders());
        while ($this->orderGeneratorService->canGenerate($activeCount)) {
            $order = $this->orderGeneratorService->generate($bakery->getReputation(), $bakery->getDay());
            $this->em->persist($order);
            $activeCount++;
        }

        $this->em->flush();

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
            ->setLayers($order->getRequiredLayers());

        if ($this->inventoryService->canBake($fakeCake, $bakery)) {
            return true;
        }

        // Check if the player can afford to buy the ingredient deficit
        $requirements = $this->inventoryService->getRequirements($fakeCake);
        $inventory    = $bakery->getInventory();
        $deficit      = 0.0;

        foreach ($requirements as $ingredient => $needed) {
            $shortfall = max(0, $needed - ($inventory[$ingredient] ?? 0));
            if ($shortfall > 0) {
                $deficit += $shortfall * Ingredient::from($ingredient)->costPerUnit();
            }
        }

        return $bakery->getMoney() >= $deficit;
    }

    #[Route('/shop/restock', name: 'game_restock', methods: ['POST'])]
    public function restock(Request $request): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        $ingredient = Ingredient::tryFrom($request->request->getString('ingredient'));
        $quantity   = max(1, $request->request->getInt('quantity', 5));
        $restocked  = false;

        if ($ingredient !== null) {
            try {
                $this->inventoryService->restock($ingredient, $quantity, $bakery);
                $this->em->flush();
                $restocked = true;
            } catch (\RuntimeException) {
                // Not enough money — silently ignore, UI already shows cost
            }
        }

        $params = [
            'bakery'          => $bakery,
            'ingredients'     => Ingredient::cases(),
            'toastIngredient' => $restocked ? $ingredient : null,
            'toastQuantity'   => $quantity,
            'builderContext'  => null,
        ];

        $orderId = $request->request->getInt('order_id');
        $cakeId  = $request->request->getInt('cake_id');

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
