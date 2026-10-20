<?php
namespace Core;

/**
 * AssetManager - WordPress tarzı wp_enqueue_script / wp_enqueue_style yöneticisi.
 * Modül aktifken ilgili JS ve CSS dosyalarını dinamik olarak sayfaya bağlar.
 */
class AssetManager
{
    private static array $scripts = [];
    private static array $styles = [];
    private static array $localized = [];

    /**
     * JS değişkeni veya i18n objesini ilgili script handle'ına bağlar
     */
    public static function localizeScript(string $handle, string $objectName, array $data): void
    {
        self::$localized[$handle][$objectName] = $data;
    }

    /**
     * URL'yi normalize eder: eğer /taskmanager/ ile başlıyorsa veya /Modules, /Public ile başlıyorsa baseUrl() ile dinamikleştirir.
     */
    public static function normalizeSrc(string $src): string
    {
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://') || str_starts_with($src, '//')) {
            return $src;
        }

        $base = function_exists('baseUrl') ? baseUrl() : '';

        if (str_starts_with($src, '/taskmanager/')) {
            $relativePath = substr($src, strlen('/taskmanager/'));
            return rtrim($base, '/') . '/' . ltrim($relativePath, '/');
        }

        if (str_starts_with($src, '/Modules/') || str_starts_with($src, '/Public/')) {
            return rtrim($base, '/') . '/' . ltrim($src, '/');
        }

        return $src;
    }

    /**
     * JS dosyasını kuyruğa ekler
     */
    public static function enqueueJs(string $handle, string $src, array $deps = [], bool $inFooter = true): void
    {
        self::$scripts[$handle] = [
            'src'       => self::normalizeSrc($src),
            'deps'      => $deps,
            'in_footer' => $inFooter
        ];
    }

    /**
     * CSS dosyasını kuyruğa ekler
     */
    public static function enqueueCss(string $handle, string $src): void
    {
        self::$styles[$handle] = [
            'src' => self::normalizeSrc($src)
        ];
    }

    /**
     * Header içinde CSS etiketlerini basar (Otomatik Cache Busting ile)
     */
    public static function renderStyles(): string
    {
        $html = '';
        $root = dirname(__DIR__);
        foreach (self::$styles as $handle => $style) {
            $src = $style['src'];
            $cleanPath = ltrim(parse_url($src, PHP_URL_PATH) ?? '', '/');
            if (function_exists('baseUrl')) {
                $base = trim(baseUrl(), '/');
                if ($base !== '' && str_starts_with($cleanPath, $base . '/')) {
                    $cleanPath = substr($cleanPath, strlen($base) + 1);
                }
            }
            $localFile = $root . '/' . $cleanPath;
            $ver = file_exists($localFile) ? filemtime($localFile) : time();
            $glue = str_contains($src, '?') ? '&' : '?';
            $finalSrc = htmlspecialchars($src . $glue . 'v=' . $ver, ENT_QUOTES, 'UTF-8');
            $html .= "<link rel=\"stylesheet\" id=\"cu-style-{$handle}\" href=\"{$finalSrc}\">\n";
        }
        return $html;
    }

    /**
     * Footer içinde JS etiketlerini ve bağlı local data objelerini basar (Otomatik Cache Busting ile)
     */
    public static function renderScripts(): string
    {
        $html = '';
        $root = dirname(__DIR__);
        foreach (self::$scripts as $handle => $script) {
            if (!empty(self::$localized[$handle])) {
                $html .= "<script id=\"cu-localize-{$handle}\">\n";
                foreach (self::$localized[$handle] as $objName => $objData) {
                    $json = json_encode($objData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
                    $html .= "window.{$objName} = {$json};\n";
                }
                $html .= "</script>\n";
            }
            $src = $script['src'];
            $cleanPath = ltrim(parse_url($src, PHP_URL_PATH) ?? '', '/');
            if (function_exists('baseUrl')) {
                $base = trim(baseUrl(), '/');
                if ($base !== '' && str_starts_with($cleanPath, $base . '/')) {
                    $cleanPath = substr($cleanPath, strlen($base) + 1);
                }
            }
            $localFile = $root . '/' . $cleanPath;
            $ver = file_exists($localFile) ? filemtime($localFile) : time();
            $glue = str_contains($src, '?') ? '&' : '?';
            $finalSrc = htmlspecialchars($src . $glue . 'v=' . $ver, ENT_QUOTES, 'UTF-8');
            $html .= "<script id=\"cu-script-{$handle}\" src=\"{$finalSrc}\"></script>\n";
        }
        return $html;
    }

    /**
     * Kuyruğu temizler (Testler için)
     */
    public static function clear(): void
    {
        self::$scripts = [];
        self::$styles = [];
        self::$localized = [];
    }
}
