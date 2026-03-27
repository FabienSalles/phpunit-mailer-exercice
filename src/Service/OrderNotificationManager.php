<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class OrderNotificationManager
{
    private Environment $twig;
    private MailerInterface $mailer;
    private UserService $userService;
    private ConfigService $configService;
    private InvoiceService $invoiceService;
    private string $fromEmail;
    private string $shopName;

    public function __construct(
        Environment $twig,
        MailerInterface $mailer,
        UserService $userService,
        ConfigService $configService,
        InvoiceService $invoiceService,
        string $fromEmail,
        string $shopName,
    ) {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->userService = $userService;
        $this->configService = $configService;
        $this->invoiceService = $invoiceService;
        $this->fromEmail = $fromEmail;
        $this->shopName = $shopName;
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $preferences = $this->userService->getCustomerPreferences($order->getId());

        if (!$preferences['notifications']) {
            return;
        }

        $emailType = $order->isExpressDelivery()
            ? 'express_order.confirmation'
            : 'order.confirmation';

        $body = $this->buildEmailBody('order_confirmation', $order, [
            'estimatedDelivery' => $order->isExpressDelivery() ? '24h' : '3-5 jours ouvrés',
        ]);

        $subject = $this->getSubjectOverride($emailType)
            ?? 'Confirmation de votre commande __orderNumber__';
        $this->replacePlaceholders($subject, $order);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($order->getCustomerEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

    public function sendShippingNotification(Order $order, string $trackingUrl): void
    {
        $preferences = $this->userService->getCustomerPreferences($order->getId());

        if (!$preferences['notifications']) {
            return;
        }

        $body = $this->buildEmailBody('shipping_notification', $order, [
            'trackingUrl' => $trackingUrl,
        ]);

        $subject = $this->getSubjectOverride('order.shipping')
            ?? 'Votre commande __orderNumber__ a été expédiée';
        $this->replacePlaceholders($subject, $order);

        $invoice = $this->invoiceService->generateInvoicePdf($order->getId());

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($order->getCustomerEmail())
            ->subject($subject)
            ->html($body)
            ->attach($invoice['content'], $invoice['filename'], 'application/pdf');

        $this->mailer->send($email);
    }

    public function sendOrderReminder(Order $order): void
    {
        $preferences = $this->userService->getCustomerPreferences($order->getId());

        if (!$preferences['notifications']) {
            return;
        }

        $body = $this->buildEmailBody('order_reminder', $order);

        $subject = $this->getSubjectOverride('order.reminder')
            ?? 'N\'oubliez pas votre commande __orderNumber__';
        $this->replacePlaceholders($subject, $order);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($order->getCustomerEmail())
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

    private function buildEmailBody(string $templateName, Order $order, array $extraParams = []): string
    {
        $innerHtml = $this->twig->render('Mail/' . $templateName . '.html.twig', array_merge([
            'order' => $order,
            'shopName' => $this->shopName,
        ], $extraParams));

        return $this->twig->render('Mail/base.html.twig', [
            'body' => $innerHtml,
            'shopName' => $this->shopName,
        ]);
    }

    private function getSubjectOverride(string $emailType): ?string
    {
        $config = $this->configService->getEmailConfig($emailType);

        if (empty($config['subject'])) {
            return null;
        }

        return $config['subject'];
    }

    private function replacePlaceholders(string &$subject, Order $order): void
    {
        foreach ($order->toArray() as $key => $value) {
            $subject = str_replace('__' . $key . '__', (string) $value, $subject);
        }
    }
}
