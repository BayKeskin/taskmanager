<!-- 👤 YENİ EKİP ÜYESİ EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewMember">
    <div class="cu-modal cu-team-modal">
        <div class="cu-modal-header cu-team-modal-header">
            <h3 class="cu-team-modal-title">
                <span>👤</span> <?= __('Team::team_modal_title', 'Yeni Ekip Üyesi Ekle') ?>
            </h3>
            <button type="button" onclick="closeModal('modalNewMember')" class="cu-modal-close-btn">&times;</button>
        </div>

        <form id="formNewMember" onsubmit="submitNewMember(event)">
            <div class="cu-modal-body cu-team-modal-body">
                <div class="cu-team-form-group">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_name', 'Ad Soyad *') ?></label>
                    <input type="text" name="name" class="cu-form-control cu-team-form-input" required placeholder="<?= htmlspecialchars(__('Team::team_modal_name_ph', 'Örn: Ahmet Yılmaz'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="cu-team-form-group">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_email', 'E-Posta Adresi *') ?></label>
                    <input type="email" name="email" class="cu-form-control cu-team-form-input" required placeholder="<?= htmlspecialchars(__('Team::team_modal_email_ph', 'ornek@tkmdev.com'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="cu-team-form-group">
                    <div class="cu-team-pwd-label-wrap">
                        <label class="cu-team-form-label-inline"><?= __('Team::team_modal_pwd', 'Giriş Şifresi *') ?></label>
                        <button type="button" onclick="generateRandomPass()" class="cu-team-btn-random-pwd"><?= __('Team::team_modal_random_pwd', '🎲 Rastgele Üret') ?></button>
                    </div>
                    <input type="text" id="memberPassword" name="password" class="cu-form-control cu-team-form-input" required value="145236" placeholder="<?= htmlspecialchars(__('Team::team_modal_pwd_ph', 'Şifre'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="cu-team-form-grid-2">
                    <div>
                        <label class="cu-team-form-label"><?= __('Team::team_modal_role', 'Sistem Rolü *') ?></label>
                        <select name="role" id="memberRoleSelect" class="cu-form-control cu-team-form-select" onchange="handleMemberRoleChange(this.value)">
                            <option value="user" selected><?= __('Team::team_modal_role_user', 'Kullanıcı (Standart Ekip)') ?></option>
                            <option value="admin"><?= __('Team::team_modal_role_admin', 'Yönetici (Admin)') ?></option>
                            <option value="client"><?= __('Team::team_modal_role_client', 'Müşteri / Danışan (Client Portal)') ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="cu-team-form-label"><?= __('Team::team_modal_title_label', 'Pozisyon / Unvan') ?></label>
                        <input type="text" id="memberTitleInput" name="title" class="cu-form-control cu-team-form-input" value="Ekip Üyesi" placeholder="<?= htmlspecialchars(__('Team::team_modal_title_ph', 'Örn: Frontend Geliştirici'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <!-- MÜŞTERİ ROLÜ İÇİN PROJE SEÇİM ALANI -->
                <div id="clientProjectContainer" class="cu-team-client-box">
                    <label class="cu-team-client-box-label">
                        <?= __('Team::team_modal_client_proj', '📌 Müşterinin Erişebileceği Proje *') ?>
                    </label>
                    <select name="client_project_id" id="clientProjectSelect" class="cu-form-control cu-team-form-select">
                        <option value=""><?= __('Team::team_modal_client_proj_select', '-- Bir Proje Seçiniz --') ?></option>
                        <?php if (!empty($allProjects)): foreach ($allProjects as $p): ?>
                            <option value="<?= $p->id ?>">[<?= htmlspecialchars($p->project_code) ?>] <?= htmlspecialchars($p->title) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <p class="cu-team-client-box-hint">
                        <?= __('Team::team_modal_client_proj_hint', '🔒 Müşteri yalnızca bu seçilen projenin panosunu ve onay bekleyen teslimatlarını görebilir. İç yazışmalar gizlenir.') ?>
                    </p>
                </div>

                <!-- KULLANIM DİLİ SEÇİMİ -->
                <div class="cu-team-form-group">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_lang', '🌐 Kullanım Dili (Dil Tercihi) *') ?></label>
                    <select name="language" id="memberLanguageSelect" class="cu-form-control cu-team-form-select">
                        <option value="tr" selected>🇹🇷 Türkçe (Turkish)</option>
                        <option value="de">🇩🇪 Deutsch (German)</option>
                        <option value="en">🇬🇧 English (Global / American)</option>
                    </select>
                    <small class="cu-team-hint-text"><?= __('Team::team_modal_lang_hint', 'Eklenen kullanıcı giriş yaptığında panel doğrudan seçilen dille açılır.') ?></small>
                </div>

                <div class="cu-team-color-picker-wrap">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_avatar_color', 'Profil Rengi') ?></label>
                    <div class="cu-team-color-dots-flex">
                        <label class="cu-team-color-option"><input type="radio" name="avatar_color" value="#7B68EE" checked class="cu-team-color-radio"><span class="color-dot dot-purple"></span></label>
                        <label class="cu-team-color-option"><input type="radio" name="avatar_color" value="#06D6A0" class="cu-team-color-radio"><span class="color-dot dot-teal"></span></label>
                        <label class="cu-team-color-option"><input type="radio" name="avatar_color" value="#f59e0b" class="cu-team-color-radio"><span class="color-dot dot-amber"></span></label>
                        <label class="cu-team-color-option"><input type="radio" name="avatar_color" value="#ef4444" class="cu-team-color-radio"><span class="color-dot dot-red"></span></label>
                        <label class="cu-team-color-option"><input type="radio" name="avatar_color" value="#3b82f6" class="cu-team-color-radio"><span class="color-dot dot-blue"></span></label>
                        <label class="cu-team-color-option"><input type="radio" name="avatar_color" value="#ec4899" class="cu-team-color-radio"><span class="color-dot dot-pink"></span></label>
                    </div>
                </div>
            </div>

            <div class="cu-modal-footer cu-team-modal-footer">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalNewMember')"><?= __('Team::team_modal_btn_cancel', 'İptal') ?></button>
                <button type="submit" id="btnSubmitMember" class="cu-btn-quick-task">
                    <span>💾</span>
                    <span><?= __('Team::team_modal_btn_submit', 'Kaydet ve Ekle') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>
