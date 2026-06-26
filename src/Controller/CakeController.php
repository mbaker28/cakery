<?php

namespace App\Controller;

use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
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

    #[Route('/build', name: 'cake_build')]
    public function build(CakeOrder $order): Response
    {
        if ($order->getStatus() === OrderStatus::FULFILLED || $order->getStatus() === OrderStatus::FAILED) {
            return $this->redirectToRoute('game_index');
        }

        $cake = (new Cake())->setCakeOrder($order);
        $this->em->persist($cake);

        $order->setStatus(OrderStatus::IN_PROGRESS);

        $this->em->flush();

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
    }

    #[Route('/{cakeId}/edit', name: 'cake_edit', requirements: ['cakeId' => '\d+'])]
    public function edit(CakeOrder $order, int $cakeId): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
        $bakery = $this->bakeryRepository->findOneBy([]);

        return $this->render('cake/edit.html.twig', [
            'order'   => $order,
            'cake'    => $cake,
            'bakery'  => $bakery,
            'sizes'   => CakeSize::cases(),
            'flavors' => FrostingFlavor::cases(),
            'toppings' => Topping::cases(),
            'canBake' => $bakery && $this->inventoryService->canBake($cake, $bakery),
        ]);
    }

    #[Route('/{cakeId}/size', name: 'cake_set_size', methods: ['POST'])]
    public function setSize(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
        $size = CakeSize::tryFrom($request->request->getString('size'));

        if ($size !== null) {
            $cake->setSize($size);
            $this->em->flush();
        }

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cakeId]);
    }

    #[Route('/{cakeId}/frosting', name: 'cake_set_frosting', methods: ['POST'])]
    public function setFrosting(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
        $flavor = FrostingFlavor::tryFrom($request->request->getString('flavor'));

        if ($flavor !== null) {
            $cake->setFrostingFlavor($flavor);
            $this->em->flush();
        }

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cakeId]);
    }

    #[Route('/{cakeId}/layers', name: 'cake_set_layers', methods: ['POST'])]
    public function setLayers(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
        $layers = $request->request->getInt('layers');

        if ($layers >= 1 && $layers <= 5) {
            $cake->setLayers($layers);
            $this->em->flush();
        }

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cakeId]);
    }

    #[Route('/{cakeId}/topping', name: 'cake_toggle_topping', methods: ['POST'])]
    public function toggleTopping(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
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

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cakeId]);
    }

    #[Route('/{cakeId}/bake', name: 'cake_bake', methods: ['POST'])]
    public function bake(CakeOrder $order, int $cakeId): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null || !$this->inventoryService->canBake($cake, $bakery)) {
            return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cakeId]);
        }

        $this->inventoryService->deduct($cake, $bakery);
        $this->bakingService->bake($cake);
        $this->orderService->fulfill($order, $bakery);

        $this->em->flush();

        return $this->redirectToRoute('game_index');
    }

    private function getCakeOr404(CakeOrder $order, int $cakeId): Cake
    {
        $cake = $order->getCakes()->findFirst(fn($_, $c) => $c->getId() === $cakeId);

        if ($cake === null) {
            throw $this->createNotFoundException();
        }

        return $cake;
    }
}
