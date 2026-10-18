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
        'title' => __('app.export_excel', 'Excel (.csv) İndir'),
        'url'   => '/taskmanager/board/export-excel?project_id=' . $projectId,
        'order' => 10
    ];
    return $items;
});
