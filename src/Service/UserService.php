<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserService
{
    private HttpClientInterface $httpClient;
    private string $customerApiUrl;
    private string $customerApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        string $customerApiUrl,
        string $customerApiKey,
    ) {
        $this->httpClient = $httpClient;
        $this->customerApiUrl = $customerApiUrl;
        $this->customerApiKey = $customerApiKey;
    }

    /**
     * @return array{locale: string, notifications: bool}
     */
    public function getCustomerPreferences(int $customerId): array
    {
        $response = $this->httpClient->request('GET', $this->customerApiUrl . '/customers/' . $customerId . '/preferences', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->customerApiKey,
                'Accept' => 'application/json',
            ],
        ]);

        return $response->toArray();
    }
}
