<?php
// TaskManager Ekip & Birebir Özel Sohbet (Direct Messaging & Channels)
$initialTargetUserId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$chatController = new \Modules\Chat\Controllers\ChatController();
$chatData = $chatController->index($initialTargetUserId);
extract($chatData);

$system->title = __('Chat::chat_title', 'Ekip İçi Sohbet - TaskManager');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<div class="cu-view-header">
    <div style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:0 4px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg, #7B68EE, #00b894); display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; box-shadow:0 4px 12px rgba(123,104,238,0.25);">
                💬
            </div>
            <div>
                <h1 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;">
                    <?= ($currentUser->role === 'client') ? __('tasks.chat_header_client') : __('tasks.chat_header_team') ?>
                </h1>
                <p style="font-size:11px; color:var(--text-muted); margin:2px 0 0 0;">
                    <?= ($currentUser->role === 'client') ? __('tasks.chat_desc_client') : __('tasks.chat_desc_team') ?>
                </p>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" class="cu-btn-sm cu-mobile-toggle" onclick="toggleMembersSidebar()" style="display:none; align-items:center; gap:6px;">
                <span>💬</span> <span><?= __('tasks.chat_btn_chats') ?></span>
            </button>
            <span class="cu-realtime-badge" style="background:rgba(0,184,148,0.12); border-color:rgba(0,184,148,0.3); color:#00b894; font-size:11.5px; padding:4px 10px; border-radius:20px;">
                <span class="cu-realtime-dot" style="background:#00b894;"></span>
                <span><?= __('tasks.chat_sse_active') ?></span>
            </span>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
