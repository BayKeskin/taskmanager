<!-- 4.1 KULLANICI PROFİL AYARLARI MODALI -->
<?php if ($currentUser): ?>
<div class="cu-modal-overlay" id="modalProfileSettings" onclick="if(event.target === this) closeProfileModal()">
    <div class="cu-modal" style="max-width:540px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0;">
            <h3 style="font-size:15px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; margin:0;">
                <span>⚙️</span> <?= __('app.modal_profile_title') ?>
            </h3>
            <button type="button" onclick="closeProfileModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px; line-height:1;">&times;</button>
        </div>

        <form id="formProfileSettings" onsubmit="handleUpdateProfile(event)" style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden; margin:0;">
            <div class="cu-modal-body" style="padding:18px 20px; overflow-y:auto; flex:1;">
                
                <!-- Canlı Profil Önizleme Kartı -->
                <div style="display:flex; align-items:center; gap:14px; padding:14px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:var(--radius-md); margin-bottom:16px;">
                    <div id="profileAvatarPreview" class="cu-avatar" style="width:50px; height:50px; font-size:20px; font-weight:700; background:<?= htmlspecialchars($currentUser->avatar_color ?? '#7B68EE') ?>; box-shadow:0 4px 12px rgba(0,0,0,0.3); flex-shrink:0;">
                        <?= mb_substr($currentUser->name ?? 'U', 0, 1) ?>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div id="profileNamePreview" style="font-size:14px; font-weight:700; color:var(--text-white); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($currentUser->name ?? '') ?></div>
                        <div style="font-size:11.5px; color:var(--text-muted); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($currentUser->email ?? '') ?></div>
                        <div style="margin-top:5px; display:flex; gap:6px; align-items:center;">
                            <span style="font-size:10px; font-weight:700; text-transform:uppercase; padding:2px 8px; border-radius:4px; <?= ($currentUser->role ?? '') === 'admin' ? 'background:rgba(123,104,238,0.2); color:#7B68EE;' : 'background:rgba(255,255,255,0.06); color:var(--text-secondary);' ?>">
                                <?= ($currentUser->role ?? '') === 'admin' ? __('app.layout_role_admin') : __('app.layout_role_member') ?>
                            </span>
                            <span id="profileTitlePreview" style="font-size:11px; color:var(--text-secondary);"><?= htmlspecialchars($currentUser->title ?? __('app.layout_role_member')) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Ad Soyad & Pozisyon -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_profile_name') ?></label>
                        <input type="text" id="profileInputName" name="name" class="cu-form-control" value="<?= htmlspecialchars($currentUser->name ?? '') ?>" required oninput="updateProfileAvatarPreview()" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_profile_title_role') ?></label>
                        <input type="text" id="profileInputTitle" name="title" class="cu-form-control" value="<?= htmlspecialchars($currentUser->title ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.modal_profile_title_role_ph')) ?>" oninput="document.getElementById('profileTitlePreview').innerText = this.value || '<?= htmlspecialchars(__('app.layout_role_member')) ?>'" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                    </div>
                </div>

                <!-- E-Posta -->
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_profile_email') ?></label>
                    <input type="email" name="email" class="cu-form-control" value="<?= htmlspecialchars($currentUser->email ?? '') ?>" required style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                </div>

                <!-- Kullanım Dili (Interface Language) -->
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;">🌐 <?= __('app.modal_profile_language') ?></label>
                    <select name="language" id="profileInputLanguage" class="cu-form-control" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                        <?php 
                        $userLang = $currentUser->language ?? ($_SESSION['lang'] ?? ($_SESSION['admin_lang'] ?? 'tr')); 
                        ?>
                        <option value="tr" <?= $userLang === 'tr' ? 'selected' : '' ?>>🇹🇷 Türkçe (Turkish)</option>
                        <option value="de" <?= $userLang === 'de' ? 'selected' : '' ?>>🇩🇪 Deutsch (German)</option>
                        <option value="en" <?= $userLang === 'en' ? 'selected' : '' ?>>🇬🇧 English (American / Global)</option>
                    </select>
                    <small style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;"><?= __('app.modal_profile_lang_hint') ?></small>
                </div>

                <!-- Avatar Rengi Seçimi -->
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:8px;"><?= __('app.modal_profile_avatar_color') ?></label>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <?php 
                        $colors = ['#7B68EE', '#06D6A0', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899', '#14b8a6', '#8b5cf6'];
                        $currColor = $currentUser->avatar_color ?? '#7B68EE';
                        foreach ($colors as $c): 
                        ?>
                            <label style="cursor:pointer; margin:0;">
                                <input type="radio" name="avatar_color" value="<?= $c ?>" <?= ($currColor === $c) ? 'checked' : '' ?> style="display:none;" onchange="selectProfileColor('<?= $c ?>')">
                                <span class="profile-color-dot" data-color="<?= $c ?>" style="display:inline-block; width:28px; height:28px; border-radius:50%; background:<?= $c ?>; border:2px solid <?= ($currColor === $c) ? '#fff' : 'transparent' ?>; box-shadow:0 2px 5px rgba(0,0,0,0.2); transition:var(--transition); vertical-align:middle;"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- API Anahtarı (Personal Access Token) -->
                <div style="background:rgba(123,104,238,0.06); border:1px solid rgba(123,104,238,0.25); border-radius:var(--radius-md); padding:12px 14px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:12px; font-weight:700; color:#7B68EE; display:flex; align-items:center; gap:6px;">
                            <span>⚡</span> <?= __('app.modal_profile_api_token') ?>
                        </span>
                        <a href="/taskmanager/api-docs" target="_blank" style="font-size:11px; color:var(--brand-primary); text-decoration:none; font-weight:600;"><?= __('app.modal_profile_api_doc') ?></a>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="profileApiTokenInput" readonly value="<?= htmlspecialchars($currentUser->api_token ?? 'Token oluşturulmadı') ?>" style="flex:1; height:34px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 10px; color:var(--text-white); font-family:monospace; font-size:11.5px; outline:none;">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('profileApiTokenInput').value); if(typeof showToast==='function') showToast('API Token kopyalandı!','success'); else alert('Token kopyalandı!');" class="cu-btn-sm" style="background:rgba(255,255,255,0.08); border-color:var(--border-subtle); color:var(--text-white); padding:6px 12px; cursor:pointer;" title="<?= htmlspecialchars(__('app.modal_profile_copy')) ?>">
                            📋 <?= __('app.modal_profile_copy') ?>
                        </button>
                    </div>
                </div>

                <!-- Şifre Değiştirme Bölümü -->
                <div style="background:rgba(0,0,0,0.22); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:14px; margin-bottom:6px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-size:12.5px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:6px;">
                            <span>🔒</span> <?= __('app.modal_profile_pwd_update') ?>
                        </span>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_profile_optional') ?></span>
                    </div>
                    <p style="font-size:11.5px; color:var(--text-muted); margin:0 0 12px 0;"><?= __('app.modal_profile_pwd_hint') ?></p>

                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-bottom:5px;"><?= __('app.modal_profile_pwd_current') ?></label>
                        <div style="position:relative;">
                            <input type="password" id="inputCurrentPassword" name="current_password" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_profile_pwd_current_ph')) ?>" style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 36px 0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                            <button type="button" onclick="togglePassVisibility('inputCurrentPassword', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="display:block; font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-bottom:5px;"><?= __('app.modal_profile_pwd_new') ?></label>
                            <div style="position:relative;">
                                <input type="password" id="inputNewPassword" name="new_password" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_profile_pwd_new_ph')) ?>" style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 36px 0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                                <button type="button" onclick="togglePassVisibility('inputNewPassword', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-bottom:5px;"><?= __('app.modal_profile_pwd_confirm') ?></label>
                            <div style="position:relative;">
                                <input type="password" id="inputNewPasswordConfirm" name="new_password_confirm" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_profile_pwd_confirm_ph')) ?>" style="width:100%; height:36px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 36px 0 12px; color:var(--text-primary); font-size:13px; box-sizing:border-box;">
                                <button type="button" onclick="togglePassVisibility('inputNewPasswordConfirm', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:4px;">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="cu-modal-footer" style="padding:12px 20px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border-subtle); background:rgba(0,0,0,0.15); flex-shrink:0;">
                <button type="button" class="cu-btn cu-btn-secondary" onclick="closeProfileModal()" style="padding:8px 16px; font-size:13px;"><?= __('app.modal_cancel') ?></button>
                <button type="submit" id="btnSubmitProfile" class="cu-btn cu-btn-primary" style="padding:8px 20px; font-size:13px; font-weight:600;">
                    <span>💾</span>
                    <span><?= __('app.modal_profile_save') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
