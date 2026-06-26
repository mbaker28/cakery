<?php

namespace App\Repository;

use App\Entity\CakeOrder;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CakeOrder>
 */
class CakeOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CakeOrder::class);
    }

    /** @return CakeOrder[] */
    public function findActiveOrders(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', [OrderStatus::PENDING, OrderStatus::IN_PROGRESS])
            ->orderBy('o.dueDay', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Orders that must be fulfilled before the day can advance (due today or overdue). */
    /** @return CakeOrder[] */
    public function findBlockingOrders(int $currentDay): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.dueDay <= :day')
            ->setParameter('statuses', [OrderStatus::PENDING, OrderStatus::IN_PROGRESS])
            ->setParameter('day', $currentDay)
            ->orderBy('o.dueDay', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
