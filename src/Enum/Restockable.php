<?php

namespace App\Enum;

interface Restockable
{
    public function inventoryKey(): string; // e.g. 'frosting_vanilla'
    public function label(): string;
    public function costPerUnit(): float;
    public function unit(): string;
    public function group(): string;
}