<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\CakeOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CakeOrderRepository::class)]
class CakeOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: OrderStatus::class)]
    private ?OrderStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column]
    private ?float $payout = null;

    #[ORM\Column]
    private ?int $happinessBonus = null;

    /**
     * @var Collection<int, Cake>
     */
    #[ORM\OneToMany(targetEntity: Cake::class, mappedBy: 'cakeOrder', orphanRemoval: true)]
    private Collection $cakes;

    #[ORM\Column(length: 255)]
    private ?string $customerName = null;

    #[ORM\Column(length: 255)]
    private ?string $avatar = null;

    public function __construct()
    {
        $this->cakes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getStatus(): ?OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getPayout(): ?float
    {
        return $this->payout;
    }

    public function setPayout(float $payout): static
    {
        $this->payout = $payout;

        return $this;
    }

    public function getHappinessBonus(): ?int
    {
        return $this->happinessBonus;
    }

    public function setHappinessBonus(int $happinessBonus): static
    {
        $this->happinessBonus = $happinessBonus;

        return $this;
    }

    /**
     * @return Collection<int, Cake>
     */
    public function getCakes(): Collection
    {
        return $this->cakes;
    }

    public function addCake(Cake $cake): static
    {
        if (!$this->cakes->contains($cake)) {
            $this->cakes->add($cake);
            $cake->setCakeOrder($this);
        }

        return $this;
    }

    public function removeCake(Cake $cake): static
    {
        if ($this->cakes->removeElement($cake)) {
            // set the owning side to null (unless already changed)
            if ($cake->getCakeOrder() === $this) {
                $cake->setCakeOrder(null);
            }
        }

        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }
}
