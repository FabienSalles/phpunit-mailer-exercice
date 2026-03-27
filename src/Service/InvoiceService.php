<?php

declare(strict_types=1);

namespace App\Service;

class InvoiceService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function generateInvoicePdf(int $orderId): array
    {
        return [
            'filename' => 'facture_' . $orderId . '.pdf',
            'content' => '%PDF-1.4 fake content for order ' . $orderId,
        ];
    }
}
