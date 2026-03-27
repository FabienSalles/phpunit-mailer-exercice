<?php

declare(strict_types=1);

namespace App\Tests;

use App\Spi\Mailer\SendEmailInterface;
use Symfony\Component\Mime\Email;

class StubbedEmailSender implements SendEmailInterface
{
    private Email $sentEmail;

    public function __invoke(Email $email): void
    {
        $this->sentEmail = $email;
    }

    public function getSentEmail(): Email
    {
        return $this->sentEmail;
    }
}
