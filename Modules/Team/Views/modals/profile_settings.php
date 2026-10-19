<!-- 4.1 KULLANICI PROFİL AYARLARI MODALI -->
<?php 
$currentUser = $currentUser ?? ($_SESSION['user'] ?? null);
if ($currentUser): 
?>
<div class="cu-modal-overlay" id="modalProfileSettings">
    <div class="cu-modal cu-profile-modal-box">
        <div class="cu-modal-header cu-profile-modal-header">
            <h3 class="cu-profile-modal-title">
                <span>⚙️</span> <?= __('Team::modal_profile_title') ?>
            </h3>
            <button type="button" class="cu-profile-modal-close btn-close-profile-modal">&times;</button>
        </div>

        <form id="formProfileSettings" class="cu-profile-form">
            <div class="cu-modal-body cu-profile-modal-body">
                
                <!-- Canlı Profil Önizleme Kartı -->
                <div class="cu-profile-preview-card">
                    <div id="profileAvatarPreview" class="cu-avatar cu-profile-avatar-preview cu-avatar-<?= $currentUser->id ?>">
                        <?= mb_substr($currentUser->name ?? 'U', 0, 1) ?>
                    </div>
                    <div class="cu-profile-preview-info">
                        <div id="profileNamePreview" class="cu-profile-preview-name"><?= htmlspecialchars($currentUser->name ?? '') ?></div>
                        <div class="cu-profile-preview-email"><?= htmlspecialchars($currentUser->email ?? '') ?></div>
                        <div class="cu-profile-preview-badges">
                            <span class="cu-profile-badge-role <?= ($currentUser->role ?? '') === 'admin' ? 'cu-profile-badge-admin' : 'cu-profile-badge-user' ?>">
                                <?= ($currentUser->role ?? '') === 'admin' ? __('Team::role_admin') : __('Team::role_user') ?>
                            </span>
                            <span id="profileTitlePreview" class="cu-profile-preview-title"><?= htmlspecialchars($currentUser->title ?? __('Team::role_user')) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Ad Soyad & Pozisyon -->
                <div class="cu-profile-grid-2">
                    <div>
                        <label class="cu-profile-field-label"><?= __('Team::modal_profile_name') ?></label>
                        <input type="text" id="profileInputName" name="name" class="cu-form-control cu-profile-field-input" value="<?= htmlspecialchars($currentUser->name ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="cu-profile-field-label"><?= __('Team::modal_profile_title_role') ?></label>
                        <input type="text" id="profileInputTitle" name="title" class="cu-form-control cu-profile-field-input" value="<?= htmlspecialchars($currentUser->title ?? '') ?>" placeholder="<?= htmlspecialchars(__('Team::modal_profile_title_role_ph'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <!-- E-Posta -->
                <div class="cu-profile-field-group">
                    <label class="cu-profile-field-label"><?= __('Team::modal_profile_email') ?></label>
                    <input type="email" name="email" class="cu-form-control cu-profile-field-input" value="<?= htmlspecialchars($currentUser->email ?? '') ?>" required>
                </div>

                <!-- Kullanım Dili (Interface Language) -->
                <div class="cu-profile-field-group">
                    <label class="cu-profile-field-label">🌐 <?= __('Team::modal_profile_language') ?></label>
                    <select name="language" id="profileInputLanguage" class="cu-form-control cu-profile-field-input">
                        <?php 
                        $userLang = $currentUser->language ?? ($_SESSION['lang'] ?? ($_SESSION['admin_lang'] ?? 'tr')); 
                        ?>
                        <option value="tr" <?= $userLang === 'tr' ? 'selected' : '' ?>>🇹🇷 Türkçe (Turkish)</option>
                        <option value="de" <?= $userLang === 'de' ? 'selected' : '' ?>>🇩🇪 Deutsch (German)</option>
                        <option value="en" <?= $userLang === 'en' ? 'selected' : '' ?>>🇬🇧 English (American / Global)</option>
                    </select>
                    <small class="cu-team-hint-text"><?= __('Team::modal_profile_lang_hint') ?></small>
                </div>

                <!-- Avatar Rengi Seçimi -->
                <div class="cu-profile-colors-group">
                    <label class="cu-profile-field-label"><?= __('Team::modal_profile_avatar_color') ?></label>
                    <div class="cu-profile-colors-wrap">
                        <?php 
                        $colors = ['#7B68EE', '#06D6A0', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6', '#8b5cf6'];
                        $currColor = $currentUser->avatar_color ?? '#7B68EE';
                        foreach ($colors as $c): 
                        ?>
                            <label class="cu-profile-color-label">
                                <input type="radio" name="avatar_color" value="<?= $c ?>" <?= ($currColor === $c) ? 'checked' : '' ?> class="cu-team-color-radio profile-avatar-radio">
                                <span class="profile-color-dot cu-profile-color-dot <?= ($currColor === $c) ? 'cu-profile-color-active' : '' ?>" data-color="<?= $c ?>"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- API Anahtarı (Personal Access Token) -->
                <div class="cu-profile-api-box">
                    <div class="cu-profile-api-header">
                        <span class="cu-profile-api-title">
                            <span>⚡</span> <?= __('Team::modal_profile_api_token') ?>
                        </span>
                        <a href="<?= baseUrl('api-docs') ?>" target="_blank" class="cu-profile-api-link"><?= __('Team::modal_profile_api_doc') ?></a>
                    </div>
                    <div class="cu-profile-api-row">
                        <input type="text" id="profileApiTokenInput" readonly value="<?= htmlspecialchars($currentUser->api_token ?? __('Team::modal_profile_no_token')) ?>" class="cu-profile-api-input">
                        <button type="button" id="btnCopyApiToken" class="cu-btn-sm cu-profile-api-btn-copy" title="<?= htmlspecialchars(__('Team::modal_profile_copy'), ENT_QUOTES, 'UTF-8') ?>">
                            📋 <?= __('Team::modal_profile_copy') ?>
                        </button>
                    </div>
                </div>

                <!-- Şifre Değiştirme Bölümü -->
                <div class="cu-profile-pwd-box">
                    <div class="cu-profile-pwd-header">
                        <span class="cu-profile-pwd-title">
                            <span>🔒</span> <?= __('Team::modal_profile_pwd_update') ?>
                        </span>
                        <span class="cu-profile-pwd-optional"><?= __('Team::modal_profile_optional') ?></span>
                    </div>
                    <p class="cu-profile-pwd-desc"><?= __('Team::modal_profile_pwd_hint') ?></p>

                    <div class="cu-profile-pwd-field">
                        <label class="cu-profile-field-label"><?= __('Team::modal_profile_pwd_current') ?></label>
                        <div class="cu-profile-pwd-relative">
                            <input type="password" id="inputCurrentPassword" name="current_password" class="cu-form-control cu-profile-pwd-input" placeholder="<?= htmlspecialchars(__('Team::modal_profile_pwd_current_ph'), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" class="cu-profile-pwd-toggle btn-toggle-pass" data-target="inputCurrentPassword">👁️</button>
                        </div>
                    </div>

                    <div class="cu-profile-pwd-grid">
                        <div>
                            <label class="cu-profile-field-label"><?= __('Team::modal_profile_pwd_new') ?></label>
                            <div class="cu-profile-pwd-relative">
                                <input type="password" id="inputNewPassword" name="new_password" class="cu-form-control cu-profile-pwd-input" placeholder="<?= htmlspecialchars(__('Team::modal_profile_pwd_new_ph'), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="button" class="cu-profile-pwd-toggle btn-toggle-pass" data-target="inputNewPassword">👁️</button>
                            </div>
                        </div>
                        <div>
                            <label class="cu-profile-field-label"><?= __('Team::modal_profile_pwd_confirm') ?></label>
                            <div class="cu-profile-pwd-relative">
                                <input type="password" id="inputNewPasswordConfirm" name="new_password_confirm" class="cu-form-control cu-profile-pwd-input" placeholder="<?= htmlspecialchars(__('Team::modal_profile_pwd_confirm_ph'), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="button" class="cu-profile-pwd-toggle btn-toggle-pass" data-target="inputNewPasswordConfirm">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="cu-modal-footer cu-profile-modal-footer">
                <button type="button" class="cu-btn cu-btn-secondary cu-profile-btn-cancel btn-close-profile-modal"><?= __('Team::team_modal_btn_cancel') ?></button>
                <button type="submit" id="btnSubmitProfile" class="cu-btn cu-btn-primary cu-profile-btn-save">
                    <span>💾</span>
                    <span><?= __('Team::modal_profile_save') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
