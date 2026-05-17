<?php

declare(strict_types=1);

namespace App\Tests\Spi\Mailer;

use App\Spi\Mailer\ApiEmailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Email;

class ApiEmailSenderTest extends TestCase
{
    private const API_URL = 'https://api.notification-service.internal';
    private const API_KEY = 'test-api-key';

    public function testSendsEmailViaApi(): void
    {
        $email = (new Email())
            ->from('noreply@shop-training.com')
            ->to('jean.dupont@example.com')
            ->subject('Confirmation de commande')
            ->html('<p>Bonjour Jean</p>');

        $response = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($response);

        $sender = new ApiEmailSender($httpClient, self::API_URL, self::API_KEY);

        ($sender)($email);

        self::assertEquals(
            [
                'method' => 'POST',
                'url' => self::API_URL . '/emails',
                'body' => [
                    'from' => 'noreply@shop-training.com',
                    'to' => 'jean.dupont@example.com',
                    'subject' => 'Confirmation de commande',
                    'body' => '<p>Bonjour Jean</p>',
                    'attachments' => [],
                ],
            ],
            [
                'method' => $response->getRequestMethod(),
                'url' => $response->getRequestUrl(),
                'body' => json_decode($response->getRequestOptions()['body'], true),
            ],
        );

        self::assertContains('Authorization: Bearer ' . self::API_KEY, $response->getRequestOptions()['headers']);
        self::assertContains('Content-Type: application/json', $response->getRequestOptions()['headers']);
    }

    public function testSendsEmailWithAttachment(): void
    {
        $email = (new Email())
            ->from('noreply@shop-training.com')
            ->to('jean.dupont@example.com')
            ->subject('Votre facture')
            ->html('<p>Ci-joint votre facture</p>')
            ->attach('fake-pdf-content', 'facture_1.pdf', 'application/pdf');

        $response = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($response);

        $sender = new ApiEmailSender($httpClient, self::API_URL, self::API_KEY);

        ($sender)($email);

        $payload = json_decode($response->getRequestOptions()['body'], true);

        self::assertEquals(
            [['filename' => 'facture_1.pdf', 'content' => base64_encode('fake-pdf-content')]],
            $payload['attachments'],
        );
    }
}
