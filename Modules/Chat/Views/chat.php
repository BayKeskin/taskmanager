<?php
// TaskManager Ekip & Birebir Özel Sohbet (Direct Messaging & Channels)
$initialTargetUserId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$chatController = new \Modules\Chat\Controllers\ChatController();
$chatData = $chatController->index($initialTargetUserId);
extract($chatData);

$system->title = __('Chat::chat_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<div class="cu-view-header">
    <div class="cu-chat-header-bar">
        <div class="cu-chat-header-left">
            <div class="cu-chat-header-icon">
                💬
            </div>
            <div>
                <h1 class="cu-chat-header-title">
                    <?= ($currentUser->role === 'client') ? __('Chat::chat_header_client') : __('Chat::chat_header_team') ?>
                </h1>
                <p class="cu-chat-header-subtitle">
                    <?= ($currentUser->role === 'client') ? __('Chat::chat_desc_client') : __('Chat::chat_desc_team') ?>
                </p>
            </div>
        </div>
        <div class="cu-chat-header-right">
            <button type="button" id="btnToggleMembersSidebar" class="cu-btn-sm cu-mobile-toggle cu-chat-mobile-btn">
                <span>💬</span> <span><?= __('Chat::chat_btn_chats') ?></span>
            </button>
            <span class="cu-realtime-badge cu-chat-realtime-badge">
                <span class="cu-realtime-dot cu-chat-realtime-dot"></span>
                <span><?= __('Chat::chat_sse_active') ?></span>
            </span>
        </div>
    </div>
</div>

<div class="cu-chat-wrapper" data-initial-target-user-id="<?= (int)$initialTargetUserId ?>">
    <!-- SOL KENAR PANELİ: KANALLAR & BİREBİR ÖZEL SOHBETLER -->
    <div id="cuChatSidebar" class="cu-chat-sidebar">
        <div class="cu-chat-sidebar-mobile-header">
            <strong>💬 <?= __('Chat::chat_sidebar_mobile_title') ?></strong>
            <button type="button" id="btnCloseSidebarMobile" class="cu-btn-close-sidebar">✕</button>
        </div>
        
        <?php if ($currentUser->role !== 'client'): ?>
        <!-- GENEL KANALLAR BÖLÜMÜ -->
        <div>
            <div class="cu-chat-section-label">
                <?= __('Chat::chat_section_channels') ?>
            </div>
            <button type="button" id="btnChannelGeneral" class="cu-chat-channel-btn active">
                <span class="cu-channel-hash">#</span>
                <div class="cu-channel-info">
                    <div class="cu-channel-title"><?= __('Chat::chat_channel_general') ?></div>
                    <div class="cu-channel-sub"><?= __('Chat::chat_all_team') ?></div>
                </div>
                <span class="cu-channel-badge"><?= __('Chat::chat_general_badge') ?></span>
            </button>
        </div>

        <div class="cu-chat-divider"></div>
        <?php endif; ?>

        <!-- BİREBİR ÖZEL SOHBETLER (DIRECT MESSAGES) BÖLÜMÜ -->
        <div class="cu-chat-dm-section">
            <div class="cu-chat-dm-header">
                <div class="cu-chat-dm-label">
                    <span>🔒</span> <span><?= __('Chat::chat_section_dm') ?></span>
                </div>
                <span id="totalDmBadge" class="cu-unread-badge cu-hidden">0</span>
            </div>

            <!-- ÜYE ARAMA KUTUSU -->
            <div class="cu-chat-search-wrap">
                <input 
                    type="text" 
                    id="memberSearchInput" 
                    class="cu-chat-search-input"
                    placeholder="<?= htmlspecialchars(__('Chat::chat_search_member')) ?>" 
                >
                <span class="cu-chat-search-icon">🔍</span>
            </div>

            <!-- KİŞİLER LİSTESİ -->
            <div id="dmMembersList" class="cu-chat-members-list">
                <div class="cu-chat-loading-members"><?= __('Chat::loading') ?></div>
            </div>
        </div>
    </div>

    <!-- SOHBET ANA ALANI -->
    <div class="cu-chat-main-container">
        
        <!-- SOHBET BAŞLIĞI -->
        <div id="chatHeader" class="cu-chat-top-header">
            <div class="cu-chat-header-user">
                <div id="chatHeaderAvatar" class="cu-chat-avatar-box">
                    #
                </div>
                <div>
                    <div class="cu-chat-title-row">
                        <span id="chatHeaderTitle" class="cu-chat-top-title"># <?= __('Chat::chat_channel_general') ?></span>
                        <span id="chatHeaderBadge" class="cu-chat-top-badge"><?= __('Chat::chat_all_team') ?></span>
                    </div>
                    <p id="chatHeaderDesc" class="cu-chat-top-desc"><?= __('Chat::chat_header_general_desc') ?></p>
                </div>
            </div>

            <div class="cu-chat-top-actions">
                <button type="button" id="btnBackToGeneral" class="cu-btn-sm cu-chat-back-btn">
                    <span>↩️</span> <span><?= __('Chat::chat_btn_back_general') ?></span>
                </button>
                <div id="chatStatusText" class="cu-chat-status">
                    <?= __('Chat::loading') ?>
                </div>
            </div>
        </div>

        <!-- GİZLİLİK GÜVENCE BİLGİLENDİRMESİ (Özel Sohbet Modunda Görünür) -->
        <div id="directPrivacyNotice" class="cu-chat-privacy-box">
            <span>🔒</span>
            <span><b><?= __('Chat::chat_privacy_notice') ?></b></span>
        </div>

        <!-- MESAJ LİSTESİ -->
        <div id="chatMessagesContainer" class="cu-chat-messages-container">
            <div class="cu-chat-history-loader">
                <div class="cu-loader-icon">⏳</div>
                <?= __('Chat::chat_loading_history') ?>
            </div>
        </div>

        <!-- EN ALTA GİT BUTONU -->
        <button id="btnScrollBottom" type="button" class="cu-chat-scroll-bottom-btn">
            <span><?= __('Chat::chat_scroll_bottom') ?></span> <span>↓</span>
        </button>

        <!-- MESAJ GÖNDERME FORMU -->
        <div class="cu-chat-form-container">
            <!-- HIZLI EMOJİ ÇUBUĞU -->
            <div class="cu-chat-reactions-row">
                <span class="cu-chat-reaction-label"><?= __('Chat::chat_quick_reaction') ?></span>
                <button type="button" class="cu-emoji-chip" data-emoji="👍">👍</button>
                <button type="button" class="cu-emoji-chip" data-emoji="🚀">🚀</button>
                <button type="button" class="cu-emoji-chip" data-emoji="✅">✅</button>
                <button type="button" class="cu-emoji-chip" data-emoji="🎉">🎉</button>
                <button type="button" class="cu-emoji-chip" data-emoji="👀">👀</button>
                <button type="button" class="cu-emoji-chip" data-emoji="🔥">🔥</button>
                <button type="button" class="cu-emoji-chip" data-emoji="💡">💡</button>
            </div>

            <!-- SEÇİLEN DOSYA ÖNİZLEME ÇİPİ -->
            <div id="chatFilePreview" class="cu-chat-file-preview">
                <span>📎</span>
                <span id="chatFileName" class="cu-chat-filename"></span>
                <button type="button" id="btnClearChatFile" class="cu-chat-clear-file">✕</button>
            </div>

            <form id="chatForm" class="cu-chat-form">
                <!-- Ek Dosya Butonu -->
                <input type="file" id="chatFileInput" class="cu-hidden" accept="image/*,.pdf,.zip,.rar,.txt,.docx,.xlsx">
                <button type="button" id="btnTriggerFileInput" class="cu-chat-attach-btn" title="<?= htmlspecialchars(__('Chat::chat_attach_file')) ?>">
                    📎
                </button>

                <div class="cu-chat-input-wrapper">
                    <textarea 
                        id="chatInput" 
                        rows="1" 
                        class="cu-chat-textarea"
                        placeholder="<?= htmlspecialchars(__('Chat::chat_input_placeholder')) ?>" 
                    ></textarea>
                </div>
                <button type="submit" id="btnSendChat" class="cu-btn cu-btn-primary cu-chat-send-btn">
                    <span><?= __('Chat::chat_btn_send') ?></span>
                    <span>🚀</span>
                </button>
            </form>
            <div class="cu-chat-footer-info">
                <span id="chatHintText"><?= __('Chat::chat_hint_realtime') ?></span>
                <span id="charCount" class="cu-char-count"></span>
            </div>
        </div>

    </div>
</div>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
