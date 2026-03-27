<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class InvoiceService
{
    private HttpClientInterface $httpClient;
    private string $billingApiUrl;
    private string $billingApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        string $billingApiUrl,
        string $billingApiKey,
    ) {
        $this->httpClient = $httpClient;
        $this->billingApiUrl = $billingApiUrl;
        $this->billingApiKey = $billingApiKey;
    }

    /**
     * @return array{filename: string, content: string}
     */
    public function generateInvoicePdf(int $orderId): array
    {
        $response = $this->httpClient->request('GET', $this->billingApiUrl . '/invoices/' . $orderId . '.pdf', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->billingApiKey,
                'Accept' => 'application/pdf',
            ],
        ]);

        return [
            'filename' => 'facture_' . $orderId . '.pdf',
            'content' => $response->getContent(),
        ];
    }
}
