<?php
global $system;
if ($system) {
    $system->title = __('AgentRules::module_name');
}
include dirname(__DIR__, 2) . '/../Core/Views/header.php';
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap cu-agentrules-header-wrap">
        <span class="cu-agentrules-header-icon">🛡️</span>
        <span class="cu-agentrules-header-title"><?= __('AgentRules::module_name') ?></span>
    </div>
    <div class="cu-filter-bar">
        <button type="button" id="btnSyncAll" class="btn-agent-success">
            <?= __('AgentRules::btn_sync_all') ?>
        </button>
    </div>
</div>

<div class="cu-content-scroll">
    <div class="agent-rules-wrapper">
        <div class="agent-rules-grid">
            <!-- Sol: Genel Kurallar (Global Rules) Editörü -->
            <div class="agent-rules-card">
                <div class="agent-rules-card-header">
                    <h2 class="agent-rules-card-title"><?= __('AgentRules::global_rules_title') ?></h2>
                    <p class="agent-rules-card-subtitle"><?= __('AgentRules::global_rules_subtitle') ?></p>
                </div>
                <div class="agent-rules-card-body">
                    <textarea id="globalRulesInput" class="agent-rules-textarea" spellcheck="false"><?= htmlspecialchars($globalRules) ?></textarea>
                </div>
                <div class="agent-rules-card-footer">
                    <button type="button" id="btnSaveGlobal" class="btn-agent-primary">
                        <?= __('AgentRules::btn_save_global') ?>
                    </button>
                </div>
            </div>

            <!-- Sağ: Proje Listesi ve Senkronizasyon Durumu -->
            <div class="agent-rules-card">
                <div class="agent-rules-card-header">
                    <h2 class="agent-rules-card-title"><?= __('AgentRules::projects_rules_title') ?></h2>
                    <p class="agent-rules-card-subtitle"><?= __('AgentRules::projects_rules_subtitle') ?></p>
                </div>
                <div class="agent-rules-card-body table-container">
                    <table class="agent-rules-table">
                        <thead>
                            <tr>
                                <th><?= __('AgentRules::col_project') ?></th>
                                <th><?= __('AgentRules::col_workspace') ?></th>
                                <th><?= __('AgentRules::col_custom_rules') ?></th>
                                <th><?= __('AgentRules::col_last_sync') ?></th>
                                <th class="text-right"><?= __('AgentRules::col_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projectStatuses as $p): ?>
                                <tr>
                                    <td>
                                        <strong>[<?= htmlspecialchars($p['project_code']) ?>]</strong>
                                        <div><?= htmlspecialchars($p['title']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($p['has_dir']): ?>
                                            <span class="text-code-pill"><?= htmlspecialchars($p['workspace_path']) ?></span>
                                        <?php else: ?>
                                            <span class="badge-tag badge-danger"><?= __('AgentRules::status_not_found') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['rules_length'] > 0): ?>
                                            <span class="badge-tag badge-success"><?= sprintf(__('AgentRules::status_has_rules'), $p['rules_length']) ?></span>
                                        <?php else: ?>
                                            <span class="badge-tag badge-neutral"><?= __('AgentRules::status_no_rules') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="cell-file-badge">
                                            <?php if ($p['has_file']): ?>
                                                <span class="badge-tag badge-success"><?= __('AgentRules::status_synced') ?></span>
                                            <?php else: ?>
                                                <span class="badge-tag badge-warning"><?= __('AgentRules::status_missing_file') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="cell-last-sync">
                                            <?= $p['last_sync'] ?: '-' ?>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <?php if ($p['has_dir']): ?>
                                            <button type="button" class="btn-agent-sync btn-sync-single" data-project-id="<?= $p['id'] ?>">
                                                <?= __('AgentRules::btn_sync_single') ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include dirname(__DIR__, 2) . '/../Core/Views/footer.php';
?>
