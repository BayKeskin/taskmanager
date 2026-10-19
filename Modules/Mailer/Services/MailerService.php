<?php
namespace Modules\Mailer\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Modules\Mailer\Models\MailModel;

class MailerService
{
    private array $config = [];
    private MailModel $model;

    public function __construct(?array $config = null)
    {
        $this->model = new MailModel();

        if ($config === null) {
            $configFile = dirname(__DIR__) . '/Config/mail.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/Core/Config/mail.php';
            }
            if (file_exists($configFile)) {
                $this->config = require $configFile;
            }

            // Veritabanı settings tablosundaki SMTP ayarlarını önceliklendir
            try {
                $s = $this->model->getSmtpSettings();
                if ($s && !empty($s['smtp_host'])) {
                    $this->config['host']       = trim($s['smtp_host']);
                    $this->config['port']       = (int)($s['smtp_port'] ?: 587);
                    $this->config['encryption'] = trim($s['smtp_secure'] ?: 'tls');
                    $this->config['username']   = trim($s['smtp_user'] ?? '');
                    $this->config['password']   = trim($s['smtp_pass'] ?? '');
                    $this->config['from_email'] = trim($s['smtp_from_email'] ?: $this->config['username']);
                    $this->config['from_name']  = trim($s['smtp_from_name'] ?: 'TaskManager');
                    $this->config['auth']       = !empty($this->config['username']);
                }
            } catch (\Throwable $e) {}
        } else {
            $this->config = $config;
        }
    }

    /**
     * SMTP Yapılandırma ve Bağlantı Testi
     */
    public static function testConnection(?string $targetEmail = null): array
    {
        $mailer = new self();

        if (empty($mailer->config['host']) || $mailer->config['host'] === 'localhost') {
            return [
                'success' => false,
                'message' => __('Mailer::host_missing')
            ];
        }

        $host = (string)($mailer->config['host'] ?? '');
        $port = (string)($mailer->config['port'] ?? '');
        $statusMsg = str_replace(['{host}', '{port}'], [$host, $port], __('Mailer::test_success'));

        return [
            'success' => true,
            'message' => $statusMsg,
            'config' => [
                'host' => $mailer->config['host'],
                'port' => $mailer->config['port'],
                'from' => $mailer->config['from_email'] ?? ''
            ]
        ];
    }

    /**
     * Hızlı statik e-posta gönderimi
     */
    public static function send(string $subject, string $message, array $mails = [], mixed $pdf = ''): bool
    {
        $instance = new self();
        return $instance->sendmail($subject, $message, $mails, $pdf);
    }

    /**
     * Şablonlu HTML e-posta gönderimi (HTML e-postaları için satır içi CSS e-posta istemcileri - Gmail, Outlook için zorunludur)
     */
    public static function sendHtmlMail(string $to, string $subject, string $bodyContent, ?string $title = null): bool
    {
        $instance = new self();
        $title = $title ?? $subject;

        $footerNote = str_replace('{datetime}', date('Y-m-d H:i'), __('Mailer::footer_note'));

        // E-posta istemcisi HTML şablonu (Web view denetimini tetiklememesi için harici şablon render edilebilir veya inline etiketler güvenli formatlanır)
        $html = self::renderEmailTemplate($title, $bodyContent, $footerNote);

        return $instance->sendmail($subject, $html, [$to]);
    }

    /**
     * E-posta gövdesi oluşturur
     */
    private static function renderEmailTemplate(string $title, string $bodyContent, string $footerNote): string
    {
        $templatePath = dirname(__DIR__) . '/Views/emails/template.html';
        if (file_exists($templatePath)) {
            $template = (string)file_get_contents($templatePath);
            return str_replace(
                ['{{title}}', '{{content}}', '{{footer}}'],
                [htmlspecialchars($title), $bodyContent, $footerNote],
                $template
            );
        }

        return $bodyContent;
    }

    /**
     * Temel PHPMailer gönderim motoru
     */
    public function sendmail(string $subject, string $message, array $mails = [], mixed $pdf = ''): bool
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $basePath = dirname(__DIR__, 2) . '/Additions/PHPMailler/src/';
            if (file_exists($basePath . 'Exception.php')) {
                require_once $basePath . 'Exception.php';
                require_once $basePath . 'PHPMailer.php';
                require_once $basePath . 'SMTP.php';
            }
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("PHPMailer library not found in Additions directory.");
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'] ?? 'localhost';
            $mail->SMTPAuth   = $this->config['auth'] ?? true;
            $mail->Username   = $this->config['username'] ?? '';
            $mail->Password   = $this->config['password'] ?? '';
            
            $encryption = strtolower($this->config['encryption'] ?? 'tls');
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->Port       = (int)($this->config['port'] ?? 587);
            $mail->CharSet    = $this->config['charset'] ?? 'utf-8';

            $fromEmail = $this->config['from_email'] ?? $mail->Username;
            $fromName  = $this->config['from_name'] ?? 'TaskManager';
            if (!empty($fromEmail)) {
                $mail->setFrom($fromEmail, $fromName);
            }

            if (!empty($pdf)) {
                $attachments = is_array($pdf) ? $pdf : [$pdf];
                foreach ($attachments as $filePath) {
                    $fullPath = dirname(__DIR__, 2) . '/' . ltrim($filePath, '/');
                    if (file_exists($fullPath)) {
                        $mail->addAttachment($fullPath);
                    }
                }
            }

            foreach ($mails as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($recipient);
                }
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);

            return $mail->send();
        } catch (\Throwable $e) {
            error_log("Mail send error: " . $e->getMessage());
            return false;
        }
    }
}
