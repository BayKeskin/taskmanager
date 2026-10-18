<?php
namespace Core;

/**
 * View - Çekirdek Şablon ve Görünüm Motoru
 * 'ModuleName::view_name' veya 'view_name' sözdizimiyle şablonları çözer.
 */
class View
{
    /**
     * Şablonu render eder ve çıktısını döndürür
     */
    public static function render(string $view, array $data = [], bool $returnAsString = false): string
    {
        $filePath = self::resolveViewPath($view);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("View dosyası bulunamadı: {$view} (Aranan yol: {$filePath})");
        }

        extract($data, EXTR_SKIP);

        if ($returnAsString) {
            ob_start();
            include $filePath;
            return ob_get_clean();
        }

        include $filePath;
        return '';
    }

    /**
     * View yolunu çözer (Örn: 'ExcelExport::modal' => 'Modules/ExcelExport/Views/modal.php')
     */
    public static function resolveViewPath(string $view): string
    {
        $baseDir = dirname(__DIR__); // c:\wamp64\www\taskmanager

        if (str_contains($view, '::')) {
            [$module, $viewName] = explode('::', $view, 2);
            return $baseDir . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . $viewName . '.php';
        }

        return $baseDir . DIRECTORY_SEPARATOR . 'View' . DIRECTORY_SEPARATOR . $view . '.php';
    }
}
