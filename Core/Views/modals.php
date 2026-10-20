<?php
/**
 * Core/Views/modals.php
 * 
 * Çekirdek Modal Konteyneri:
 * 1. Ortak veri hazırlığı ($allUsers, $allProjects, $sysSettings)
 * 2. Evrensel Komut Paleti (Ctrl+K)
 * 3. Aktif tüm HMVC modüllerinin Views/modals/*.php şablonlarının otomatik yüklenmesi (Zero-Touch)
 * 4. Toast bildirim konteyneri
 */
$layoutMgr = \Core\LayoutManager::getInstance();
$modalsData = $layoutMgr->getModalsData();
$allUsers = $modalsData['allUsers'];
$allProjects = $modalsData['allProjects'];
$sysSettings = $modalsData['sysSettings'];
$currentUser = $_SESSION['user'] ?? null;
$employeeCanAiTasks = (int)($sysSettings->employee_can_ai_tasks ?? 0);
$canUseAiTasks = ($currentUser && $currentUser->role === 'admin') || ($currentUser && $currentUser->role !== 'client' && $employeeCanAiTasks);
?>

<!-- EVRENSEL KOMUT PALETİ (CTRL+K) -->
<div class="cu-modal-overlay" id="modalCommandPalette" onclick="if(event.target === this) closeCommandPalette()">
    <div class="cu-modal" style="max-width:580px; background:var(--bg-sidebar); border-color:var(--brand-glow); box-shadow:0 25px 60px rgba(0,0,0,0.6);">
        <div style="padding:14px 18px; border-bottom:1px solid var(--border-subtle); display:flex; align-items:center; gap:12px;">
            <span style="font-size:18px;">🔍</span>
            <input type="text" id="inputCommandPalette" placeholder="<?= htmlspecialchars(__('app.palette_search_ph')) ?>" style="width:100%; background:transparent; border:none; color:var(--text-white); font-size:15px; outline:none;" autocomplete="off" oninput="filterCommandPalette(this.value)">
            <span style="font-size:11px; color:var(--text-muted); background:rgba(255,255,255,0.06); padding:2px 6px; border-radius:4px;">ESC</span>
        </div>
        <div id="commandPaletteList" style="max-height:340px; overflow-y:auto; padding:8px;">
            <div style="font-size:10.5px; font-weight:700; color:var(--text-muted); text-transform:uppercase; padding:6px 10px;"><?= __('app.palette_quick_actions') ?></div>
            <div class="cu-command-item" onclick="closeCommandPalette(); openNewTaskModal();" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>➕</span> <span><?= __('app.modal_new_task_title') ?></span>
            </div>
            <div class="cu-command-item" onclick="closeCommandPalette(); openNewProjectModal();" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📁</span> <span><?= __('app.sidebar_add_project') ?></span>
            </div>
            <div class="cu-command-item" onclick="closeCommandPalette(); openProfileModal();" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>⚙️</span> <span><?= __('app.palette_edit_profile') ?></span>
            </div>
            <?php if (\Core\ModuleLoader::isModuleActive('apitester')): ?>
            <div class="cu-command-item" onclick="window.location.href='<?= baseUrl('api-docs') ?>';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:#7B68EE; transition:var(--transition);">
                <span>⚡</span> <span><?= __('app.layout_api_guide') ?></span>
            </div>
            <?php endif; ?>
            
            <div style="font-size:10.5px; font-weight:700; color:var(--text-muted); text-transform:uppercase; padding:10px 10px 6px;"><?= __('app.palette_views_heading') ?></div>
            <div class="cu-command-item" onclick="window.location.href='<?= baseUrl('tasks') ?>';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📋</span> <span><?= __('app.sidebar_list') ?></span>
            </div>
            <div class="cu-command-item" onclick="window.location.href='<?= baseUrl('board') ?>';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📌</span> <span><?= __('app.sidebar_board') ?></span>
            </div>
            <?php if (\Core\ModuleLoader::isModuleActive('calendar')): ?>
            <div class="cu-command-item" onclick="window.location.href='<?= baseUrl('calendar') ?>';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📅</span> <span><?= __('app.sidebar_calendar') ?></span>
            </div>
            <?php endif; ?>
            <?php if (\Core\ModuleLoader::isModuleActive('docs')): ?>
            <div class="cu-command-item" onclick="window.location.href='<?= baseUrl('docs') ?>';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📄</span> <span><?= __('app.sidebar_docs') ?></span>
            </div>
            <?php endif; ?>
            <?php if (\Core\ModuleLoader::isModuleActive('reports')): ?>
            <div class="cu-command-item" onclick="window.location.href='<?= baseUrl('reports') ?>';" style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius-sm); cursor:pointer; font-size:13px; color:var(--text-primary); transition:var(--transition);">
                <span>📊</span> <span><?= __('app.sidebar_reports') ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// OTOMATİK MODÜL MODAL KEŞFİ & YÜKLEMESİ (PLUG & PLAY)
$layoutMgr->renderModuleModals([
    'allUsers'           => $allUsers,
    'allProjects'        => $allProjects,
    'sysSettings'        => $sysSettings,
    'currentUser'        => $currentUser,
    'employeeCanAiTasks' => $employeeCanAiTasks,
    'canUseAiTasks'      => $canUseAiTasks
]);
?>

<!-- TOAST CONTAINER -->
<div class="cu-toast-container" id="cuToastContainer"></div>

<script>
window.ALL_TEAM_USERS = <?= json_encode($allUsers, JSON_UNESCAPED_UNICODE) ?>;
</script>
