<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class OrderNotificationManager
{
    private Environment $twig;
    private MailerInterface $mailer;
    private UserService $userService;
    private ConfigService $configService;
    private InvoiceService $invoiceService;
    private RouterInterface $router;
    private string $fromEmail;
    private string $shopName;

    public function __construct(
        Environment $twig,
        MailerInterface $mailer,
        UserService $userService,
        ConfigService $configService,
        InvoiceService $invoiceService,
        RouterInterface $router,
        string $fromEmail,
        string $shopName
    ) {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->userService = $userService;
        $this->configService = $configService;
        $this->invoiceService = $invoiceService;
        $this->router = $router;
        $this->fromEmail = $fromEmail;
        $this->shopName = $shopName;
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $emailType = $order->isExpressDelivery()
            ? 'express_order.confirmation'
            : 'order.confirmation';

        $body = $this->buildEmailBody('order_confirmation', $order, [
            'estimatedDelivery' => $order->isExpressDelivery() ? '24h' : '3-5 jours ouvrés',
        ]);

        $subject = $this->getSubjectOverride($emailType, $order)
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
        $body = $this->buildEmailBody('shipping_notification', $order, [
            'trackingUrl' => $trackingUrl,
        ]);

        $subject = $this->getSubjectOverride('order.shipping', $order)
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
        $body = $this->buildEmailBody('order_reminder', $order);

        $subject = $this->getSubjectOverride('order.reminder', $order)
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

    private function getSubjectOverride(string $emailType, Order $order): ?string
    {
        $config = $this->configService->getEmailConfig($emailType, $order->getStoreCode());

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
