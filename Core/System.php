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

// 1. Dil Motorunun ve DataLayer'ın Yüklenmesi
require_once __DIR__ . '/DataLayer.php';
require_once __DIR__ . '/Language.php';

// 2. Modüllerin Yüklenmesi
require_once __DIR__ . '/ModuleLoader.php';
\Core\ModuleLoader::boot(dirname(__DIR__) . '/Modules');

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
 */
class System
{
    public string $title = '';
    public string $description = '';

    // Çekirdek Servisler
    public Database $db;

    public function __construct(?Database $database = null)
    {
        global $db;
        $this->db = $database ?? $db;
    }
}

// Global System Nesnesi
$system = new System($db);

// Dil Motorunu Oturum ve Kullanıcı Tercihine Göre Başlat
Language::init();
