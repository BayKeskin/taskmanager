<?php
/**
 * Legacy Mailer Facade
 * Delegates directly to HMVC Module: Modules\Mailer\Services\MailerService
 */
class Mailer
{
    public static function testConnection(?string $targetEmail = null): array
    {
        return \Modules\Mailer\Services\MailerService::testConnection($targetEmail);
    }

    public static function send(string $subject, string $message, array $mails = [], mixed $pdf = ''): bool
    {
        return \Modules\Mailer\Services\MailerService::send($subject, $message, $mails, $pdf);
    }

    public function sendmail(string $subject, string $message, array $mails = [], mixed $pdf = ''): bool
    {
        return \Modules\Mailer\Services\MailerService::send($subject, $message, $mails, $pdf);
    }
}
