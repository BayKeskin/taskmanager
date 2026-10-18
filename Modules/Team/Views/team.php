<?php
// TaskManager Ekip Yönetimi Sayfası (Team View)
$teamController = new \Modules\Team\Controllers\TeamController();
$teamData = $teamController->index();
if (!$teamData['isAuthorized']) {
    $system->title = __('Team::team_title', 'Ekip Yönetimi - TaskManager');
    include dirname(__DIR__, 3) . '/Core/Views/header.php';
    echo '<div style="padding:40px; text-align:center; color:var(--text-muted);">' . __('Team::team_unauthorized', 'Bu sayfaya erişim yetkiniz bulunmamaktadır.') . '</div>';
    include dirname(__DIR__, 3) . '/Core/Views/footer.php';
    exit;
}
extract($teamData);

$system->title = __('Team::team_title', 'Ekip Yönetimi - TaskManager');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">👥</span>
        <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= __('Team::team_tab_team', 'Ekip Yönetimi & Müşteriler') ?></span>
    </div>
</div>

<div class="cu-content-scroll">
    <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div>
                <h2 style="font-size:16px; font-weight:700; color:var(--text-white);"><?= __('Team::team_heading', 'Çalışanlar ve Müşteri Portalı Kullanıcıları') ?></h2>
                <p style="font-size:12.5px; color:var(--text-secondary); margin-top:2px;"><?= __('Team::team_desc', 'Görev ataması yapılabilecek ekip üyeleri ve yalnızca kendi projesine erişebilen danışan/müşteriler.') ?></p>
            </div>
            <button id="btnOpenNewMember" type="button" class="cu-btn-quick-task" onclick="openNewMemberModal()" style="height:38px; padding:0 18px; font-size:13px;">
                <span>➕</span>
                <span><?= __('Team::team_btn_new_member', 'Yeni Üye / Müşteri Ekle') ?></span>
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border-subtle); text-align:left; color:var(--text-muted); font-size:11px; text-transform:uppercase;">
                        <th style="padding:10px 14px;"><?= __('Team::team_th_user', 'Kullanıcı') ?></th>
                        <th style="padding:10px 14px;"><?= __('Team::team_th_email', 'E-Posta') ?></th>
                        <th style="padding:10px 14px;"><?= __('Team::team_th_title', 'Unvan / Pozisyon') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('Team::team_th_role', 'Sistem Rolü') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('Team::team_th_lang', 'Dil') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('Team::team_th_project', 'Eriştiği Proje') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('Team::team_th_status', 'Durum') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('Team::team_th_tasks', 'Atanan Görevler') ?></th>
                        <th style="padding:10px 14px; text-align:right;"><?= __('Team::team_th_action', 'İşlem') ?></th>
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
                                        <?= __('Team::team_role_admin', 'Yönetici') ?>
                                    </span>
                                <?php elseif ($u->role === 'client'): ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:var(--radius-sm); font-size:11px; font-weight:700; text-transform:uppercase; background:rgba(0,184,148,0.18); color:#00b894; border:1px solid rgba(0,184,148,0.3);">
                                        <?= __('Team::team_role_client', '👤 Müşteri') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:var(--radius-sm); font-size:11px; font-weight:700; text-transform:uppercase; background:var(--border-subtle); color:var(--text-secondary);">
                                        <?= __('Team::team_role_member', 'Ekip Üyesi') ?>
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
                                    <span style="color:#e67e22; font-size:11px;"><?= __('Team::team_no_project', '⚠️ Proje atanmamış') ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;"><?= __('Team::team_all_projects', 'Tüm Projeler') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 14px; text-align:center;">
                                <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; color:<?= $u->status === 'active' ? '#00b894' : '#ff7675' ?>;">
                                    <span style="width:7px; height:7px; border-radius:50%; background:currentColor;"></span>
                                    <?= $u->status === 'active' ? __('Team::team_status_active', 'Aktif') : __('Team::team_status_passive', 'Pasif') ?>
                                </span>
                            </td>
                            <td style="padding:12px 14px; text-align:center; font-weight:600; color:var(--brand-primary);">
                                <?= __('Team::team_tasks_count', ['count' => $taskCount], $taskCount . ' Görev') ?>
                            </td>
                            <td style="padding:12px 14px; text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:6px;">
                                    <?php if ($u->role === 'client'): ?>
                                        <button type="button" class="cu-btn cu-btn-secondary" onclick="copyClientInvite('<?= htmlspecialchars(addslashes($u->name)) ?>', '<?= htmlspecialchars(addslashes($u->email)) ?>', '<?= htmlspecialchars(addslashes($assignedProj->title ?? 'Belirlenen Proje')) ?>')" style="padding:4px 8px; font-size:11.5px; color:#00b894; border-color:rgba(0,184,148,0.3);" title="<?= htmlspecialchars(__('Team::team_btn_invite_title', 'Müşteri Giriş Davet Metnini Kopyala'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= __('Team::team_btn_invite', '✉️ Davet') ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($u->id != $currentUser->id): ?>
                                        <button type="button" class="cu-btn cu-btn-secondary" onclick="deleteUser(<?= $u->id ?>, '<?= htmlspecialchars(addslashes($u->name)) ?>')" style="padding:4px 10px; font-size:11.5px; color:#ff7675; border-color:rgba(255,118,117,0.3);">
                                            <?= __('Team::team_btn_delete', '🗑️ Sil') ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:var(--text-muted); font-style:italic;"><?= __('Team::team_current_account', '(Mevcut Hesap)') ?></span>
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
<!-- 👤 YENİ EKİP ÜYESİ EKLEME MODALI -->
<?php include __DIR__ . '/modals/new_member.php'; ?>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
