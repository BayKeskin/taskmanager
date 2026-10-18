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
     * JS dosyasını kuyruğa ekler
     */
    public static function enqueueJs(string $handle, string $src, array $deps = [], bool $inFooter = true): void
    {
        self::$scripts[$handle] = [
            'src'       => $src,
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
            'src' => $src
        ];
    }

    /**
     * Header içinde CSS etiketlerini basar
     */
    public static function renderStyles(): string
    {
        $html = '';
        foreach (self::$styles as $handle => $style) {
            $src = htmlspecialchars($style['src'], ENT_QUOTES, 'UTF-8');
            $html .= "<link rel=\"stylesheet\" id=\"cu-style-{$handle}\" href=\"{$src}\">\n";
        }
        return $html;
    }

    /**
     * Footer içinde JS etiketlerini ve bağlı local data objelerini basar
     */
    public static function renderScripts(): string
    {
        $html = '';
        foreach (self::$scripts as $handle => $script) {
            if (!empty(self::$localized[$handle])) {
                $html .= "<script id=\"cu-localize-{$handle}\">\n";
                foreach (self::$localized[$handle] as $objName => $objData) {
                    $json = json_encode($objData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
                    $html .= "window.{$objName} = {$json};\n";
                }
                $html .= "</script>\n";
            }
            $src = htmlspecialchars($script['src'], ENT_QUOTES, 'UTF-8');
            $html .= "<script id=\"cu-script-{$handle}\" src=\"{$src}\"></script>\n";
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
