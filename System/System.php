<?php
ob_start();
date_default_timezone_set('Europe/Istanbul');
ini_set("display_errors", 1);

// Oturum ve Güvenli Çerez Ayarları
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => $isSecure ? 'None' : 'Lax'
    ]);
    session_start();
}

// 1. Modüllerin Yüklenmesi
require_once __DIR__ . '/../Model/DataLayer.php';
require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Content.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Language.php';
require_once __DIR__ . '/ProjectAnalyzer.php';

// 2. Global Veritabanı ve Temel Ayarlar
$db = new Database();
$settingsRow = $db->multi_record("settings", [], [], "", "id", "ASC", "1");
$directory = $settingsRow[0]->directory ?? ($db->get_single("settings", "directory", "id", 1) ?? '');
$siteURL   = $settingsRow[0]->siteurl ?? ($db->get_single("settings", "siteurl", "id", 1) ?? '');
if (!empty($siteURL) && !str_starts_with($siteURL, 'http://') && !str_starts_with($siteURL, 'https://')) {
    $siteURL = 'http://' . $siteURL;
}

/**
 * System Sınıfı - Uygulama Koordinatörü ve Servis Yöneticisi
 * Çekirdek modülleri barındırır, ağır servisleri (Commerce, Mailer) ihtiyaç anında (Lazy Load) yükler.
 */
class System
{
    public string $title = '';
    public string $description = '';

    // Çekirdek Servisler
    public Database $db;
    public Content $content;

    // İhtiyaç Anında Yüklenen Servisler (Lazy Loaded)
    private ?Auth $authInstance = null;
    private ?Mailer $mailerInstance = null;

    public function __construct(?Database $database = null)
    {
        global $db;
        $this->db = $database ?? $db;

        $this->content = new Content($this->db);
    }

    /**
     * Geriye dönük uyumluluk: Content::return_value metodunu doğrudan System üzerinden çağırabilme
     */
    public function return_value($post_id, $name)
    {
        return $this->content->return_value($post_id, $name);
    }

    public function __call(string $method, array $args)
    {
        if (method_exists($this->content, $method)) {
            return $this->content->$method(...$args);
        }
        if (method_exists($this->auth(), $method)) {
            return $this->auth()->$method(...$args);
        }
        throw new BadMethodCallException("Method System::{$method} does not exist.");
    }

    /**
     * Kullanıcı / Oturum Modülü (Sadece üyelikli projelerde belleğe alınır)
     */
    public function auth(): Auth
    {
        if ($this->authInstance === null) {
            $this->authInstance = new Auth($this->db);
        }
        return $this->authInstance;
    }

    /**
     * Mailer Modülü (Sadece mail gönderileceği an belleğe alınır)
     */
    public function mailer(): Mailer
    {
        if ($this->mailerInstance === null) {
            $this->mailerInstance = new Mailer();
        }
        return $this->mailerInstance;
    }
}

// Global System Nesnesi
$system = new System($db);

// Dil Motorunu Oturum ve Kullanıcı Tercihine Göre Başlat
Language::init();
