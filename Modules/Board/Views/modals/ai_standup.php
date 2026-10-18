<!-- GÜNLÜK AI STANDUP MODALI -->
<div class="cu-modal-overlay" id="modalAiStandup" onclick="if(event.target === this) closeModal('modalAiStandup')">
    <div class="cu-modal cu-ai-standup-modal">
        <div class="cu-modal-header cu-ai-standup-header">
            <div class="cu-ai-standup-title-wrap">
                <span class="cu-ai-standup-icon">🤖</span>
                <h3 class="cu-ai-standup-title"><?= __('Board::ai_standup_modal_title', 'Günlük AI Standup & Görev Özeti') ?></h3>
            </div>
            <button type="button" onclick="closeModal('modalAiStandup')" class="cu-modal-close-btn">&times;</button>
        </div>
        <div class="cu-modal-body cu-ai-standup-body" id="aiStandupContent">
        </div>
        <div class="cu-modal-footer cu-ai-standup-footer">
            <button type="button" class="cu-btn-sm cu-btn-secondary cu-ai-standup-email-btn" onclick="sendStandupEmail()">
                <span>📧</span> <span><?= __('Board::ai_standup_send_email_btn', 'E-Posta Bülteni Gönder') ?></span>
            </button>
            <button type="button" class="cu-btn-sm" onclick="closeModal('modalAiStandup')">
                <?= __('Board::modal_close', 'Kapat') ?>
            </button>
        </div>
    </div>
</div>
