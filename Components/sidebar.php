<?php
// TaskManager Sidebar Bileşeni
require_once __DIR__ . '/../Controller/LayoutController.php';

$layoutCtrl = new LayoutController();
$sbData = $layoutCtrl->getSidebarData($_SESSION['user'] ?? null);
extract($sbData);

$activeProjectId = $_GET['project_id'] ?? null;
$projQuery = !empty($activeProjectId) ? '?project_id=' . (int)$activeProjectId : '';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
?>
<aside class="cu-sidebar">
    <!-- MOBİL BAŞLIK VE KAPATMA BUTONU -->
    <!-- MOBİL BAŞLIK, LOGO, TEMA DEĞİŞTİRİCİ VE KAPATMA BUTONU -->
    <div class="cu-sidebar-mobile-header">
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="cu-logo-icon" style="width:28px; height:28px;">
                <svg viewBox="0 0 24 24" style="width:15px; height:15px; fill:#fff;">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <span style="font-weight:700; font-size:15px; color:var(--text-white); letter-spacing:-0.2px;">TaskManager</span>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <button type="button" onclick="toggleTheme()" class="cu-btn-sm" style="background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); padding:4px 8px; font-size:13px; border-radius:6px; cursor:pointer;" title="<?= htmlspecialchars(__('app.layout_theme_toggle')) ?>">
                <span id="cuMobileSidebarThemeIcon">☀️</span>
            </button>
            <button type="button" class="cu-sidebar-close-btn" onclick="closeMobileSidebar()" aria-label="<?= htmlspecialchars(__('app.sidebar_close')) ?>" title="<?= htmlspecialchars(__('app.sidebar_close')) ?>" style="font-size:15px; background:none; border:none; color:var(--text-muted); cursor:pointer; padding:4px 8px;">✕</button>
        </div>
    </div>

<?php
// Aktif Görünüm Bilgisini Belirle
$activeViewIcon = '📌';
$activeViewTitle = ($currentUser->role === 'client') ? __('app.sidebar_client_board') : __('app.sidebar_board');

if ($currentPath === '/taskmanager/tasks') {
    $activeViewIcon = '📋';
    $activeViewTitle = __('app.sidebar_list');
} elseif ($currentPath === '/taskmanager/calendar') {
    $activeViewIcon = '📅';
    $activeViewTitle = __('app.sidebar_calendar');
} elseif ($currentPath === '/taskmanager/reports') {
    $activeViewIcon = '📊';
    $activeViewTitle = __('app.sidebar_reports');
} elseif ($currentPath === '/taskmanager/docs') {
    $activeViewIcon = '📄';
    $activeViewTitle = __('app.sidebar_docs');
} elseif ($currentPath === '/taskmanager/chat') {
    $activeViewIcon = '💬';
    $activeViewTitle = ($currentUser->role === 'client') ? __('app.sidebar_client_chat') : __('app.sidebar_chat');
} elseif ($currentPath === '/taskmanager/team') {
    $activeViewIcon = '👥';
    $activeViewTitle = __('app.sidebar_team');
} elseif ($currentPath === '/taskmanager/settings') {
    $activeViewIcon = '🛠️';
    $activeViewTitle = __('app.sidebar_settings');
} elseif ($currentPath === '/taskmanager/api-docs') {
    $activeViewIcon = '⚡';
    $activeViewTitle = __('app.sidebar_api_docs');
}

// unreadDmCount is provided by LayoutController
?>
    <!-- SAĞA AÇILIR GÖRÜNÜMLER & MODÜLLER MENÜSÜ -->
    <div class="cu-sidebar-section" style="padding-bottom: 8px;">
        <div class="cu-views-flyout-wrapper" id="cuViewsFlyoutWrapper">
            <button type="button" class="cu-views-flyout-trigger" id="cuViewsTriggerBtn" onclick="toggleViewsFlyout(event)">
                <div class="cu-nav-item-left" style="overflow:hidden; gap:8px;">
                    <span style="font-size:16px;"><?= $activeViewIcon ?></span>
                    <div style="display:flex; flex-direction:column; align-items:flex-start; text-align:left; overflow:hidden;">
                        <span style="font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-muted); letter-spacing:0.5px; line-height:1.1;"><?= __('app.sidebar_view') ?></span>
                        <span style="font-size:12.5px; font-weight:600; color:var(--text-white); white-space:nowrap; text-overflow:ellipsis; overflow:hidden; line-height:1.2;"><?= $activeViewTitle ?></span>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
                    <?php if ($unreadDmCount > 0): ?>
                        <span class="cu-nav-badge" style="background:#e74c3c; color:#fff; font-size:9px; font-weight:800; padding:1px 5px;"><?= $unreadDmCount ?></span>
                    <?php endif; ?>
                    <span class="cu-flyout-arrow" id="cuFlyoutArrow">❯</span>
                </div>
            </button>

            <!-- SAĞA AÇILAN UÇAN MENÜ (FLYOUT DROPDOWN) -->
            <div id="cuViewsFlyoutMenu" class="cu-views-flyout-menu">
                <div class="cu-flyout-title">
                    <span><?= __('app.sidebar_system_views') ?></span>
                    <span style="font-size:10px; color:var(--brand-primary); font-weight:600;"><?= __('app.sidebar_quick_select') ?></span>
                </div>
                
                <a href="/taskmanager/board<?= $projQuery ?>" class="cu-nav-item <?= ($currentPath === '/taskmanager/' || $currentPath === '/taskmanager/board') ? 'active' : '' ?>">
                    <div class="cu-nav-item-left">
                        <span>📌</span>
                        <span><?= ($currentUser->role === 'client') ? __('app.sidebar_client_board') : __('app.sidebar_board') ?></span>
                    </div>
                    <span class="cu-nav-badge"><?= $totalTasksCount ?></span>
                </a>

                <?php if ($currentUser->role !== 'client'): ?>
                    <a href="/taskmanager/tasks<?= $projQuery ?>" class="cu-nav-item <?= ($currentPath === '/taskmanager/tasks') ? 'active' : '' ?>">
                        <div class="cu-nav-item-left">
                            <span>📋</span>
                            <span><?= __('app.sidebar_list') ?></span>
                        </div>
                    </a>
                    <a href="/taskmanager/calendar<?= $projQuery ?>" class="cu-nav-item <?= ($currentPath === '/taskmanager/calendar') ? 'active' : '' ?>">
                        <div class="cu-nav-item-left">
                            <span>📅</span>
                            <span><?= __('app.sidebar_calendar') ?></span>
                        </div>
                    </a>
                    <a href="/taskmanager/reports" class="cu-nav-item <?= ($currentPath === '/taskmanager/reports') ? 'active' : '' ?>">
                        <div class="cu-nav-item-left">
                            <span>📊</span>
                            <span><?= __('app.sidebar_reports') ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <a href="/taskmanager/docs<?= $projQuery ?>" class="cu-nav-item <?= ($currentPath === '/taskmanager/docs') ? 'active' : '' ?>">
                    <div class="cu-nav-item-left">
                        <span>📄</span>
                        <span><?= __('app.sidebar_docs') ?></span>
                    </div>
                    <span class="cu-nav-badge"><?= $totalDocsCount ?></span>
                </a>

                <a href="/taskmanager/chat" class="cu-nav-item <?= ($currentPath === '/taskmanager/chat') ? 'active' : '' ?>">
                    <div class="cu-nav-item-left">
                        <span>💬</span>
                        <span><?= ($currentUser->role === 'client') ? __('app.sidebar_client_chat') : __('app.sidebar_chat') ?></span>
                    </div>
                    <?php if ($unreadDmCount > 0): ?>
                        <span class="cu-nav-badge" style="background:#e74c3c; color:#fff; font-size:10px; font-weight:800; border-radius:10px; padding:1px 6px;"><?= $unreadDmCount ?></span>
                    <?php else: ?>
                        <span class="cu-nav-badge" style="background:rgba(0,184,148,0.2); color:#00b894; font-size:10px; font-weight:700;">Live</span>
                    <?php endif; ?>
                </a>

                <?php if ($currentUser->role === 'admin'): ?>
                    <a href="/taskmanager/team" class="cu-nav-item <?= ($currentPath === '/taskmanager/team') ? 'active' : '' ?>">
                        <div class="cu-nav-item-left">
                            <span>👥</span>
                            <span><?= __('app.sidebar_team') ?></span>
                        </div>
                        <span class="cu-nav-badge"><?= $totalUsersCount ?></span>
                    </a>
                    <a href="/taskmanager/settings" class="cu-nav-item <?= ($currentPath === '/taskmanager/settings') ? 'active' : '' ?>">
                        <div class="cu-nav-item-left">
                            <span>🛠️</span>
                            <span><?= __('app.sidebar_settings') ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($currentUser->role !== 'client'): ?>
                    <a href="/taskmanager/api-docs" class="cu-nav-item <?= ($currentPath === '/taskmanager/api-docs') ? 'active' : '' ?>">
                        <div class="cu-nav-item-left">
                            <span>⚡</span>
                            <span><?= __('app.sidebar_api_docs') ?></span>
                        </div>
                        <span class="cu-nav-badge" style="background:rgba(123,104,238,0.25); color:#7B68EE; font-size:10px; font-weight:700;">v1.0</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
// Aktif ve Geçmiş Projeleri Ayır
$activeProjects = [];
$pastProjects = [];
foreach ($projectsList as $p) {
    if (isset($p->status) && in_array($p->status, ['completed', 'archived'])) {
        $pastProjects[] = $p;
    } else {
        $activeProjects[] = $p;
    }
}
?>

    <!-- PROJELER (SPACES) -->
    <div class="cu-sidebar-section" style="flex:1; overflow-y:auto;">
        <?php if ($currentUser->role === 'client'): ?>
            <div class="cu-sidebar-title" style="padding-bottom:0;"><?= __('app.sidebar_client_project') ?></div>
            <div style="margin-top:8px;">
                <?php 
                if ($clientProj): 
                    $cpCount = $projectTaskCounts[$clientProj->id] ?? 0;
                ?>
                    <div class="cu-nav-item active" style="display:flex; justify-content:space-between; align-items:center;">
                        <a href="/taskmanager/board?project_id=<?= $clientProj->id ?>" style="flex:1; display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit; overflow:hidden;">
                            <span style="display:inline-block; width:9px; height:9px; border-radius:3px; background:<?= htmlspecialchars($clientProj->color ?: '#00b894') ?>; flex-shrink:0;"></span>
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px; font-weight:600;" title="<?= htmlspecialchars($clientProj->title) ?>">
                                <?= htmlspecialchars($clientProj->title) ?>
                            </span>
                        </a>
                        <span class="cu-nav-badge"><?= $cpCount ?></span>
                    </div>
                <?php else: ?>
                    <div style="font-size:12px; color:var(--text-muted); padding:6px 8px;">
                        <?= __('app.sidebar_no_project') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="display:flex; align-items:center; justify-content:space-between; padding-right:8px;">
                <div class="cu-sidebar-title" style="padding-bottom:0;"><?= __('app.sidebar_active_projects') ?></div>
                <?php if ($currentUser->role === 'admin'): ?>
                    <button onclick="openNewProjectModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;" title="<?= htmlspecialchars(__('app.sidebar_add_project')) ?>">➕</button>
                <?php endif; ?>
            </div>

            <div style="margin-top:8px;">
                <a href="/taskmanager/board" class="cu-nav-item <?= empty($activeProjectId) && ($currentPath === '/taskmanager/' || $currentPath === '/taskmanager/board') ? 'active' : '' ?>">
                    <div class="cu-nav-item-left">
                        <span style="font-size:14px;">📁</span>
                        <span><?= __('app.sidebar_all_tasks') ?></span>
                    </div>
                </a>

                <?php foreach ($activeProjects as $proj): 
                    $pCount = $projectTaskCounts[$proj->id] ?? 0;
                    $isPActive = ($activeProjectId == $proj->id);
                ?>
                    <div class="cu-nav-item <?= $isPActive ? 'active' : '' ?>" style="display:flex; justify-content:space-between; align-items:center;">
                        <a href="/taskmanager/board?project_id=<?= $proj->id ?>" style="flex:1; display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit; overflow:hidden;">
                            <span style="display:inline-block; width:9px; height:9px; border-radius:3px; background:<?= htmlspecialchars($proj->color) ?>; flex-shrink:0;"></span>
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12.5px;" title="<?= htmlspecialchars($proj->title) ?>">
                                <?= htmlspecialchars($proj->title) ?>
                            </span>
                        </a>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span class="cu-nav-badge"><?= $pCount ?></span>
                            <?php if ($currentUser->role === 'admin'): ?>
                                <button type="button" onclick="openEditProjectModal(<?= htmlspecialchars(json_encode($proj), ENT_QUOTES, 'UTF-8') ?>)" class="cu-btn-proj-action" title="<?= htmlspecialchars(__('app.sidebar_project_settings')) ?>">
                                    ⚙️
                                </button>
                                <button type="button" onclick="toggleProjectStatus(<?= $proj->id ?>, 'completed')" class="cu-btn-proj-action" title="<?= htmlspecialchars(__('app.sidebar_archive_project')) ?>">
                                    📦
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- GEÇMİŞ PROJELER (PAST / COMPLETED PROJECTS) -->
            <div style="margin-top:20px; border-top:1px solid rgba(255,255,255,0.06); padding-top:12px;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding-right:8px; margin-bottom:6px;">
                    <div class="cu-sidebar-title" style="padding-bottom:0; color:var(--text-muted);">
                        <?= __('app.sidebar_past_projects', ['count' => count($pastProjects)]) ?>
                    </div>
                </div>

                <?php if (empty($pastProjects)): ?>
                    <div style="font-size:11px; color:var(--text-muted); padding:4px 8px;">
                        <?= __('app.sidebar_no_past_projects') ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($pastProjects as $proj): 
                        $pCount = $projectTaskCounts[$proj->id] ?? 0;
                        $isPActive = ($activeProjectId == $proj->id);
                    ?>
                        <div class="cu-nav-item <?= $isPActive ? 'active' : '' ?>" style="display:flex; justify-content:space-between; align-items:center; opacity:0.75;">
                            <a href="/taskmanager/tasks?project_id=<?= $proj->id ?>" style="flex:1; display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit; overflow:hidden;">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#7f8c8d; flex-shrink:0;"></span>
                                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; text-decoration:line-through; color:var(--text-muted);" title="<?= htmlspecialchars($proj->title) ?>">
                                    <?= htmlspecialchars($proj->title) ?>
                                </span>
                            </a>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span class="cu-nav-badge" style="background:rgba(255,255,255,0.05); color:var(--text-muted);"><?= $pCount ?></span>
                                <?php if ($currentUser->role === 'admin'): ?>
                                    <button type="button" onclick="toggleProjectStatus(<?= $proj->id ?>, 'active')" style="background:rgba(123, 104, 238, 0.1); border:1px solid rgba(123, 104, 238, 0.2); border-radius:4px; color:var(--brand-primary); cursor:pointer; font-size:11px; padding:2px 5px;" title="<?= htmlspecialchars(__('app.sidebar_restore_project')) ?>">
                                        ↺
                                    </button>
                                    <button type="button" onclick="deleteProjectPermanently(<?= $proj->id ?>)" style="background:rgba(231, 76, 60, 0.1); border:1px solid rgba(231, 76, 60, 0.2); border-radius:4px; color:#e74c3c; cursor:pointer; font-size:11px; padding:2px 5px;" title="<?= htmlspecialchars(__('app.sidebar_delete_permanent')) ?>">
                                        🗑️
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</aside>
