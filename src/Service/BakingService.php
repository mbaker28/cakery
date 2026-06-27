<?php

namespace App\Service;

use App\Entity\Cake;

class BakingService
{
    public function bake(Cake $cake): void
    {
        $cake->setQualityScore(100.0);
        $cake->setIsBaked(true);
    }
}
