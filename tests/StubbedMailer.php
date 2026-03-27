<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class StubbedMailer implements MailerInterface
{
    private Email $sentEmail;

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        assert($message instanceof Email);
        $this->sentEmail = $message;
    }

    public function getSentEmail(): Email
    {
        return $this->sentEmail;
    }
}
