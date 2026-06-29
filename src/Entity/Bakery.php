<?php

namespace App\Entity;

use App\Enum\FrostingFlavor;
use App\Enum\GamePhase;
use App\Enum\Ingredient;
use App\Enum\Restockable;
use App\Enum\Topping;
use App\Enum\Upgrade;
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

    #[ORM\Column]
    private int $maxDays = 7;

    #[ORM\Column(enumType: GamePhase::class, length: 10)]
    private GamePhase $phase = GamePhase::DAY;

    #[ORM\Column(nullable: true)]
    private ?float $dayStartMoney = null;

    #[ORM\Column(nullable: true)]
    private ?int $dayStartReputation = null;

    #[ORM\Column(nullable: true)]
    private ?int $dayStartOrdersCompleted = null;

    #[ORM\Column(nullable: true)]
    private ?int $dayStartOrdersFailed = null;

    #[ORM\Column(nullable: true)]
    private ?int $dayTotalOrders = null;

    /**
     * @var array<string, int> $upgrades
     */
    #[ORM\Column(type: 'json')]
    private array $upgrades = [];

    /**
     * @var array<string, int> $inventory
     */
    #[ORM\Column]
    private array $inventory = [
        Ingredient::FLOUR->value  => 5.0,  // bags
        Ingredient::BUTTER->value => 3.0,  // sticks
        Ingredient::EGGS->value   => 24.0, // eggs
        Ingredient::SUGAR->value  => 4.0,  // bags
        Ingredient::MILK->value   => 3.0,  // gallons
        FrostingFlavor::FROSTING_CHOCOLATE->value    => 3,
        FrostingFlavor::FROSTING_VANILLA->value      => 3,
        FrostingFlavor::FROSTING_CREAM_CHEESE->value => 3,
        Topping::TOPPING_SPRINKLES->value        => 3,
        Topping::TOPPING_CHOCOLATE_CHIPS->value  => 3,
        Topping::TOPPING_STRAWBERRIES->value     => 3,
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

    public function getMaxDays(): int
    {
        return $this->maxDays;
    }

    public function setMaxDays(int $maxDays): static
    {
        $this->maxDays = $maxDays;

        return $this;
    }

    public function getPhase(): GamePhase
    {
        return $this->phase;
    }

    public function setPhase(GamePhase $phase): static
    {
        $this->phase = $phase;

        return $this;
    }

    public function getDayStartMoney(): ?float
    {
        return $this->dayStartMoney;
    }

    public function setDayStartMoney(?float $dayStartMoney): static
    {
        $this->dayStartMoney = $dayStartMoney;

        return $this;
    }

    public function getDayStartReputation(): ?int
    {
        return $this->dayStartReputation;
    }

    public function setDayStartReputation(?int $dayStartReputation): static
    {
        $this->dayStartReputation = $dayStartReputation;

        return $this;
    }

    public function getDayStartOrdersCompleted(): ?int
    {
        return $this->dayStartOrdersCompleted;
    }

    public function setDayStartOrdersCompleted(?int $dayStartOrdersCompleted): static
    {
        $this->dayStartOrdersCompleted = $dayStartOrdersCompleted;

        return $this;
    }

    public function getDayStartOrdersFailed(): ?int
    {
        return $this->dayStartOrdersFailed;
    }

    public function setDayStartOrdersFailed(?int $dayStartOrdersFailed): static
    {
        $this->dayStartOrdersFailed = $dayStartOrdersFailed;

        return $this;
    }

    public function getDayTotalOrders(): ?int
    {
        return $this->dayTotalOrders;
    }

    public function setDayTotalOrders(?int $dayTotalOrders): static
    {
        $this->dayTotalOrders = $dayTotalOrders;

        return $this;
    }

    public function getUpgradeLevel(Upgrade $upgrade): int
    {
        return $this->upgrades[$upgrade->value] ?? 0;
    }

    public function hasUpgrade(Upgrade $upgrade): bool
    {
        return $this->getUpgradeLevel($upgrade) > 0;
    }

    public function setUpgradeLevel(Upgrade $upgrade, int $level): static
    {
        $this->upgrades[$upgrade->value] = $level;

        return $this;
    }

    public function getStock(Restockable $item): float
    {
        return $this->inventory[$item->inventoryKey()] ?? 0.0;
    }

    public function setStock(Restockable $item, float $amount): static
    {
        $this->inventory[$item->inventoryKey()] = $amount;

        return $this;
    }
}
