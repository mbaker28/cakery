<?php

namespace App\Entity;

use App\Enum\CakeFlavor;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\OrderStatus;
use App\Enum\Topping;
use App\Repository\CakeOrderRepository;
use Doctrine\DBAL\Types\Types;
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
    private ?\DateTimeImmutable $spawnAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $failsAt = null;

    #[ORM\Column]
    private ?float $payout = null;

    #[ORM\Column]
    private ?int $happinessBonus = null;

    #[ORM\OneToOne(targetEntity: Cake::class, mappedBy: 'cakeOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Cake $cake = null;

    #[ORM\Column(length: 255)]
    private ?string $customerName = null;

    #[ORM\Column(length: 255)]
    private ?string $avatar = null;

    #[ORM\Column(enumType: CakeFlavor::class)]
    private ?CakeFlavor $requiredFlavor = null;

    #[ORM\Column(enumType: CakeSize::class)]
    private ?CakeSize $requiredSize = null;

    #[ORM\Column(enumType: FrostingFlavor::class)]
    private ?FrostingFlavor $requiredFrostingFlavor = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true, enumType: Topping::class)]
    private ?array $requiredToppings = null;

    #[ORM\Column]
    private ?int $requiredLayers = null;

    #[ORM\Column]
    private bool $isVip = false;

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

    public function getSpawnAt(): ?\DateTimeImmutable
    {
        return $this->spawnAt;
    }

    public function setSpawnAt(\DateTimeImmutable $spawnAt): static
    {
        $this->spawnAt = $spawnAt;

        return $this;
    }

    public function getFailsAt(): ?\DateTimeImmutable
    {
        return $this->failsAt;
    }

    public function setFailsAt(?\DateTimeImmutable $failsAt): static
    {
        $this->failsAt = $failsAt;

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

    public function getCake(): ?Cake
    {
        return $this->cake;
    }

    public function setCake(?Cake $cake): static
    {
        $this->cake = $cake;
        if ($cake !== null && $cake->getCakeOrder() !== $this) {
            $cake->setCakeOrder($this);
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

    public function getRequiredFlavor(): ?CakeFlavor
    {
        return $this->requiredFlavor;
    }

    public function setRequiredFlavor(CakeFlavor $requiredFlavor): static
    {
        $this->requiredFlavor = $requiredFlavor;

        return $this;
    }

    public function getRequiredSize(): ?CakeSize
    {
        return $this->requiredSize;
    }

    public function setRequiredSize(CakeSize $requiredSize): static
    {
        $this->requiredSize = $requiredSize;

        return $this;
    }

    public function getRequiredFrostingFlavor(): ?FrostingFlavor
    {
        return $this->requiredFrostingFlavor;
    }

    public function setRequiredFrostingFlavor(FrostingFlavor $requiredFrostingFlavor): static
    {
        $this->requiredFrostingFlavor = $requiredFrostingFlavor;

        return $this;
    }

    /**
     * @return Topping[]|null
     */
    public function getRequiredToppings(): ?array
    {
        return $this->requiredToppings;
    }

    public function setRequiredToppings(?array $requiredToppings): static
    {
        $this->requiredToppings = $requiredToppings;

        return $this;
    }

    public function getRequiredLayers(): ?int
    {
        return $this->requiredLayers;
    }

    public function setRequiredLayers(int $requiredLayers): static
    {
        $this->requiredLayers = $requiredLayers;

        return $this;
    }

    public function isVip(): bool
    {
        return $this->isVip;
    }

    public function setIsVip(bool $isVip): static
    {
        $this->isVip = $isVip;

        return $this;
    }
}
