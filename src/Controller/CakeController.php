<?php

namespace App\Controller;

use App\Config;
use App\Entity\Cake;
use App\Entity\CakeOrder;
use App\Enum\CakeBuildPhase;
use App\Enum\CakeFlavor;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\Ingredient;
use App\Enum\OrderStatus;
use App\Enum\Topping;
use App\Enum\Upgrade;
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

    #[Route('/{cakeId}/flavor', name: 'cake_set_flavor', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function setFlavor(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::MIXING) {
            return $this->renderBuilder($order, $cake);
        }

        $flavor = CakeFlavor::tryFrom($request->request->getString('flavor'));

        if ($flavor !== null) {
            $cake->setFlavor($flavor);
            $this->em->flush();
        }

        return $this->renderBuilder($order, $cake);
    }

    #[Route('/{cakeId}/size', name: 'cake_set_size', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function setSize(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::MIXING) {
            return $this->renderBuilder($order, $cake);
        }

        $size = CakeSize::tryFrom($request->request->getString('size'));

        if ($size !== null) {
            $cake->setSize($size);
            if ($size === CakeSize::CUPCAKE) {
                $cake->setLayers(1);
            }
            $this->em->flush();
        }

        return $this->renderBuilder($order, $cake);
    }

    #[Route('/{cakeId}/frosting', name: 'cake_set_frosting', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function setFrosting(CakeOrder $order, int $cakeId, Request $request): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::DECORATING) {
            return $this->renderBuilder($order, $cake);
        }

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
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::MIXING) {
            return $this->renderBuilder($order, $cake);
        }

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
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::DECORATING) {
            return $this->renderBuilder($order, $cake);
        }

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

    #[Route('/{cakeId}/start-baking', name: 'cake_start_baking', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function startBaking(CakeOrder $order, int $cakeId): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::MIXING
            || $cake->getFlavor() === null
            || $cake->getSize() === null
            || $cake->getLayers() === null
        ) {
            return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
        }

        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null || !$this->inventoryService->canBake($cake, $bakery)) {
            return $this->renderBuilder($order, $cake, fullPage: true);
        }

        $this->inventoryService->deduct($cake, $bakery);
        $cake->setBuildPhase(CakeBuildPhase::BAKING);
        $cake->setBakingStartedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
    }

    #[Route('/{cakeId}/done-baking', name: 'cake_done_baking', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function doneBaking(CakeOrder $order, int $cakeId): Response
    {
        $cake = $this->getCakeOr404($order, $cakeId);

        if ($cake->getBuildPhase() !== CakeBuildPhase::BAKING) {
            return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
        }

        $bakery        = $this->bakeryRepository->findOneBy([]);
        $bakingSeconds = Config::bakingSecondsForLevel($bakery?->getUpgradeLevel(Upgrade::FASTER_OVEN) ?? 0);
        $startedAt     = $cake->getBakingStartedAt();
        $elapsed       = $startedAt ? (new \DateTimeImmutable())->getTimestamp() - $startedAt->getTimestamp() : 0;

        if ($elapsed < $bakingSeconds) {
            return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
        }

        $this->bakingService->bake($cake);
        $cake->setBuildPhase(CakeBuildPhase::DECORATING);
        $this->em->flush();

        return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
    }

    #[Route('/{cakeId}/submit', name: 'cake_submit', requirements: ['cakeId' => '\d+'], methods: ['POST'])]
    public function submit(CakeOrder $order, int $cakeId): Response
    {
        $cake   = $this->getCakeOr404($order, $cakeId);
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($cake->getBuildPhase() !== CakeBuildPhase::DECORATING) {
            return $this->redirectToRoute('cake_edit', ['id' => $order->getId(), 'cakeId' => $cake->getId()]);
        }

        if ($bakery === null) {
            return $this->renderBuilder($order, $cake, fullPage: true);
        }

        $result = $this->orderService->fulfill($order, $bakery);

        $this->em->flush();

        if ($order->getStatus() === OrderStatus::FULFILLED) {
            $name    = $order->getCustomerName();
            $earned  = $result['earned'];
            $quality = $result['quality'];
            $excited = $result['excited'];

            $reaction = match(true) {
                $excited && $quality === 100.0 => "$name was thrilled!",
                $excited && $quality >= 85.0   => "$name loved it!",
                $excited && $quality >= 70.0   => "$name was happy with it.",
                $excited                       => "$name thought it was alright.",
                $quality >= 85.0               => "$name liked it, but wished it came sooner.",
                default                        => "$name was a bit disappointed by the wait.",
            };

            $this->addFlash('success', sprintf('%s You earned $%.2f.', $reaction, $earned));
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
        $bakery  = $this->bakeryRepository->findOneBy([]);
        $unitMap = array_column(array_map(
            fn($item) => [$item->inventoryKey(), $item->unit()],
            Ingredient::cases()
        ), 1, 0);

        $params = [
            'order'          => $order,
            'cake'           => $cake,
            'bakery'         => $bakery,
            'cakeFlavors'    => CakeFlavor::cases(),
            'sizes'          => CakeSize::cases(),
            'flavors'        => array_values(array_filter(
                FrostingFlavor::cases(),
                fn($f) => $f->requiredRecipeLevel() <= ($bakery?->getUpgradeLevel(Upgrade::RECIPE_BOOK) ?? 0)
            )),
            'toppings'       => array_values(array_filter(
                Topping::cases(),
                fn($t) => $t->requiredRecipeLevel() <= ($bakery?->getUpgradeLevel(Upgrade::RECIPE_BOOK) ?? 0)
            )),
            'unitMap'        => $unitMap,
            'canBake'        => $bakery && $this->inventoryService->canBake($cake, $bakery),
            'serverNow'      => (new \DateTimeImmutable())->getTimestamp(),
            'bakingDuration' => Config::bakingSecondsForLevel($bakery?->getUpgradeLevel(Upgrade::FASTER_OVEN) ?? 0),
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
