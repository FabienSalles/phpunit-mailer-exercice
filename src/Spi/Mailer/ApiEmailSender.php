<?php

declare(strict_types=1);

namespace App\Spi\Mailer;

use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApiEmailSender implements SendEmailInterface
{
    private HttpClientInterface $httpClient;
    private string $apiUrl;
    private string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        string $apiUrl,
        string $apiKey,
    ) {
        $this->httpClient = $httpClient;
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    public function __invoke(Email $email): void
    {
        $payload = [
            'from' => $email->getFrom()[0]->getAddress(),
            'to' => $email->getTo()[0]->getAddress(),
            'subject' => $email->getSubject(),
            'body' => $email->getHtmlBody(),
            'attachments' => array_map(
                fn($attachment) => [
                    'filename' => $attachment->getFilename(),
                    'content' => base64_encode($attachment->getBody()),
                ],
                $email->getAttachments(),
            ),
        ];

        $this->httpClient->request('POST', $this->apiUrl . '/emails', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
    }
}
