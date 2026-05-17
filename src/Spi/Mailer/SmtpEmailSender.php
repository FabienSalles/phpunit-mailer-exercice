<?php

declare(strict_types=1);

namespace App\Spi\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SmtpEmailSender implements SendEmailInterface
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function __invoke(Email $email): void
    {
        $this->mailer->send($email);
    }
}
