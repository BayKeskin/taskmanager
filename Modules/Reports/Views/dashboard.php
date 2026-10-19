<?php
// Modules/Reports/Views/dashboard.php
?>
<div class="cu-reports-grid">
    <div class="cu-metric-card">
        <h4><?= htmlspecialchars(__('Reports::total_tasks')) ?></h4>
        <p class="cu-metric-val"><?= (int)($totalTasks ?? 0) ?></p>
    </div>
    <div class="cu-metric-card">
        <h4><?= htmlspecialchars(__('Reports::completed_tasks')) ?></h4>
        <p class="cu-metric-val cu-metric-completed"><?= (int)($completedTasks ?? 0) ?></p>
    </div>
    <div class="cu-metric-card">
        <h4><?= htmlspecialchars(__('Reports::completion_rate')) ?></h4>
        <p class="cu-metric-val cu-metric-rate">%<?= (int)($completionRate ?? 0) ?></p>
    </div>
</div>
