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
     * Footer içinde JS etiketlerini basar
     */
    public static function renderScripts(): string
    {
        $html = '';
        foreach (self::$scripts as $handle => $script) {
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
    }
}
