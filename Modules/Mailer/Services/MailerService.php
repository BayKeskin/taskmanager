<?php
namespace Modules\Mailer\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailerService
{
    private array $config = [];

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $configFile = dirname(__DIR__, 2) . '/config/mail.php';
            if (file_exists($configFile)) {
                $this->config = require $configFile;
            }

            // Veritabanı settings tablosundaki SMTP ayarlarını önceliklendir
            try {
                $db = $GLOBALS['db'] ?? null;
                if ($db && isset($db->connect)) {
                    $s = $db->connect->query("SELECT smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, smtp_from_email, smtp_from_name FROM settings WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);
                    if (!empty($s['smtp_host'])) {
                        $this->config['host']       = trim($s['smtp_host']);
                        $this->config['port']       = (int)($s['smtp_port'] ?: 587);
                        $this->config['encryption'] = trim($s['smtp_secure'] ?: 'tls');
                        $this->config['username']   = trim($s['smtp_user'] ?? '');
                        $this->config['password']   = trim($s['smtp_pass'] ?? '');
                        $this->config['from_email'] = trim($s['smtp_from_email'] ?: $this->config['username']);
                        $this->config['from_name']  = trim($s['smtp_from_name'] ?: 'TaskManager');
                        $this->config['auth']       = !empty($this->config['username']);
                    }
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
                'message' => __('Mailer::host_missing', 'SMTP Host henüz girilmemiş. Lütfen Sistem Ayarları sayfasından SMTP sunucu bilgilerini kaydedin.')
            ];
        }

        $statusMsg = __('Mailer::test_success', "SMTP ayarları yüklendi: {$mailer->config['host']}:{$mailer->config['port']}");

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
     * Şablonlu HTML e-posta gönderimi
     */
    public static function sendHtmlMail(string $to, string $subject, string $bodyContent, ?string $title = null): bool
    {
        $instance = new self();
        $title = $title ?? $subject;
        $html = "
        <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif; max-width:600px; margin:0 auto; padding:24px; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px;'>
            <div style='border-bottom:2px solid #7b68ee; padding-bottom:12px; margin-bottom:18px;'>
                <h2 style='color:#1a202c; margin:0; font-size:18px;'>{$title}</h2>
            </div>
            <div style='color:#4a5568; font-size:14px; line-height:1.6;'>
                {$bodyContent}
            </div>
            <div style='margin-top:24px; padding-top:14px; border-top:1px solid #edf2f7; font-size:11px; color:#a0aec0; text-align:center;'>
                TaskManager Pro Notifications &bull; " . date('Y-m-d H:i') . "
            </div>
        </div>";

        return $instance->sendmail($subject, $html, [$to]);
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
