<!-- OTONOM SMOKE TEST MODALI -->
<div class="cu-modal-overlay" id="modalSmokeTest">
    <div class="cu-modal cu-smoketest-modal">
        <div class="cu-modal-header cu-smoketest-header">
            <div class="cu-smoketest-title-wrap">
                <span class="cu-smoketest-icon">🔍</span>
                <h3 class="cu-smoketest-title"><?= __('SmokeTest::modal_title') ?></h3>
            </div>
            <button type="button" class="cu-modal-close-btn" data-close-modal="modalSmokeTest">&times;</button>
        </div>
        <div class="cu-modal-body cu-smoketest-body">
            <!-- Progress Bar -->
            <div class="cu-smoke-progress-wrap" id="smokeProgressWrap">
                <div class="cu-smoke-progress-bar" id="smokeProgressBar"></div>
            </div>
            <div class="cu-smoke-status-text" id="smokeStatusText">
                <?= __('SmokeTest::running_tests') ?>
            </div>

            <!-- Sonuç Listesi -->
            <div class="cu-smoketest-results-container" id="smokeTestResultsList">
            </div>
        </div>
        <div class="cu-modal-footer cu-smoketest-footer">
            <button type="button" class="cu-btn-sm cu-btn-secondary" id="btnRerunSmokeTest">
                <span>🔄</span> <span><?= __('SmokeTest::btn_run_smoketest') ?></span>
            </button>
            <button type="button" class="cu-btn-sm" data-close-modal="modalSmokeTest">
                <?= __('SmokeTest::btn_close') ?>
            </button>
        </div>
    </div>
</div>
