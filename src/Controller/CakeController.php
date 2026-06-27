<?php

namespace App\Controller;

use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\Ingredient;
use App\Enum\OrderStatus;
use App\Enum\Topping;
use App\Repository\BakeryRepository;
use App\Service\BakingService;
use App\Service\InventoryService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/order/{id}/cake', requirements: ['id' => '\d+'])]
class CakeController extends AbstractController
{
    public function __construct(
        private readonly BakeryRepository $bakeryRepository,
        private readonly BakingService $bakingService,
        private readonly InventoryService $inventoryService,
        private readonly OrderService $orderService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/build', name: 'cake_build', methods: ['POST'])]
    public function build(CakeOrder $order): Response
    {
        if ($order->getStatus() === OrderStatus::FULFILLED || $order->getStatus() === OrderStatus::FAILED) {
            return $this->redirectToRoute('game_index');
        }

        if ($order->getCake() !== null) {
            return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $order->getCake()->getId()]);
        }

        $cake = (new Cake())->setCakeOrder($order);
        $this->em->persist($cake);

        $order->setStatus(OrderStatus::IN_PROGRESS);

        $this->em->flush();

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
    }

    #[Route('/{cakeId}/edit', name: 'cake_edit', requirements: ['cakeId' => '\d+'], methods: ['GET'])]
    public function edit(CakeOrder $order, int $cakeId): Response
    {
        return $this->renderBuilder($order, $this->getCakeOr404($order, $cakeId), fullPage: true);
    }

    #[Route('/{cakeId}/size', name: 'cake_set_size', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function setSize(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
        $size = CakeSize::tryFrom($request->request->getString('size'));

        if ($size !== null) {
            $cake->setSize($size);
            $this->em->flush();
        }

        return $this->renderBuilder($order, $cake);
    }

    #[Route('/{cakeId}/frosting', name: 'cake_set_frosting', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function setFrosting(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake   = $this->getCakeOr404($order, $cakeId);
        $flavor = FrostingFlavor::tryFrom($request->request->getString('flavor'));

        if ($flavor !== null) {
            $cake->setFrostingFlavor($flavor);
            $this->em->flush();
        }

        return $this->renderBuilder($order, $cake);
    }

    #[Route('/{cakeId}/layers', name: 'cake_set_layers', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function setLayers(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake   = $this->getCakeOr404($order, $cakeId);
        $layers = $request->request->getInt('layers');

        if ($layers >= 1 && $layers <= 5) {
            $cake->setLayers($layers);
            $this->em->flush();
        }

        return $this->renderBuilder($order, $cake);
    }

    #[Route('/{cakeId}/topping', name: 'cake_toggle_topping', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function toggleTopping(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake    = $this->getCakeOr404($order, $cakeId);
        $topping = Topping::tryFrom($request->request->getString('topping'));

        if ($topping !== null) {
            $toppings = $cake->getToppings() ?? [];
            if (in_array($topping, $toppings, true)) {
                $toppings = array_values(array_filter($toppings, fn($t) => $t !== $topping));
            } else {
                $toppings[] = $topping;
            }
            $cake->setToppings($toppings ?: null);
            $this->em->flush();
        }

        return $this->renderBuilder($order, $cake);
    }

    #[Route('/{cakeId}/bake', name: 'cake_bake', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function bake(CakeOrder $order, int $cakeId): Response
    {
        $cake   = $this->getCakeOr404($order, $cakeId);
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null || !$this->inventoryService->canBake($cake, $bakery)) {
            return $this->renderBuilder($order, $cake);
        }

        $this->inventoryService->deduct($cake, $bakery);
        $this->bakingService->bake($cake);
        $earned = $this->orderService->fulfill($order, $bakery);

        $this->em->flush();

        if ($order->getStatus() === OrderStatus::FULFILLED) {
            $this->addFlash('success', sprintf(
                '%s loved it! You earned $%.2f.',
                $order->getCustomerName(),
                $earned
            ));
        } else {
            $this->addFlash('danger', sprintf(
                "%s's order failed — the cake quality was too low.",
                $order->getCustomerName()
            ));
        }

        return $this->redirectToRoute('game_index');
    }

    private function renderBuilder(CakeOrder $order, Cake $cake, bool $fullPage = false): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);
        $allItems = [...Ingredient::cases(), ...FrostingFlavor::cases(), ...Topping::cases()];
        $unitMap  = array_column(array_map(
            fn($item) => [$item->inventoryKey(), $item->unit()],
            $allItems
        ), 1, 0);

        $params = [
            'order'        => $order,
            'cake'         => $cake,
            'bakery'       => $bakery,
            'sizes'        => CakeSize::cases(),
            'flavors'      => FrostingFlavor::cases(),
            'toppings'     => Topping::cases(),
            'restockables' => $allItems,
            'unitMap'      => $unitMap,
            'canBake'      => $bakery && $this->inventoryService->canBake($cake, $bakery),
            'serverNow'    => (new \DateTimeImmutable())->getTimestamp(),
        ];

        try {
            $params['requirements'] = $this->inventoryService->getRequirements($cake);
        } catch (\LogicException) {
            $params['requirements'] = null;
        }

        $template = $fullPage ? 'cake/edit.html.twig' : 'cake/_builder.html.twig';

        return $this->render($template, $params);
    }

    private function getCakeOr404(CakeOrder $order, int $cakeId): Cake
    {
        $cake = $order->getCake();

        if ($cake === null || $cake->getId() !== $cakeId) {
            throw $this->createNotFoundException();
        }

        return $cake;
    }
}
