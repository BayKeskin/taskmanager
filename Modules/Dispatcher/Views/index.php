<?php
global $system;
if ($system) {
    $system->title = __('Dispatcher::page_title');
}
include dirname(__DIR__, 2) . '/../Core/Views/header.php';
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap cu-dispatcher-header-wrap">
        <span class="cu-dispatcher-header-icon">⚡</span>
        <span class="cu-dispatcher-header-title"><?= __('Dispatcher::page_title') ?></span>
        <span class="cu-dispatcher-badge"><?= __('Dispatcher::badge_live') ?></span>
    </div>
    <div class="cu-filter-bar">
        <button type="button" id="btnSyncWorkspaces" class="btn-dispatcher-secondary">
            🔄 <?= __('Dispatcher::btn_sync_workspaces') ?>
        </button>
        <button type="button" id="btnRefreshStatus" class="btn-dispatcher-primary">
            ⚡ <?= __('Dispatcher::btn_refresh') ?>
        </button>
    </div>
</div>

<div class="cu-content-scroll">
    <div class="dispatcher-dashboard-wrapper">

        <!-- Stat Kartları -->
        <div class="dispatcher-metrics-grid">
            <div class="dispatcher-metric-card" id="cardBridgeStatus">
                <div class="dispatcher-metric-icon">🔌</div>
                <div class="dispatcher-metric-info">
                    <span class="dispatcher-metric-label"><?= __('Dispatcher::status_bridge_title') ?></span>
                    <div class="dispatcher-metric-value-row">
                        <span id="metricBridgeValue" class="dispatcher-badge-status <?= $isRunning ? 'online' : 'offline' ?>">
                            <?= $isRunning ? 'ONLINE (Port 4000)' : 'OFFLINE' ?>
                        </span>
                    </div>
                    <span class="dispatcher-metric-hint"><?= __('Dispatcher::status_bridge_desc') ?></span>
                </div>
            </div>

            <div class="dispatcher-metric-card">
                <div class="dispatcher-metric-icon">📋</div>
                <div class="dispatcher-metric-info">
                    <span class="dispatcher-metric-label"><?= __('Dispatcher::status_queue_title') ?></span>
                    <div class="dispatcher-metric-value-row">
                        <span id="metricQueueCount" class="dispatcher-metric-number"><?= count($queuedTasks) ?></span>
                    </div>
                    <span class="dispatcher-metric-hint"><?= __('Dispatcher::status_queue_desc') ?></span>
                </div>
            </div>

            <div class="dispatcher-metric-card">
                <div class="dispatcher-metric-icon">⚙️</div>
                <div class="dispatcher-metric-info">
                    <span class="dispatcher-metric-label"><?= __('Dispatcher::status_active_title') ?></span>
                    <div class="dispatcher-metric-value-row">
                        <span id="metricActiveCount" class="dispatcher-metric-number active-accent"><?= count($activeTasks) ?></span>
                    </div>
                    <span class="dispatcher-metric-hint"><?= __('Dispatcher::status_active_desc') ?></span>
                </div>
            </div>

            <div class="dispatcher-metric-card">
                <div class="dispatcher-metric-icon">📁</div>
                <div class="dispatcher-metric-info">
                    <span class="dispatcher-metric-label"><?= __('Dispatcher::status_total_title') ?></span>
                    <div class="dispatcher-metric-value-row">
                        <span id="metricProjectCount" class="dispatcher-metric-number"><?= count($projects) ?></span>
                    </div>
                    <span class="dispatcher-metric-hint"><?= __('Dispatcher::status_total_desc') ?></span>
                </div>
            </div>
        </div>

        <!-- Sekmeler (Tabs) -->
        <div class="dispatcher-tabs-nav">
            <button type="button" class="dispatcher-tab-btn active" data-tab="tabQueue">
                📋 <?= __('Dispatcher::tab_queue') ?> <span id="tabBadgeQueue" class="tab-badge"><?= count($queuedTasks) ?></span>
            </button>
            <button type="button" class="dispatcher-tab-btn" data-tab="tabActive">
                ⚡ <?= __('Dispatcher::tab_active') ?> <span id="tabBadgeActive" class="tab-badge"><?= count($activeTasks) ?></span>
            </button>
            <button type="button" class="dispatcher-tab-btn" data-tab="tabLogs">
                🖥️ <?= __('Dispatcher::tab_logs') ?>
            </button>
        </div>

        <!-- Tab 1: Kuyruk (Atanmış AI Görevleri) -->
        <div class="dispatcher-tab-content active" id="tabQueue">
            <div class="dispatcher-card">
                <div class="dispatcher-table-responsive">
                    <table class="dispatcher-table">
                        <thead>
                            <tr>
                                <th><?= __('Dispatcher::th_task_code') ?></th>
                                <th><?= __('Dispatcher::th_project') ?></th>
                                <th><?= __('Dispatcher::th_title') ?></th>
                                <th><?= __('Dispatcher::th_priority') ?></th>
                                <th><?= __('Dispatcher::th_status') ?></th>
                                <th><?= __('Dispatcher::th_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="queueTasksTbody">
                            <?php if (empty($queuedTasks)): ?>
                                <tr class="empty-row"><td colspan="6"><?= __('Dispatcher::empty_queue') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($queuedTasks as $t): ?>
                                    <tr data-task-id="<?= (int)$t['id'] ?>" data-project-id="<?= (int)$t['project_id'] ?>">
                                        <td><strong><?= htmlspecialchars($t['task_code'] ?? '') ?></strong></td>
                                        <td>
                                            <span class="project-tag">[<?= htmlspecialchars($t['project_code'] ?? '') ?>]</span>
                                            <?= htmlspecialchars($t['project_title'] ?? '') ?>
                                        </td>
                                        <td><?= htmlspecialchars($t['title'] ?? '') ?></td>
                                        <td><span class="priority-badge priority-<?= strtolower($t['priority'] ?? 'normal') ?>"><?= htmlspecialchars($t['priority'] ?? 'normal') ?></span></td>
                                        <td><span class="status-badge status-<?= strtolower($t['status'] ?? 'assigned') ?>"><?= htmlspecialchars($t['status'] ?? 'assigned') ?></span></td>
                                        <td>
                                            <div class="table-actions">
                                                <button type="button" class="btn-action-trigger" data-task-id="<?= (int)$t['id'] ?>" data-project-id="<?= (int)$t['project_id'] ?>">
                                                    ⚡ <?= __('Dispatcher::btn_dispatch_now') ?>
                                                </button>
                                                <button type="button" class="btn-action-abort" data-task-id="<?= (int)$t['id'] ?>">
                                                    ✕ <?= __('Dispatcher::btn_cancel_task') ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Aktif Görevler (in_progress) -->
        <div class="dispatcher-tab-content" id="tabActive">
            <div class="dispatcher-card">
                <div class="dispatcher-table-responsive">
                    <table class="dispatcher-table">
                        <thead>
                            <tr>
                                <th><?= __('Dispatcher::th_task_code') ?></th>
                                <th><?= __('Dispatcher::th_project') ?></th>
                                <th><?= __('Dispatcher::th_title') ?></th>
                                <th><?= __('Dispatcher::th_priority') ?></th>
                                <th><?= __('Dispatcher::th_started_at') ?></th>
                                <th><?= __('Dispatcher::th_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="activeTasksTbody">
                            <?php if (empty($activeTasks)): ?>
                                <tr class="empty-row"><td colspan="6"><?= __('Dispatcher::empty_active') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($activeTasks as $t): ?>
                                    <tr data-task-id="<?= (int)$t['id'] ?>">
                                        <td><strong><?= htmlspecialchars($t['task_code'] ?? '') ?></strong></td>
                                        <td>
                                            <span class="project-tag">[<?= htmlspecialchars($t['project_code'] ?? '') ?>]</span>
                                            <?= htmlspecialchars($t['project_title'] ?? '') ?>
                                        </td>
                                        <td><?= htmlspecialchars($t['title'] ?? '') ?></td>
                                        <td><span class="priority-badge priority-<?= strtolower($t['priority'] ?? 'normal') ?>"><?= htmlspecialchars($t['priority'] ?? 'normal') ?></span></td>
                                        <td><?= htmlspecialchars($t['updated_at'] ?? '-') ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <button type="button" class="btn-action-abort" data-task-id="<?= (int)$t['id'] ?>">
                                                    ⏹️ <?= __('Dispatcher::btn_abort_task') ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Canlı Loglar (Terminal Console) -->
        <div class="dispatcher-tab-content" id="tabLogs">
            <div class="dispatcher-card dispatcher-log-card">
                <div class="dispatcher-log-header">
                    <span>📟 Dispatcher.log Output</span>
                    <button type="button" id="btnClearLogs" class="btn-dispatcher-secondary btn-sm">
                        <?= __('Dispatcher::btn_clear_logs') ?>
                    </button>
                </div>
                <div class="dispatcher-terminal" id="dispatcherTerminal">
                    <?php if (empty($recentLogs)): ?>
                        <div class="log-line log-muted"><?= __('Dispatcher::empty_logs') ?></div>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-line"><?= htmlspecialchars($log) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include dirname(__DIR__, 2) . '/../Core/Views/footer.php'; ?>
