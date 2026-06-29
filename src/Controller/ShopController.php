<?php

namespace App\Controller;

use App\Config;
use App\Enum\FrostingFlavor;
use App\Enum\GamePhase;
use App\Enum\Ingredient;
use App\Enum\Topping;
use App\Enum\Upgrade;
use App\Repository\BakeryRepository;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

class ShopController extends AbstractController
{
    public function __construct(
        private readonly BakeryRepository $bakeryRepository,
        private readonly InventoryService $inventoryService,
        private readonly EntityManagerInterface $em,
    ) {}

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
            'upgrades'      => Upgrade::cases(),
            'nextDayOrders' => Config::ordersForDay($bakery->getReputation()),
        ]);
    }

    #[Route('/shop/upgrade', name: 'game_purchase_upgrade', methods: ['POST'])]
    public function purchaseUpgrade(Request $request): Response
    {
        $bakery = $this->bakeryRepository->findOneBy([]);

        if ($bakery === null || $bakery->getPhase() !== GamePhase::SHOP) {
            return $this->redirectToRoute('game_index');
        }

        $upgrade = Upgrade::tryFrom($request->request->getString('upgrade'));

        if ($upgrade === null) {
            return $this->redirectToRoute('game_shop');
        }

        $currentLevel = $bakery->getUpgradeLevel($upgrade);
        $nextLevel    = $currentLevel + 1;

        if ($nextLevel > $upgrade->maxLevel()) {
            return $this->redirectToRoute('game_shop');
        }

        $cost = $upgrade->costForLevel($nextLevel);

        if ($bakery->getMoney() < $cost) {
            $this->addFlash('danger', 'Not enough money.');
            return $this->redirectToRoute('game_shop');
        }

        $bakery->setMoney($bakery->getMoney() - $cost);
        $bakery->setUpgradeLevel($upgrade, $nextLevel);
        $this->em->flush();

        $this->addFlash('success', sprintf('%s upgraded!', $upgrade->label()));

        return $this->redirectToRoute('game_shop');
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
}
