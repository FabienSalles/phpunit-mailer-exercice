<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderNotificationManagerTest extends KernelTestCase
{
    use MatchesSnapshots;

    /**
     * TODO: Implémenter ce test
     * - Booter le kernel avec self::bootKernel()
     * - Créer un StubbedMailer et l'injecter dans le container (remplace MailerInterface)
     * - Stubber UserService::getCustomerPreferences via Prophecy pour renvoyer
     *   ['locale' => 'fr', 'notifications' => true]
     *   (sinon vrai appel HTTP vers api.customer.internal, ou notifications=false => aucun email)
     * - Récupérer le OrderNotificationManager depuis le container
     * - Appeler sendOrderConfirmation($order)
     * - Vérifier from/to/subject de l'email capturé en une seule assertEquals contre une structure attendue
     * - Vérifier le body HTML avec assertMatchesHtmlSnapshot()
     */
    #[DataProvider('orderConfirmationProvider')]
    public function testSendOrderConfirmation(Order $order, string $expectedSubject): void
    {
        $this->markTestIncomplete('TODO: Implémenter le test snapshot');
    }

    public static function orderConfirmationProvider(): \Generator
    {
        yield 'commande standard' => [
            'order' => self::createStandardOrder(),
            'expectedSubject' => 'Confirmation de votre commande n°CMD-2024-001',
        ];

        yield 'commande express' => [
            'order' => self::createExpressOrder(),
            'expectedSubject' => 'Confirmation de votre commande express n°CMD-2024-002',
        ];

        yield 'commande multi-articles' => [
            'order' => self::createMultiItemOrder(),
            'expectedSubject' => 'Confirmation de votre commande n°CMD-2024-003',
        ];
    }

    /**
     * TODO: Implémenter ce test
     * - Même pattern que testSendOrderConfirmation (stub UserService via Prophecy inclus)
     * - Stubber InvoiceService::generateInvoicePdf via Prophecy — vrai appel HTTP vers api.billing.internal sinon
     * - Vérifier qu'une pièce jointe est présente sur l'email
     */
    #[DataProvider('shippingNotificationProvider')]
    public function testSendShippingNotification(Order $order, string $trackingUrl, string $expectedSubject): void
    {
        $this->markTestIncomplete('TODO: Implémenter le test snapshot');
    }

    public static function shippingNotificationProvider(): \Generator
    {
        $orderWithTracking = self::createStandardOrder();
        $orderWithTracking->setStatus(OrderStatus::SHIPPED);
        $orderWithTracking->setTrackingNumber('TRACK-FR-12345');

        $orderWithoutTracking = self::createStandardOrder();
        $orderWithoutTracking->setOrderNumber('CMD-2024-010');
        $orderWithoutTracking->setStatus(OrderStatus::SHIPPED);

        yield 'avec numéro de tracking' => [
            'order' => $orderWithTracking,
            'trackingUrl' => 'https://tracking.example.com/TRACK-FR-12345',
            'expectedSubject' => 'Votre commande n°CMD-2024-001 est en route, Jean !',
        ];

        yield 'sans numéro de tracking' => [
            'order' => $orderWithoutTracking,
            'trackingUrl' => 'https://tracking.example.com/pending',
            'expectedSubject' => 'Votre commande n°CMD-2024-010 est en route, Jean !',
        ];
    }

    /**
     * TODO: Implémenter ce test
     * - Même pattern que testSendOrderConfirmation (stub UserService inclus)
     */
    #[DataProvider('orderReminderProvider')]
    public function testSendOrderReminder(Order $order, string $expectedSubject): void
    {
        $this->markTestIncomplete('TODO: Implémenter le test snapshot');
    }

    public static function orderReminderProvider(): \Generator
    {
        yield 'relance commande standard' => [
            'order' => self::createStandardOrder(),
            'expectedSubject' => 'Votre panier vous attend, Jean !',
        ];

        yield 'relance commande express' => [
            'order' => self::createExpressOrder(),
            'expectedSubject' => 'Votre panier vous attend, Sophie !',
        ];
    }

    private static function createStandardOrder(): Order
    {
        $order = new Order();
        $order->setId(1);
        $order->setOrderNumber('CMD-2024-001');
        $order->setCustomerName('Dupont');
        $order->setCustomerFirstName('Jean');
        $order->setCustomerEmail('jean.dupont@example.com');
        $order->setStoreCode('STORE-PARIS');
        $order->setExpressDelivery(false);
        $order->setTotalAmount(119.97);
        $order->setShippingAddress('12 rue de la Paix, 75002 Paris');
        $order->setStatus(OrderStatus::CONFIRMED);
        $order->setCreatedAt(new \DateTimeImmutable('2024-01-15'));
        $order->addItem(new OrderItem('T-shirt bleu', 2, 29.99));
        $order->addItem(new OrderItem('Pantalon noir', 1, 59.99));

        return $order;
    }

    private static function createExpressOrder(): Order
    {
        $order = new Order();
        $order->setId(2);
        $order->setOrderNumber('CMD-2024-002');
        $order->setCustomerName('Martin');
        $order->setCustomerFirstName('Sophie');
        $order->setCustomerEmail('sophie.martin@example.com');
        $order->setStoreCode('STORE-LYON');
        $order->setExpressDelivery(true);
        $order->setTotalAmount(89.99);
        $order->setShippingAddress('5 place Bellecour, 69002 Lyon');
        $order->setStatus(OrderStatus::CONFIRMED);
        $order->setCreatedAt(new \DateTimeImmutable('2024-01-16'));
        $order->addItem(new OrderItem('Robe rouge', 1, 89.99));

        return $order;
    }

    private static function createMultiItemOrder(): Order
    {
        $order = new Order();
        $order->setId(3);
        $order->setOrderNumber('CMD-2024-003');
        $order->setCustomerName('Durand');
        $order->setCustomerFirstName('Pierre');
        $order->setCustomerEmail('pierre.durand@example.com');
        $order->setStoreCode('STORE-PARIS');
        $order->setExpressDelivery(false);
        $order->setTotalAmount(184.94);
        $order->setShippingAddress('8 avenue des Champs-Élysées, 75008 Paris');
        $order->setStatus(OrderStatus::CONFIRMED);
        $order->setCreatedAt(new \DateTimeImmutable('2024-01-17'));
        $order->addItem(new OrderItem('Chaussures running', 1, 129.99));
        $order->addItem(new OrderItem('Chaussettes sport', 3, 9.99));
        $order->addItem(new OrderItem('Gourde isotherme', 1, 24.99));

        return $order;
    }
}
