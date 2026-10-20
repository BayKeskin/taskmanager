<?php
/**
 * TaskManager - Modül Devre Dışı (Module Disabled) Ekranı
 */
$pageTitle = __('app.module_disabled_title') ?: 'Modül Devre Dışı';
include __DIR__ . '/header.php';
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap">
        <span class="cu-view-icon-lg">⚠️</span>
        <span class="cu-view-title-main"><?= __('app.module_disabled_title') ?: 'Modül Devre Dışı' ?></span>
    </div>
    <div class="cu-filter-bar">
        <a href="<?= baseUrl('board') ?>" class="cu-btn-sm cu-btn-secondary">
            <span>📌</span> <?= __('app.sidebar_board') ?: 'Panoya Dön' ?>
        </a>
    </div>
</div>

<div class="cu-main-centered-container" style="flex:1; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
    <div class="cu-card-disabled-notice" style="max-width:520px; width:100%; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:36px; text-align:center; box-shadow:var(--shadow-card);">
        <div style="font-size:48px; margin-bottom:16px;">🔌</div>
        <h2 style="font-size:20px; font-weight:700; color:var(--text-white); margin-bottom:10px;">
            <?= __('app.module_disabled_heading') ?: 'Bu Modül Şu Anda Devre Dışı' ?>
        </h2>
        <p style="font-size:13.5px; color:var(--text-secondary); line-height:1.6; margin-bottom:24px;">
            <?= __('app.module_disabled_desc') ?: 'Erişmek istediğiniz sistem modülü yönetici tarafından kapatılmıştır. Bu modülü tekrar kullanabilmek için Ayarlar > Modül Yöneticisi sekmesinden aktif edebilirsiniz.' ?>
        </p>
        <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
            <a href="<?= baseUrl('board') ?>" class="cu-btn cu-btn-primary" style="display:inline-flex; align-items:center; gap:8px; padding:8px 18px; font-weight:600; text-decoration:none; border-radius:6px;">
                <span>📌</span> <?= __('app.sidebar_board') ?: 'Panoya Dön' ?>
            </a>
            <?php if (!empty($_SESSION['user']) && $_SESSION['user']->role === 'admin'): ?>
                <a href="<?= baseUrl('settings?tab=modules') ?>" class="cu-btn cu-btn-secondary" style="display:inline-flex; align-items:center; gap:8px; padding:8px 18px; font-weight:600; text-decoration:none; border-radius:6px; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-white);">
                    <span>⚙️</span> <?= __('Settings::settings_tab_modules') ?: 'Modül Yöneticisi' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
