<?php
// TaskManager Ekip Yönetimi Sayfası (Team View)
$teamController = new \Modules\Team\Controllers\TeamController();
$teamData = $teamController->index();
if (!$teamData['isAuthorized']) {
    $system->title = __('Team::team_title');
    include dirname(__DIR__, 3) . '/Core/Views/header.php';
    echo '<div class="cu-unauthorized-message">' . __('Team::team_unauthorized') . '</div>';
    include dirname(__DIR__, 3) . '/Core/Views/footer.php';
    exit;
}
extract($teamData);

$system->title = __('Team::team_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap cu-team-view-title-wrap">
        <span class="cu-team-view-icon">👥</span>
        <span class="cu-team-view-title"><?= __('Team::team_tab_team') ?></span>
    </div>
</div>

<div class="cu-content-scroll">
    <div class="cu-team-card">
        <div class="cu-team-card-header">
            <div>
                <h2 class="cu-team-heading"><?= __('Team::team_heading') ?></h2>
                <p class="cu-team-desc"><?= __('Team::team_desc') ?></p>
            </div>
            <button id="btnOpenNewMember" type="button" class="cu-btn-quick-task cu-team-btn-new-member">
                <span>➕</span>
                <span><?= __('Team::team_btn_new_member') ?></span>
            </button>
        </div>

        <div class="cu-team-table-responsive">
            <table class="cu-team-table">
                <thead>
                    <tr class="cu-team-th-row">
                        <th class="cu-team-th"><?= __('Team::team_th_user') ?></th>
                        <th class="cu-team-th"><?= __('Team::team_th_email') ?></th>
                        <th class="cu-team-th"><?= __('Team::team_th_title') ?></th>
                        <th class="cu-team-th-center"><?= __('Team::team_th_role') ?></th>
                        <th class="cu-team-th-center"><?= __('Team::team_th_lang') ?></th>
                        <th class="cu-team-th-center"><?= __('Team::team_th_project') ?></th>
                        <th class="cu-team-th-center"><?= __('Team::team_th_status') ?></th>
                        <th class="cu-team-th-center"><?= __('Team::team_th_tasks') ?></th>
                        <th class="cu-team-th-right"><?= __('Team::team_th_action') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): 
                        $taskCount = $userTaskCounts[$u->id] ?? 0;
                        $assignedProj = ($u->role === 'client' && !empty($u->client_project_id)) ? ($projectsMap[$u->client_project_id] ?? null) : null;
                    ?>
                        <tr class="cu-team-tr" id="teamUserRow_<?= $u->id ?>" data-user-id="<?= $u->id ?>">
                            <td class="cu-team-td-user">
                                <div class="cu-avatar cu-team-user-avatar cu-avatar-<?= $u->id ?>">
                                    <?= mb_substr($u->name, 0, 1) ?>
                                </div>
                                <div>
                                    <span class="cu-team-user-name"><?= htmlspecialchars($u->name) ?></span>
                                    <span class="cu-team-user-id">ID: #<?= $u->id ?></span>
                                </div>
                            </td>
                            <td class="cu-team-td-email"><?= htmlspecialchars($u->email) ?></td>
                            <td class="cu-team-td-title"><?= htmlspecialchars($u->title) ?></td>
                            <td class="cu-team-td-center">
                                <?php if ($u->role === 'admin'): ?>
                                    <span class="cu-team-badge-role-admin">
                                        <?= __('Team::team_role_admin') ?>
                                    </span>
                                <?php elseif ($u->role === 'client'): ?>
                                    <span class="cu-team-badge-role-client">
                                        <?= __('Team::team_role_client') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="cu-team-badge-role-member">
                                        <?= __('Team::team_role_member') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="cu-team-td-center">
                                <?php
                                $langCode = $u->language ?? 'tr';
                                $flags = ['tr' => '🇹🇷 TR', 'de' => '🇩🇪 DE', 'en' => '🇬🇧 EN'];
                                ?>
                                <span class="cu-team-badge-lang">
                                    <?= $flags[$langCode] ?? strtoupper($langCode) ?>
                                </span>
                            </td>
                            <td class="cu-team-td-center">
                                <?php if ($u->role === 'client' && $assignedProj): ?>
                                    <span class="cu-badge-project cu-project-color-<?= $assignedProj->id ?>">
                                        📁 <?= htmlspecialchars($assignedProj->title) ?>
                                    </span>
                                <?php elseif ($u->role === 'client'): ?>
                                    <span class="cu-team-no-project"><?= __('Team::team_no_project') ?></span>
                                <?php else: ?>
                                    <span class="cu-team-all-projects"><?= __('Team::team_all_projects') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cu-team-td-center">
                                <span class="cu-team-status-indicator <?= $u->status === 'active' ? 'cu-team-status-active' : 'cu-team-status-passive' ?>">
                                    <span class="cu-team-status-dot"></span>
                                    <?= $u->status === 'active' ? __('Team::team_status_active') : __('Team::team_status_passive') ?>
                                </span>
                            </td>
                            <td class="cu-team-td-tasks">
                                <?= $taskCount ?> <?= __('Team::team_tasks_label') ?>
                            </td>
                            <td class="cu-team-td-action">
                                <div class="cu-team-action-group">
                                    <?php if ($u->role === 'client'): ?>
                                        <button type="button" class="cu-btn cu-btn-secondary cu-team-btn-invite btn-invite-client" data-name="<?= htmlspecialchars($u->name, ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars($u->email, ENT_QUOTES, 'UTF-8') ?>" data-project="<?= htmlspecialchars($assignedProj->title ?? __('Team::team_default_project'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(__('Team::team_btn_invite_title'), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= __('Team::team_btn_invite') ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($u->id != $currentUser->id): ?>
                                        <button type="button" id="btnDeleteUser_<?= $u->id ?>" class="cu-btn cu-btn-secondary cu-team-btn-delete btn-delete-team-user" data-id="<?= $u->id ?>" data-name="<?= htmlspecialchars($u->name, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= __('Team::team_btn_delete') ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="cu-team-current-badge"><?= __('Team::team_current_account') ?></span>
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

<?php include __DIR__ . '/modals/new_member.php'; ?>
<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
