<?php

namespace App\Entity;

use App\Enum\FrostingFlavor;
use App\Enum\Ingredient;
use App\Enum\Restockable;
use App\Enum\Topping;
use App\Repository\BakeryRepository;
use Doctrine\ORM\Mapping as ORM;

// TODO: possibly implement unlocking toppings
#[ORM\Entity(repositoryClass: BakeryRepository::class)]
class Bakery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $money = null;

    #[ORM\Column]
    private ?int $reputation = null;

    #[ORM\Column]
    private ?int $day = null;

    #[ORM\Column]
    private ?int $ordersCompleted = null;

    #[ORM\Column]
    private ?int $ordersFailed = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dayEndsAt = null;

    /**
     * @var array<string, int> $inventory
     */
    #[ORM\Column]
    private array $inventory = [
        Ingredient::FLOUR->value  => 2,
        Ingredient::BUTTER->value => 2,
        Ingredient::EGGS->value   => 2,
        Ingredient::SUGAR->value  => 2,
        Ingredient::MILK->value   => 2,
        FrostingFlavor::FROSTING_CHOCOLATE->value    => 2,
        FrostingFlavor::FROSTING_VANILLA->value      => 2,
        FrostingFlavor::FROSTING_CREAM_CHEESE->value => 2,
        Topping::TOPPING_SPRINKLES->value        => 2,
        Topping::TOPPING_CHOCOLATE_CHIPS->value  => 2,
        Topping::TOPPING_STRAWBERRIES->value     => 2,
    ];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMoney(): ?float
    {
        return $this->money;
    }

    public function setMoney(float $money): static
    {
        $this->money = $money;

        return $this;
    }

    public function getReputation(): ?int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): static
    {
        $this->reputation = $reputation;

        return $this;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function setDay(int $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getOrdersCompleted(): ?int
    {
        return $this->ordersCompleted;
    }

    public function setOrdersCompleted(int $ordersCompleted): static
    {
        $this->ordersCompleted = $ordersCompleted;

        return $this;
    }

    public function getOrdersFailed(): ?int
    {
        return $this->ordersFailed;
    }

    public function setOrdersFailed(int $ordersFailed): static
    {
        $this->ordersFailed = $ordersFailed;

        return $this;
    }

    public function getInventory(): array
    {
        return $this->inventory;
    }

    public function setInventory(array $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getDayEndsAt(): ?\DateTimeImmutable
    {
        return $this->dayEndsAt;
    }

    public function setDayEndsAt(?\DateTimeImmutable $dayEndsAt): static
    {
        $this->dayEndsAt = $dayEndsAt;

        return $this;
    }

    public function getStock(Restockable $item): int
    {
        return $this->inventory[$item->inventoryKey()];
    }

    public function setStock(Restockable $item, int $amount): static
    {
        $this->inventory[$item->inventoryKey()] = $amount;

        return $this;
    }
}
