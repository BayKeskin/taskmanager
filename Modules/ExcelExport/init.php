<?php
/**
 * ExcelExport Modülü Başlatıcı (init.php)
 * Kancaları (Hooks) ve Rota dinleyicilerini kaydeder.
 */
use Core\EventManager;
use Modules\ExcelExport\Controllers\ExcelExportController;

// 1. Dışa aktarma eylemi için Event/Hook kaydı
EventManager::listen('export.excel', function(int $projectId) {
    $controller = new ExcelExportController();
    $controller->export($projectId);
});

// 2. Kanban Tools Menüsüne Buton Entegrasyon Filtresi
EventManager::addFilter('kanban.tools_menu', function(array $items, int $projectId) {
    $items[] = [
        'id'    => 'tool-excel-export',
        'icon'  => 'fas fa-file-excel',
        'title' => __('ExcelExport::export_btn'),
        'url'   => baseUrl('kanban/export-excel?project_id=' . $projectId),
        'order' => 10
    ];
    return $items;
});

// 3. Statik Asset Kayıtları (AssetManager)
if (class_exists('Core\AssetManager')) {
    \Core\AssetManager::enqueueCss('excel-export-css', baseUrl('Modules/ExcelExport/Assets/css/excel_export.css'));
    \Core\AssetManager::enqueueJs('excel-export-js', baseUrl('Modules/ExcelExport/Assets/js/excel_export.js'));
}
