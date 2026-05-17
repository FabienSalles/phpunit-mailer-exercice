<?php

declare(strict_types=1);

namespace App\Spi\Mailer;

use Symfony\Component\Mime\Email;

interface SendEmailInterface
{
    public function __invoke(Email $email): void;
}
