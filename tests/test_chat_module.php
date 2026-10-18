<?php
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;
use Modules\Chat\Models\ChatModel;
use Modules\Chat\Controllers\ChatController as ModuleChatController;

echo "=== AUTONOMOUS TEST: CHAT (#20) MODULE ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('chat'), "Module 'chat' must be active.");
echo "[PASS] Module 'chat' (#20) active in ModuleLoader." . PHP_EOL;

// 2. ChatModel İşlemleri Testi
$chatModel = new ChatModel();
$adminUser = (object)['id' => 1, 'role' => 'admin', 'name' => 'Admin'];

// Mesaj kaydet
$testMessage = "Autonomous QA Message " . time();
$msgId = $chatModel->saveMessage(1, null, $testMessage, '');
assert($msgId > 0, "ChatModel::saveMessage failed.");
echo "[PASS] Successfully saved test chat message with ID: {$msgId}" . PHP_EOL;

// Mesajları çek
$messages = $chatModel->getMessages(1, 0, 0, 10);
assert(!empty($messages), "ChatModel::getMessages must return array.");
$found = false;
foreach ($messages as $m) {
    $mObj = (object)$m;
    if ((int)$mObj->id === $msgId) {
        $found = true;
        break;
    }
}
assert($found, "Recently inserted chat message must exist in getMessages result.");
echo "[PASS] Verified chat message retrieval from database." . PHP_EOL;

// Konuşmaları listele
$convData = $chatModel->getConversations(1, false);
assert(isset($convData['conversations']) && isset($convData['total_unread']), "ChatModel::getConversations structure valid.");
echo "[PASS] ChatModel::getConversations returned " . count($convData['conversations']) . " conversations." . PHP_EOL;

// 3. Controller Testi
$moduleCtrl = new ModuleChatController();
$viewData = $moduleCtrl->index(0, $adminUser);
assert(isset($viewData['teamUsers']), "ChatController::index must provide teamUsers.");
echo "[PASS] ChatController::index successfully prepared view data." . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $cName = __('Chat::module_name');
    $cTitle = __('Chat::chat_title');
    $cTeam = __('Chat::channel_team');
    
    echo "  [PASS] Lang ({$loc}): Module => '{$cName}' | Title => '{$cTitle}' | Team => '{$cTeam}'" . PHP_EOL;
    assert(!empty($cName) && !str_starts_with($cName, 'Chat::'), "Chat translation missing for {$loc}");
    assert(!empty($cTitle) && !str_starts_with($cTitle, 'Chat::'), "Chat translation missing for {$loc}");
    assert(!empty($cTeam) && !str_starts_with($cTeam, 'Chat::'), "Chat translation missing for {$loc}");
}



echo "=== ALL CHAT TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
