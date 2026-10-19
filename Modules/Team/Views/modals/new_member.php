<!-- 👤 YENİ EKİP ÜYESİ EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewMember">
    <div class="cu-modal cu-team-modal">
        <div class="cu-modal-header cu-team-modal-header">
            <h3 class="cu-team-modal-title">
                <span>👤</span> <?= __('Team::team_modal_title') ?>
            </h3>
            <button type="button" class="cu-modal-close-btn btn-close-new-member">&times;</button>
        </div>

        <form id="formNewMember">
            <div class="cu-modal-body cu-team-modal-body">
                <div class="cu-team-form-group">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_name') ?></label>
                    <input type="text" name="name" class="cu-form-control cu-team-form-input" required placeholder="<?= htmlspecialchars(__('Team::team_modal_name_ph'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="cu-team-form-group">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_email') ?></label>
                    <input type="email" name="email" class="cu-form-control cu-team-form-input" required placeholder="<?= htmlspecialchars(__('Team::team_modal_email_ph'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="cu-team-form-group">
                    <div class="cu-team-pwd-label-wrap">
                        <label class="cu-team-form-label-inline"><?= __('Team::team_modal_pwd') ?></label>
                        <button type="button" id="btnGenRandomPass" class="cu-team-btn-random-pwd"><?= __('Team::team_modal_random_pwd') ?></button>
                    </div>
                    <input type="text" id="memberPassword" name="password" class="cu-form-control cu-team-form-input" required value="145236" placeholder="<?= htmlspecialchars(__('Team::team_modal_pwd_ph'), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="cu-team-form-grid-2">
                    <div>
                        <label class="cu-team-form-label"><?= __('Team::team_modal_role') ?></label>
                        <select name="role" id="memberRoleSelect" class="cu-form-control cu-team-form-select">
                            <option value="user" selected><?= __('Team::team_modal_role_user') ?></option>
                            <option value="admin"><?= __('Team::team_modal_role_admin') ?></option>
                            <option value="client"><?= __('Team::team_modal_role_client') ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="cu-team-form-label"><?= __('Team::team_modal_title_label') ?></label>
                        <input type="text" id="memberTitleInput" name="title" class="cu-form-control cu-team-form-input" value="<?= htmlspecialchars(__('Team::team_role_member'), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(__('Team::team_modal_title_ph'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <!-- MÜŞTERİ ROLÜ İÇİN PROJE SEÇİM ALANI -->
                <div id="clientProjectContainer" class="cu-team-client-box">
                    <label class="cu-team-client-box-label">
                        <?= __('Team::team_modal_client_proj') ?>
                    </label>
                    <select name="client_project_id" id="clientProjectSelect" class="cu-form-control cu-team-form-select">
                        <option value=""><?= __('Team::team_modal_client_proj_select') ?></option>
                        <?php if (!empty($allProjects)): foreach ($allProjects as $p): ?>
                            <option value="<?= $p->id ?>">[<?= htmlspecialchars($p->project_code) ?>] <?= htmlspecialchars($p->title) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <p class="cu-team-client-box-hint">
                        <?= __('Team::team_modal_client_proj_hint') ?>
                    </p>
                </div>

                <!-- KULLANIM DİLİ SEÇİMİ -->
                <div class="cu-team-form-group">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_lang') ?></label>
                    <select name="language" id="memberLanguageSelect" class="cu-form-control cu-team-form-select">
                        <option value="tr" selected>🇹🇷 Türkçe (Turkish)</option>
                        <option value="de">🇩🇪 Deutsch (German)</option>
                        <option value="en">🇬🇧 English (Global / American)</option>
                    </select>
                    <small class="cu-team-hint-text"><?= __('Team::team_modal_lang_hint') ?></small>
                </div>

                <div class="cu-team-color-picker-wrap">
                    <label class="cu-team-form-label"><?= __('Team::team_modal_avatar_color') ?></label>
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
                <button type="button" class="cu-btn-sm btn-close-new-member"><?= __('Team::team_modal_btn_cancel') ?></button>
                <button type="submit" id="btnSubmitMember" class="cu-btn-quick-task">
                    <span>💾</span>
                    <span><?= __('Team::team_modal_btn_submit') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>
