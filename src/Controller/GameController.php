<?php

namespace App\Controller;

use App\Entity\Bakery;
use App\Repository\BakeryRepository;
use App\Repository\CakeOrderRepository;
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
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'game_index')]
    public function index(): Response
    {
        $bakery = $this->bakeryRepository->find(1);

        if ($bakery === null) {
            return $this->redirectToRoute('game_new');
        }

        $orders = $this->cakeOrderRepository->findActiveOrders();

        return $this->render('game/index.html.twig', [
            'bakery' => $bakery,
            'orders' => $orders,
        ]);
    }

    #[Route('/new', name: 'game_new')]
    public function new(): Response
    {
        if ($this->bakeryRepository->find(1) !== null) {
            return $this->redirectToRoute('game_index');
        }

        return $this->render('game/new.html.twig');
    }

    #[Route('/new', name: 'game_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if ($this->bakeryRepository->find(1) !== null) {
            return $this->redirectToRoute('game_index');
        }

        $bakery = (new Bakery())
            ->setName($request->request->getString('name', 'My Bakery'))
            ->setMoney(200.0)
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
}
