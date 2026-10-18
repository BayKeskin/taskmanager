<?php
// Modules/Reports/Views/dashboard.php
?>
<div class="cu-reports-grid">
    <div class="cu-metric-card">
        <h4><?= htmlspecialchars(__('Reports::total_tasks', 'Toplam Görev')) ?></h4>
        <p style="font-size:24px; font-weight:800; margin:8px 0;"><?= (int)($totalTasks ?? 0) ?></p>
    </div>
    <div class="cu-metric-card">
        <h4><?= htmlspecialchars(__('Reports::completed_tasks', 'Tamamlanan')) ?></h4>
        <p style="font-size:24px; font-weight:800; color:#00b894; margin:8px 0;"><?= (int)($completedTasks ?? 0) ?></p>
    </div>
    <div class="cu-metric-card">
        <h4><?= htmlspecialchars(__('Reports::completion_rate', 'Oran')) ?></h4>
        <p style="font-size:24px; font-weight:800; color:#7B68EE; margin:8px 0;">%<?= (int)($completionRate ?? 0) ?></p>
    </div>
</div>
