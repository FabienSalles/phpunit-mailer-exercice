<?php

declare(strict_types=1);

namespace App\Entity;

class Order
{
    private int $id;
    private string $orderNumber;
    private string $customerEmail;
    private string $customerName;
    private string $customerFirstName;
    private OrderStatus $status;
    /** @var OrderItem[] */
    private array $items = [];
    private float $totalAmount;
    private string $shippingAddress;
    private ?string $trackingNumber = null;
    private \DateTimeImmutable $createdAt;
    private string $storeCode;
    private bool $expressDelivery = false;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): void
    {
        $this->customerName = $customerName;
    }

    public function getCustomerFirstName(): string
    {
        return $this->customerFirstName;
    }

    public function setCustomerFirstName(string $customerFirstName): void
    {
        $this->customerFirstName = $customerFirstName;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    /** @return OrderItem[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): void
    {
        $this->items[] = $item;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getShippingAddress(): string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(string $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): void
    {
        $this->trackingNumber = $trackingNumber;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getStoreCode(): string
    {
        return $this->storeCode;
    }

    public function setStoreCode(string $storeCode): void
    {
        $this->storeCode = $storeCode;
    }

    public function isExpressDelivery(): bool
    {
        return $this->expressDelivery;
    }

    public function setExpressDelivery(bool $expressDelivery): void
    {
        $this->expressDelivery = $expressDelivery;
    }

    /**
     * Used for subject placeholder replacement (mirrors Versement::toArray()).
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'orderNumber' => $this->orderNumber,
            'customerName' => $this->customerName,
            'customerFirstName' => $this->customerFirstName,
            'totalAmount' => number_format($this->totalAmount, 2, ',', ' '),
            'shippingAddress' => $this->shippingAddress,
            'storeCode' => $this->storeCode,
        ];
    }
}
