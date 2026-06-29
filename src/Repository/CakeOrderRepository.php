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
            ->orderBy('o.spawnAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Orders visible to the player (already spawned) that are still pending/in-progress. */
    /** @return CakeOrder[] */
    public function findBlockingOrders(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.spawnAt <= :now')
            ->setParameter('statuses', [OrderStatus::PENDING, OrderStatus::IN_PROGRESS])
            ->setParameter('now', $now)
            ->orderBy('o.spawnAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Orders that spawned within a time window (for doorbell notifications). */
    /** @return CakeOrder[] */
    public function findOrdersSpawnedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.spawnAt > :from')
            ->andWhere('o.spawnAt <= :to')
            ->andWhere('o.status != :failed')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('failed', OrderStatus::FAILED)
            ->getQuery()
            ->getResult();
    }

    /** Orders not yet revealed to the player. */
    /** @return CakeOrder[] */
    public function findUnspawned(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->andWhere('o.spawnAt > :now')
            ->setParameter('status', OrderStatus::PENDING)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}