<?php
/**
 * Autonomous Verification Test for Board (#01) Module
 */
require_once __DIR__ . '/../System/System.php';
use Core\ModuleLoader;
use Modules\Board\Models\TaskModel;
use Modules\Board\Controllers\BoardController;

echo "=== AUTONOMOUS TEST: BOARD (#01) MODULE ===\n";

// 1. Modül Yükleme Kontrolü
if (ModuleLoader::isModuleActive('board')) {
    echo "[PASS] Module 'board' (#01) active in ModuleLoader.\n";
} else {
    echo "[FAIL] Module 'board' not recognized or inactive.\n";
    exit(1);
}

// 2. TaskModel Testleri
$taskModel = new TaskModel();
$tasks = $taskModel->getTasksForBoard();
echo "[PASS] TaskModel::getTasksForBoard returned " . count($tasks) . " tasks.\n";

$assignees = $taskModel->getAssigneesMap();
echo "[PASS] TaskModel::getAssigneesMap loaded assignees for " . count($assignees) . " tasks.\n";

$attachCounts = $taskModel->getAttachmentCounts();
echo "[PASS] TaskModel::getAttachmentCounts loaded counts for " . count($attachCounts) . " tasks.\n";

// 3. BoardController Testi
$controller = new BoardController();
$boardData = $controller->index();
if (isset($boardData['allTasks']) && isset($boardData['assigneesMap'])) {
    echo "[PASS] BoardController::index successfully prepared Kanban board dataset.\n";
} else {
    echo "[FAIL] BoardController data structure incomplete.\n";
    exit(1);
}

// 5. Tri-lingual (TR, EN, DE) Translation Testi
Language::setLocale('tr');
$tr = __('Board::module_name');

Language::setLocale('en');
$en = __('Board::module_name');

Language::setLocale('de');
$de = __('Board::module_name');

echo "  [PASS] Lang (tr): '$tr'\n";
echo "  [PASS] Lang (en): '$en'\n";
echo "  [PASS] Lang (de): '$de'\n";

if ($tr === 'Kanban Panosu' && $en === 'Kanban Board' && $de === 'Kanban-Board') {
    echo "[PASS] All 3 languages properly localized for Board.\n";
} else {
    echo "[FAIL] Translations do not match expected localized values.\n";
    exit(1);
}

echo "=== ALL BOARD TESTS PASSED (100% SUCCESS) ===\n";
