<!-- GÜNLÜK AI STANDUP MODALI -->
<div class="cu-modal-overlay" id="modalAiStandup">
    <div class="cu-modal cu-ai-standup-modal">
        <div class="cu-modal-header cu-ai-standup-header">
            <div class="cu-ai-standup-title-wrap">
                <span class="cu-ai-standup-icon">🤖</span>
                <h3 class="cu-ai-standup-title"><?= __('Board::ai_standup_modal_title') ?></h3>
            </div>
            <button type="button" class="cu-modal-close-btn" data-close-modal="modalAiStandup">&times;</button>
        </div>
        <div class="cu-modal-body cu-ai-standup-body" id="aiStandupContent">
        </div>
        <div class="cu-modal-footer cu-ai-standup-footer">
            <?php if (\Core\ModuleLoader::isModuleActive('mailer')): ?>
            <button type="button" class="cu-btn-sm cu-btn-secondary cu-ai-standup-email-btn" id="btnSendStandupEmail">
                <span>📧</span> <span><?= __('Board::ai_standup_send_email_btn') ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="cu-btn-sm" data-close-modal="modalAiStandup">
                <?= __('Board::modal_close') ?>
            </button>
        </div>
    </div>
</div>
