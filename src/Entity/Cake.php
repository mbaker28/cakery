<?php

namespace App\Entity;

use App\Enum\CakeBuildPhase;
use App\Enum\CakeFlavor;
use App\Enum\CakeSize;
use App\Enum\FrostingFlavor;
use App\Enum\Topping;
use App\Repository\CakeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CakeRepository::class)]
class Cake
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: CakeFlavor::class, nullable: true)]
    private ?CakeFlavor $flavor = null;

    #[ORM\Column(enumType: CakeSize::class, nullable: true)]
    private ?CakeSize $size = null;

    #[ORM\Column(nullable: true)]
    private ?int $layers = null;

    #[ORM\Column(enumType: FrostingFlavor::class, nullable: true)]
    private ?FrostingFlavor $frostingFlavor = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true, enumType: Topping::class)]
    private ?array $toppings = null;

    #[ORM\Column(nullable: true)]
    private ?float $qualityScore = null;

    #[ORM\Column]
    private bool $isBaked = false;

    #[ORM\Column(enumType: CakeBuildPhase::class, length: 20)]
    private CakeBuildPhase $buildPhase = CakeBuildPhase::MIXING;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bakingStartedAt = null;

    #[ORM\OneToOne(inversedBy: 'cake')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CakeOrder $cakeOrder = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getFlavor(): ?CakeFlavor
    {
        return $this->flavor;
    }

    public function setFlavor(?CakeFlavor $flavor): static
    {
        $this->flavor = $flavor;

        return $this;
    }

    public function getSize(): ?CakeSize
    {
        return $this->size;
    }

    public function setSize(CakeSize $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getLayers(): ?int
    {
        return $this->layers;
    }

    public function setLayers(int $layers): static
    {
        $this->layers = $layers;

        return $this;
    }

    public function getFrostingFlavor(): ?FrostingFlavor
    {
        return $this->frostingFlavor;
    }

    public function setFrostingFlavor(?FrostingFlavor $frostingFlavor): static
    {
        $this->frostingFlavor = $frostingFlavor;

        return $this;
    }

    /**
     * @return Topping[]|null
     */
    public function getToppings(): ?array
    {
        return $this->toppings;
    }

    public function setToppings(?array $toppings): static
    {
        $this->toppings = $toppings;

        return $this;
    }

    public function getQualityScore(): ?float
    {
        return $this->qualityScore;
    }

    public function setQualityScore(?float $qualityScore): static
    {
        $this->qualityScore = $qualityScore;

        return $this;
    }

    public function isBaked(): bool
    {
        return $this->isBaked;
    }

    public function setIsBaked(bool $isBaked): static
    {
        $this->isBaked = $isBaked;

        return $this;
    }

    public function getBuildPhase(): CakeBuildPhase
    {
        return $this->buildPhase;
    }

    public function setBuildPhase(CakeBuildPhase $buildPhase): static
    {
        $this->buildPhase = $buildPhase;

        return $this;
    }

    public function getBakingStartedAt(): ?\DateTimeImmutable
    {
        return $this->bakingStartedAt;
    }

    public function setBakingStartedAt(?\DateTimeImmutable $bakingStartedAt): static
    {
        $this->bakingStartedAt = $bakingStartedAt;

        return $this;
    }

    public function getCakeOrder(): ?CakeOrder
    {
        return $this->cakeOrder;
    }

    public function setCakeOrder(?CakeOrder $cakeOrder): static
    {
        $this->cakeOrder = $cakeOrder;

        return $this;
    }
}
