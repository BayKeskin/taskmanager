<?php
// TaskManager Ekip Yönetimi Sayfası (Team View)
require_once __DIR__ . '/../../Controller/TeamController.php';

$system->title = __('app.team_title', 'Ekip Yönetimi - TaskManager');
include __DIR__ . '/../../Components/header.php';

$teamController = new TeamController($system);
$teamData = $teamController->index();
if (!$teamData['isAuthorized']) {
    echo '<div style="padding:40px; text-align:center; color:var(--text-muted);">' . __('app.team_unauthorized', 'Bu sayfaya erişim yetkiniz bulunmamaktadır.') . '</div>';
    include __DIR__ . '/../../Components/footer.php';
    exit;
}
extract($teamData);
?>

<div class="cu-view-header">
    <div class="cu-view-tabs">
        <a href="/taskmanager/board" class="cu-view-tab">
            <span>📌</span>
            <span><?= __('app.team_tab_board', 'Kanban Board') ?></span>
        </a>
        <a href="/taskmanager/tasks" class="cu-view-tab">
            <span>📋</span>
            <span><?= __('app.team_tab_list', 'Liste Görünümü') ?></span>
        </a>
        <a href="/taskmanager/reports" class="cu-view-tab">
            <span>📊</span>
            <span><?= __('app.team_tab_reports', 'Analitik & SLA') ?></span>
        </a>
        <a href="/taskmanager/team" class="cu-view-tab active">
            <span>👥</span>
            <span><?= __('app.team_tab_team', 'Ekip Yönetimi & Müşteriler') ?></span>
        </a>
    </div>
</div>

<div class="cu-content-scroll">
    <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div>
                <h2 style="font-size:16px; font-weight:700; color:var(--text-white);"><?= __('app.team_heading', 'Çalışanlar ve Müşteri Portalı Kullanıcıları') ?></h2>
                <p style="font-size:12.5px; color:var(--text-secondary); margin-top:2px;"><?= __('app.team_desc', 'Görev ataması yapılabilecek ekip üyeleri ve yalnızca kendi projesine erişebilen danışan/müşteriler.') ?></p>
            </div>
            <button id="btnOpenNewMember" type="button" class="cu-btn-quick-task" onclick="openNewMemberModal()" style="height:38px; padding:0 18px; font-size:13px;">
                <span>➕</span>
                <span><?= __('app.team_btn_new_member', 'Yeni Üye / Müşteri Ekle') ?></span>
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border-subtle); text-align:left; color:var(--text-muted); font-size:11px; text-transform:uppercase;">
                        <th style="padding:10px 14px;"><?= __('app.team_th_user', 'Kullanıcı') ?></th>
                        <th style="padding:10px 14px;"><?= __('app.team_th_email', 'E-Posta') ?></th>
                        <th style="padding:10px 14px;"><?= __('app.team_th_title', 'Unvan / Pozisyon') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('app.team_th_role', 'Sistem Rolü') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('app.team_th_lang', 'Dil') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('app.team_th_project', 'Eriştiği Proje') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('app.team_th_status', 'Durum') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('app.team_th_tasks', 'Atanan Görevler') ?></th>
                        <th style="padding:10px 14px; text-align:right;"><?= __('app.team_th_action', 'İşlem') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): 
                        $taskCount = $userTaskCounts[$u->id] ?? 0;
                        $assignedProj = ($u->role === 'client' && !empty($u->client_project_id)) ? ($projectsMap[$u->client_project_id] ?? null) : null;
                    ?>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:12px 14px; display:flex; align-items:center; gap:10px;">
                                <div class="cu-avatar" style="width:32px; height:32px; font-size:12px; font-weight:700; background:<?= htmlspecialchars($u->avatar_color ?: '#7B68EE') ?>;">
                                    <?= mb_substr($u->name, 0, 1) ?>
                                </div>
                                <div>
                                    <span style="font-weight:600; color:var(--text-primary); display:block;"><?= htmlspecialchars($u->name) ?></span>
                                    <span style="font-size:11px; color:var(--text-muted);">ID: #<?= $u->id ?></span>
                                </div>
                            </td>
                            <td style="padding:12px 14px; color:var(--text-secondary);"><?= htmlspecialchars($u->email) ?></td>
                            <td style="padding:12px 14px; color:var(--text-primary); font-weight:500;"><?= htmlspecialchars($u->title) ?></td>
                            <td style="padding:12px 14px; text-align:center;">
                                <?php if ($u->role === 'admin'): ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:var(--radius-sm); font-size:11px; font-weight:700; text-transform:uppercase; background:rgba(123,104,238,0.2); color:#7B68EE; border:1px solid rgba(123,104,238,0.3);">
                                        <?= __('app.team_role_admin', 'Yönetici') ?>
                                    </span>
                                <?php elseif ($u->role === 'client'): ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:var(--radius-sm); font-size:11px; font-weight:700; text-transform:uppercase; background:rgba(0,184,148,0.18); color:#00b894; border:1px solid rgba(0,184,148,0.3);">
                                        <?= __('app.team_role_client', '👤 Müşteri') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:var(--radius-sm); font-size:11px; font-weight:700; text-transform:uppercase; background:var(--border-subtle); color:var(--text-secondary);">
                                        <?= __('app.team_role_member', 'Ekip Üyesi') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 14px; text-align:center;">
                                <?php
                                $langCode = $u->language ?? 'tr';
                                $flags = ['tr' => '🇹🇷 TR', 'de' => '🇩🇪 DE', 'en' => '🇬🇧 EN'];
                                ?>
                                <span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700; background:rgba(255,255,255,0.06); color:var(--text-secondary); border:1px solid var(--border-subtle);">
                                    <?= $flags[$langCode] ?? strtoupper($langCode) ?>
                                </span>
                            </td>
                            <td style="padding:12px 14px; text-align:center;">
                                <?php if ($u->role === 'client' && $assignedProj): ?>
                                    <span class="cu-badge-project" style="color:<?= htmlspecialchars($assignedProj->color ?: '#00b894') ?>;">
                                        📁 <?= htmlspecialchars($assignedProj->title) ?>
                                    </span>
                                <?php elseif ($u->role === 'client'): ?>
                                    <span style="color:#e67e22; font-size:11px;"><?= __('app.team_no_project', '⚠️ Proje atanmamış') ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;"><?= __('app.team_all_projects', 'Tüm Projeler') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 14px; text-align:center;">
                                <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; color:<?= $u->status === 'active' ? '#00b894' : '#ff7675' ?>;">
                                    <span style="width:7px; height:7px; border-radius:50%; background:currentColor;"></span>
                                    <?= $u->status === 'active' ? __('app.team_status_active', 'Aktif') : __('app.team_status_passive', 'Pasif') ?>
                                </span>
                            </td>
                            <td style="padding:12px 14px; text-align:center; font-weight:600; color:var(--brand-primary);">
                                <?= __('app.team_tasks_count', ['count' => $taskCount], $taskCount . ' Görev') ?>
                            </td>
                            <td style="padding:12px 14px; text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:6px;">
                                    <?php if ($u->role === 'client'): ?>
                                        <button type="button" class="cu-btn cu-btn-secondary" onclick="copyClientInvite('<?= htmlspecialchars(addslashes($u->name)) ?>', '<?= htmlspecialchars(addslashes($u->email)) ?>', '<?= htmlspecialchars(addslashes($assignedProj->title ?? 'Belirlenen Proje')) ?>')" style="padding:4px 8px; font-size:11.5px; color:#00b894; border-color:rgba(0,184,148,0.3);" title="<?= htmlspecialchars(__('app.team_btn_invite_title', 'Müşteri Giriş Davet Metnini Kopyala'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= __('app.team_btn_invite', '✉️ Davet') ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($u->id != $currentUser->id): ?>
                                        <button type="button" class="cu-btn cu-btn-secondary" onclick="deleteUser(<?= $u->id ?>, '<?= htmlspecialchars(addslashes($u->name)) ?>')" style="padding:4px 10px; font-size:11.5px; color:#ff7675; border-color:rgba(255,118,117,0.3);">
                                            <?= __('app.team_btn_delete', '🗑️ Sil') ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:var(--text-muted); font-style:italic;"><?= __('app.team_current_account', '(Mevcut Hesap)') ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div>
</div>

<!-- YENİ EKİP ÜYESİ MODALI -->
<div class="cu-modal-overlay" id="modalNewMember">
    <div class="cu-modal" style="max-width:480px;">
        <div class="cu-modal-header">
            <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0; display:flex; align-items:center; gap:8px;">
                <span>👤</span> <?= __('app.team_modal_title', 'Yeni Ekip Üyesi Ekle') ?>
            </h3>
            <button type="button" onclick="closeModal('modalNewMember')" style="background:none; border:none; color:var(--text-muted); font-size:18px; cursor:pointer;">&times;</button>
        </div>

        <form id="formNewMember" onsubmit="submitNewMember(event)">
            <div class="cu-modal-body" style="padding:16px 20px;">
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.team_modal_name', 'Ad Soyad *') ?></label>
                    <input type="text" name="name" class="cu-form-control" required placeholder="<?= htmlspecialchars(__('app.team_modal_name_ph', 'Örn: Ahmet Yılmaz'), ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.team_modal_email', 'E-Posta Adresi *') ?></label>
                    <input type="email" name="email" class="cu-form-control" required placeholder="<?= htmlspecialchars(__('app.team_modal_email_ph', 'ornek@tkmdev.com'), ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="font-size:12px; font-weight:600; color:var(--text-secondary); margin:0;"><?= __('app.team_modal_pwd', 'Giriş Şifresi *') ?></label>
                        <button type="button" onclick="generateRandomPass()" style="background:none; border:none; color:var(--brand-primary); font-size:11px; cursor:pointer; padding:0;"><?= __('app.team_modal_random_pwd', '🎲 Rastgele Üret') ?></button>
                    </div>
                    <input type="text" id="memberPassword" name="password" class="cu-form-control" required value="145236" placeholder="<?= htmlspecialchars(__('app.team_modal_pwd_ph', 'Şifre'), ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.team_modal_role', 'Sistem Rolü *') ?></label>
                        <select name="role" id="memberRoleSelect" class="cu-form-control" onchange="handleMemberRoleChange(this.value)" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 10px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                            <option value="user" selected><?= __('app.team_modal_role_user', 'Kullanıcı (Standart Ekip)') ?></option>
                            <option value="admin"><?= __('app.team_modal_role_admin', 'Yönetici (Admin)') ?></option>
                            <option value="client"><?= __('app.team_modal_role_client', 'Müşteri / Danışan (Client Portal)') ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.team_modal_title_label', 'Pozisyon / Unvan') ?></label>
                        <input type="text" id="memberTitleInput" name="title" class="cu-form-control" value="Ekip Üyesi" placeholder="<?= htmlspecialchars(__('app.team_modal_title_ph', 'Örn: Frontend Geliştirici'), ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 12px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                    </div>
                </div>

                <!-- MÜŞTERİ ROLÜ İÇİN PROJE SEÇİM ALANI -->
                <div id="clientProjectContainer" style="display:none; margin-bottom:14px; background:rgba(123, 104, 238, 0.06); padding:12px; border-radius:8px; border:1px dashed rgba(123, 104, 238, 0.35);">
                    <label style="display:block; font-size:12px; font-weight:700; color:var(--brand-primary); margin-bottom:6px;">
                        <?= __('app.team_modal_client_proj', '📌 Müşterinin Erişebileceği Proje *') ?>
                    </label>
                    <select name="client_project_id" id="clientProjectSelect" class="cu-form-control" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 10px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                        <option value=""><?= __('app.team_modal_client_proj_select', '-- Bir Proje Seçiniz --') ?></option>
                        <?php foreach ($allProjects as $p): ?>
                            <option value="<?= $p->id ?>">[<?= htmlspecialchars($p->project_code) ?>] <?= htmlspecialchars($p->title) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size:11px; color:var(--text-muted); margin:6px 0 0 0;">
                        <?= __('app.team_modal_client_proj_hint', '🔒 Müşteri yalnızca bu seçilen projenin panosunu ve onay bekleyen teslimatlarını görebilir. İç yazışmalar gizlenir.') ?>
                    </p>
                </div>

                <!-- KULLANIM DİLİ SEÇİMİ -->
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.team_modal_lang', '🌐 Kullanım Dili (Dil Tercihi) *') ?></label>
                    <select name="language" id="memberLanguageSelect" class="cu-form-control" style="width:100%; height:38px; background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:6px; padding:0 10px; color:var(--text-primary); font-size:13px; outline:none; box-sizing:border-box;">
                        <option value="tr" selected>🇹🇷 Türkçe (Turkish)</option>
                        <option value="de">🇩🇪 Deutsch (German)</option>
                        <option value="en">🇬🇧 English (Global / American)</option>
                    </select>
                    <small style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;"><?= __('app.team_modal_lang_hint', 'Eklenen kullanıcı giriş yaptığında panel doğrudan seçilen dille açılır.') ?></small>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.team_modal_avatar_color', 'Profil Rengi') ?></label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <label style="cursor:pointer;"><input type="radio" name="avatar_color" value="#7B68EE" checked style="display:none;"><span style="display:inline-block; width:26px; height:26px; border-radius:50%; background:#7B68EE; border:2px solid transparent;" class="color-dot"></span></label>
                        <label style="cursor:pointer;"><input type="radio" name="avatar_color" value="#06D6A0" style="display:none;"><span style="display:inline-block; width:26px; height:26px; border-radius:50%; background:#06D6A0; border:2px solid transparent;" class="color-dot"></span></label>
                        <label style="cursor:pointer;"><input type="radio" name="avatar_color" value="#f59e0b" style="display:none;"><span style="display:inline-block; width:26px; height:26px; border-radius:50%; background:#f59e0b; border:2px solid transparent;" class="color-dot"></span></label>
                        <label style="cursor:pointer;"><input type="radio" name="avatar_color" value="#ef4444" style="display:none;"><span style="display:inline-block; width:26px; height:26px; border-radius:50%; background:#ef4444; border:2px solid transparent;" class="color-dot"></span></label>
                        <label style="cursor:pointer;"><input type="radio" name="avatar_color" value="#3b82f6" style="display:none;"><span style="display:inline-block; width:26px; height:26px; border-radius:50%; background:#3b82f6; border:2px solid transparent;" class="color-dot"></span></label>
                        <label style="cursor:pointer;"><input type="radio" name="avatar_color" value="#ec4899" style="display:none;"><span style="display:inline-block; width:26px; height:26px; border-radius:50%; background:#ec4899; border:2px solid transparent;" class="color-dot"></span></label>
                    </div>
                </div>
            </div>

            <div class="cu-modal-footer" style="padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalNewMember')"><?= __('app.team_modal_btn_cancel', 'İptal') ?></button>
                <button type="submit" id="btnSubmitMember" class="cu-btn-quick-task">
                    <span>💾</span>
                    <span><?= __('app.team_modal_btn_submit', 'Kaydet ve Ekle') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openNewMemberModal() {
    openModal('modalNewMember');
}

function closeNewMemberModal() {
    closeModal('modalNewMember');
}

function generateRandomPass() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('memberPassword').value = pass;
}

document.querySelectorAll('.color-dot').forEach(dot => {
    dot.addEventListener('click', function() {
        document.querySelectorAll('.color-dot').forEach(d => d.style.borderColor = 'transparent');
        this.style.borderColor = '#fff';
    });
});
// İlk seçili renk
document.querySelector('.color-dot').style.borderColor = '#fff';

function handleMemberRoleChange(role) {
    const projContainer = document.getElementById('clientProjectContainer');
    const projSelect = document.getElementById('clientProjectSelect');
    const titleInput = document.getElementById('memberTitleInput');
    
    if (role === 'client') {
        projContainer.style.display = 'block';
        projSelect.required = true;
        if (titleInput && (titleInput.value === 'Ekip Üyesi' || titleInput.value === '')) {
            titleInput.value = 'Müşteri / Danışan';
        }
    } else {
        projContainer.style.display = 'none';
        projSelect.required = false;
        projSelect.value = '';
        if (titleInput && titleInput.value === 'Müşteri / Danışan') {
            titleInput.value = (role === 'admin') ? 'Yönetici' : 'Ekip Üyesi';
        }
    }
}

function submitNewMember(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitMember');
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span><span>Ekleniyor...</span>';

    const formData = new FormData(e.target);
    formData.append('action', 'create_user');

    fetch('/taskmanager/ajax/create_user', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<span>💾</span><span>Kaydet ve Ekle</span>';
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Üye başarıyla eklendi', 'success');
            } else {
                alert('✅ ' + data.message);
            }
            setTimeout(() => location.reload(), 600);
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Hata oluştu', 'error');
            } else {
                alert('❌ Hata: ' + data.message);
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<span>💾</span><span>Kaydet ve Ekle</span>';
        alert('Sunucu hatası oluştu: ' + err.message);
    });
}

function deleteUser(userId, userName) {
    const confirmMsg = <?= json_encode(__('app.team_confirm_delete', ['name' => '{name}'], ':name isimli ekip üyesini silmek istediğinize emin misiniz?')) ?>;
    if (!confirm(confirmMsg.replace(':name', userName).replace('{name}', userName))) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);

    fetch('/taskmanager/ajax/delete_user', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(err => {
        alert('Sunucu hatası: ' + err.message);
    });
}

function copyClientInvite(name, email, projectTitle) {
    const loginUrl = window.location.origin + '/taskmanager/login';
    const inviteText = `Merhaba ${name},\n\n` +
        `"${projectTitle}" projenizin süreçlerini şeffaf bir şekilde takip edebilmeniz ve tamamlanan teslimatları onaylayabilmeniz için TaskManager Müşteri Portalı hesabınız oluşturulmuştur.\n\n` +
        `📌 Giriş Adresi: ${loginUrl}\n` +
        `✉️ E-Posta: ${email}\n` +
        `🔑 Varsayılan Şifre: 123456 (İlk girişinizde güncelleyebilirsiniz)\n\n` +
        `İyi çalışmalar dileriz.`;

    const copiedMsg = <?= json_encode(__('app.team_invite_copied', '📋 Müşteri davet metni panoya kopyalandı:')) ?>;
    const promptMsg = <?= json_encode(__('app.team_invite_prompt', 'Müşteri davet metni (Kopyalamak için Ctrl+C):')) ?>;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(inviteText).then(() => {
            alert(copiedMsg + '\n\n' + inviteText);
        }).catch(() => {
            prompt(promptMsg, inviteText);
        });
    } else {
        prompt(promptMsg, inviteText);
    }
}
</script>

<?php include __DIR__ . '/../../Components/footer.php'; ?>
