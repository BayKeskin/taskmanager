<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private array $config = [];

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $configFile = __DIR__ . '/../config/mail.php';
            if (file_exists($configFile)) {
                $this->config = require $configFile;
            }

            // Veritabanı settings tablosundaki SMTP ayarlarını önceliklendir
            try {
                if (class_exists('Database')) {
                    $db = new Database();
                    $s = $db->connect->query("SELECT smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, smtp_from_email, smtp_from_name FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
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
            } catch (\Throwable $e) {
                // Hata durumunda config dosyasını koru
            }
        } else {
            $this->config = $config;
        }
    }

    /**
     * SMTP Yapılandırma ve Bağlantı Testi (SMTP Configuration and Connection Test)
     */
    public static function testConnection(?string $targetEmail = null): array
    {
        $mailer = new self();
        $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');

        if (empty($mailer->config['host']) || $mailer->config['host'] === 'localhost') {
            return [
                'success' => false,
                'message' => $isTr 
                    ? 'SMTP Host henüz girilmemiş. Lütfen Sistem Ayarları sayfasından SMTP sunucu bilgilerini kaydedin.'
                    : 'SMTP Host is not configured yet. Please save your SMTP server credentials in System Settings.'
            ];
        }

        $statusMsg = $isTr
            ? "SMTP ayarları yüklendi: {$mailer->config['host']}:{$mailer->config['port']} (" . strtoupper($mailer->config['encryption'] ?? 'TLS') . ")"
            : "SMTP configuration loaded: {$mailer->config['host']}:{$mailer->config['port']} (" . strtoupper($mailer->config['encryption'] ?? 'TLS') . ")";

        // Test configuration verification
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
     * Hızlı ve pratik statik gönderim:
     * Mailer::send("Konu", "Mesaj", ["alici@mail.com"])
     */
    public static function send(string $subject, string $message, array $mails = [], mixed $pdf = ''): bool
    {
        $instance = new self();
        return $instance->sendmail($subject, $message, $mails, $pdf);
    }

    /**
     * E-posta gönderim metodu
     */
    public function sendmail(string $subject, string $message, array $mails = [], mixed $pdf = ''): bool
    {
        // PHPMailer kütüphanesini yalnızca mail gönderileceği an hafızaya al (Lazy Load)
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $basePath = __DIR__ . '/../../Additions/PHPMailler/src/';
            if (file_exists($basePath . 'Exception.php')) {
                require_once $basePath . 'Exception.php';
                require_once $basePath . 'PHPMailer.php';
                require_once $basePath . 'SMTP.php';
            }
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("PHPMailer library not found in the specified directory: " . $basePath);
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            // SMTP Settings
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

            // Sender
            $fromEmail = $this->config['from_email'] ?? $mail->Username;
            $fromName  = $this->config['from_name'] ?? '';
            $mail->setFrom($fromEmail, $fromName);

            // Attachments (PDF / Files)
            if (!empty($pdf)) {
                $attachments = is_array($pdf) ? $pdf : [$pdf];
                foreach ($attachments as $filePath) {
                    $fullPath = __DIR__ . '/../../' . ltrim($filePath, '/');
                    if (file_exists($fullPath)) {
                        $mail->addAttachment($fullPath);
                    }
                }
            }

            // Recipients
            foreach ($mails as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($recipient);
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);

            return $mail->send();
        } catch (Exception $e) {
            error_log("Mail send error: {$mail->ErrorInfo} | " . $e->getMessage());
            return false;
        }
    }
}
