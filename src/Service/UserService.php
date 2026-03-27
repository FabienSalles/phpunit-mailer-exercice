<?php

declare(strict_types=1);

namespace App\Service;

class UserService
{
    /**
     * @return array{locale: string, notifications: bool}
     */
    public function getCustomerPreferences(int $customerId): array
    {
        return ['locale' => 'fr', 'notifications' => true];
    }

    public function getStoreManagerEmail(string $storeCode): string
    {
        return 'manager@store.com';
    }
}
