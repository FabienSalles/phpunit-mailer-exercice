<?php

declare(strict_types=1);

namespace App\Entity;

class OrderItem
{
    private string $productName;
    private int $quantity;
    private float $unitPrice;

    public function __construct(string $productName, int $quantity, float $unitPrice)
    {
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getTotal(): float
    {
        return $this->quantity * $this->unitPrice;
    }
}
