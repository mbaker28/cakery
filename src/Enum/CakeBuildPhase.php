<?php

namespace App\Enum;

enum CakeBuildPhase: string
{
    case MIXING     = 'mixing';
    case BAKING     = 'baking';
    case DECORATING = 'decorating';
}
