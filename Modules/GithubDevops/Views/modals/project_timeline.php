<!-- 🕒 PROJE ZAMAN TÜNELİ & SÜRÜM GEÇMİŞİ (TIME MACHINE) MODALI -->
<div class="cu-modal-overlay" id="modalProjectTimeline">
    <div class="cu-modal tm-timeline-modal-dialog">
        <div class="cu-modal-header tm-timeline-modal-header">
            <div class="tm-header-left">
                <span class="tm-header-icon">🕒</span>
                <div>
                    <h3 class="tm-modal-title"><?= __('GithubDevops::modal_timeline_title') ?></h3>
                    <p class="tm-modal-subtitle" id="tmProjectSubtitle"><?= __('GithubDevops::timeline_desc') ?></p>
                </div>
            </div>
            <button type="button" class="gh-close-btn" id="btnTmCloseTop" title="<?= htmlspecialchars(__('GithubDevops::btn_close')) ?>">&times;</button>
        </div>
        <div class="cu-modal-body tm-timeline-modal-body">
            <!-- Yükleme / İskelet Durumu -->
            <div id="tmTimelineLoading" class="tm-timeline-loading">
                <div class="tm-spinner"></div>
                <p class="tm-loading-text"><?= __('GithubDevops::syncing') ?></p>
            </div>

            <!-- Boş Durum -->
            <div id="tmTimelineEmpty" class="tm-timeline-empty cu-hide">
                <span class="tm-empty-icon">📭</span>
                <p class="tm-empty-text"><?= __('GithubDevops::timeline_empty') ?></p>
            </div>

            <!-- Zaman Tüneli Akış Alanı -->
            <div id="tmTimelineList" class="tm-timeline-list cu-hide">
                <!-- Dinamik JavaScript ile doldurulacaktır -->
            </div>
        </div>
        <div class="cu-modal-footer tm-timeline-modal-footer">
            <button type="button" class="cu-btn-sm cu-btn-secondary" id="btnTmCloseBottom"><?= __('GithubDevops::btn_close') ?></button>
        </div>
    </div>
</div>
