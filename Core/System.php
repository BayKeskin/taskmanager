<?php
ob_start();
date_default_timezone_set('Europe/Istanbul');
ini_set("display_errors", 1);

// Oturum ve Güvenli Çerez Ayarları
if (session_status() === PHP_SESSION_NONE) {
    // Sistem geçici dizin izin kilitlenmelerini önlemek için izole proje session dizini
    $sessionSavePath = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessionSavePath)) {
        @mkdir($sessionSavePath, 0777, true);
    }
    if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
        @session_save_path($sessionSavePath);
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => $isSecure ? 'None' : 'Lax'
    ]);
    @session_start();
}

// 1. Dil Motorunun ve DataLayer'ın Yüklenmesi
require_once __DIR__ . '/DataLayer.php';
require_once __DIR__ . '/Language.php';

// 2. Global Veritabanı ve Temel Ayarlar
$db = new Database();

// 3. Modüllerin Yüklenmesi
require_once __DIR__ . '/ModuleLoader.php';
\Core\ModuleLoader::boot(dirname(__DIR__) . '/Modules');
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

/**
 * Dinamik Web Dizin Yolu (Base URL/Path)
 * Uygulamanın kurulu olduğu alt dizini (örn: /taskmanager, /taskold, /crm veya kök dizinse '') dinamik olarak çözer.
 */
function baseUrl(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
        $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';

        if (!empty($docRoot) && str_starts_with($projectRoot, $docRoot)) {
            $rel = substr($projectRoot, strlen($docRoot));
            $base = '/' . trim(str_replace('\\', '/', $rel), '/');
        } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $dir = preg_replace('#/(Controller|Public|View|System|Modules|Core|tests).*$#i', '', $dir);
            $base = '/' . trim($dir, '/');
        } else {
            $base = '';
        }
        if ($base === '/') {
            $base = '';
        }
    }

    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return $base ?: '/';
    }
    return ($base ? $base : '') . '/' . $cleanPath;
}

/**
 * Dinamik Tam Site URL (Scheme + Host + Base URL)
 */
function siteUrl(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = baseUrl($path);
    return rtrim($protocol . $host, '/') . '/' . ltrim($base, '/');
}
