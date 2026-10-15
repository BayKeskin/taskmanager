<?php
// TaskManager Raporlar & Analitik Paneli (Reports View)
require_once __DIR__ . '/../../Controller/ReportController.php';

$system->title = __('tasks.rep_title');
include __DIR__ . '/../../Components/header.php';

$reportController = new ReportController($system);
$reportData = $reportController->index();
extract($reportData);
?>

<div class="cu-view-header">
    <div class="cu-view-tabs">
        <a href="/taskmanager/board" class="cu-view-tab">
            <span>📌</span>
            <span><?= __('tasks.view_board') ?></span>
        </a>
        <a href="/taskmanager/tasks" class="cu-view-tab">
            <span>📋</span>
            <span><?= __('tasks.view_list') ?></span>
        </a>
        <a href="/taskmanager/reports" class="cu-view-tab active">
            <span>📊</span>
            <span><?= __('tasks.view_analytics') ?></span>
        </a>
    </div>
</div>

<div class="cu-content-scroll">
    <!-- ÜST METRİK KARTLARI (KPI CARDS) -->
    <div class="cu-reports-kpi-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px;">
        <!-- Toplam Görev -->
        <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:18px;">
            <div style="font-size:11.5px; color:var(--text-muted); text-transform:uppercase; font-weight:700;"><?= __('tasks.rep_kpi_total') ?></div>
            <div style="font-size:28px; font-weight:800; color:var(--text-white); margin:6px 0;"><?= $totalTasks ?></div>
            <div style="font-size:12px; color:var(--text-secondary);"><?= __('tasks.rep_kpi_total_sub') ?></div>
        </div>

        <!-- Tamamlanma Oranı -->
        <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:18px;">
            <div style="font-size:11.5px; color:var(--text-muted); text-transform:uppercase; font-weight:700;"><?= __('tasks.rep_kpi_rate') ?></div>
            <div style="font-size:28px; font-weight:800; color:#00b894; margin:6px 0;">%<?= $completionRate ?></div>
            <div style="width:100%; height:6px; background:var(--border-subtle); border-radius:3px; overflow:hidden;">
                <div style="width:<?= $completionRate ?>%; height:100%; background:#00b894;"></div>
            </div>
        </div>

        <!-- Aktif Çalışılan -->
        <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:18px;">
            <div style="font-size:11.5px; color:var(--text-muted); text-transform:uppercase; font-weight:700;"><?= __('tasks.rep_kpi_in_progress') ?></div>
            <div style="font-size:28px; font-weight:800; color:#9b5de5; margin:6px 0;"><?= $inProgressTasks + $submittedTasks ?></div>
            <div style="font-size:12px; color:var(--text-secondary);"><?= __('tasks.rep_kpi_in_progress_sub', ['in_progress' => $inProgressTasks, 'submitted' => $submittedTasks]) ?></div>
        </div>

        <!-- Gecikmiş (SLA Riski) -->
        <div style="background:var(--bg-card); border:1px solid rgba(255, 71, 87, 0.3); border-radius:var(--radius-md); padding:18px;">
            <div style="font-size:11.5px; color:var(--priority-urgent); text-transform:uppercase; font-weight:700;"><?= __('tasks.rep_kpi_overdue') ?></div>
            <div style="font-size:28px; font-weight:800; color:var(--priority-urgent); margin:6px 0;"><?= $overdueCount ?></div>
            <div style="font-size:12px; color:var(--text-secondary);"><?= __('tasks.rep_kpi_overdue_sub') ?></div>
        </div>
    </div>

    <!-- PROJE İLERLEME TABLOSU -->
    <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:20px; margin-bottom:24px;">
        <h3 style="font-size:14px; font-weight:700; color:var(--text-white); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
            <span>📁</span> <?= __('tasks.rep_proj_progress_title') ?>
        </h3>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <?php foreach ($projectStats as $ps): 
                $pRate = $ps->total_tasks > 0 ? round(($ps->completed_tasks / $ps->total_tasks) * 100) : 0;
            ?>
                <div style="background:var(--bg-app); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); padding:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:<?= htmlspecialchars($ps->color) ?>;"></span>
                            <span style="font-weight:600; font-size:13px; color:var(--text-primary);"><?= htmlspecialchars($ps->title) ?></span>
                            <span style="font-size:11px; color:var(--text-muted);">(<?= htmlspecialchars($ps->project_code) ?>)</span>
                        </div>
                        <span style="font-size:12px; font-weight:700; color:var(--brand-primary);"><?= __('tasks.rep_proj_completed_info', ['rate' => $pRate, 'completed' => $ps->completed_tasks, 'total' => $ps->total_tasks]) ?></span>
                    </div>
                    <div style="width:100%; height:8px; background:var(--border-subtle); border-radius:4px; overflow:hidden;">
                        <div style="width:<?= $pRate ?>%; height:100%; background:<?= htmlspecialchars($ps->color) ?>; transition:width 0.5s ease;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- EKİP ÜYESİ PERFORMANS TABLOSU -->
    <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:20px;">
        <h3 style="font-size:14px; font-weight:700; color:var(--text-white); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
            <span>👥</span> <?= __('tasks.rep_team_workload_title') ?>
        </h3>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border-subtle); text-align:left; color:var(--text-muted); font-size:11px; text-transform:uppercase;">
                        <th style="padding:10px 14px;"><?= __('tasks.rep_th_member') ?></th>
                        <th style="padding:10px 14px;"><?= __('tasks.rep_th_role') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('tasks.rep_th_total_assigned') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('tasks.rep_th_active') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('tasks.rep_th_completed') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('tasks.rep_th_overdue') ?></th>
                        <th style="padding:10px 14px; text-align:center;"><?= __('tasks.rep_th_rate') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamStats as $member): 
                        $rate = $member->total_assigned > 0 ? round(($member->total_completed / $member->total_assigned) * 100) : 0;
                    ?>
                        <tr style="border-bottom:1px solid var(--border-subtle);">
                            <td style="padding:12px 14px; display:flex; align-items:center; gap:10px;">
                                <div class="cu-avatar" style="width:26px; height:26px; font-size:11px; background:<?= htmlspecialchars($member->avatar_color) ?>;">
                                    <?= mb_substr($member->name, 0, 1) ?>
                                </div>
                                <span style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($member->name) ?></span>
                            </td>
                            <td style="padding:12px 14px; color:var(--text-secondary);"><?= htmlspecialchars($member->title) ?></td>
                            <td style="padding:12px 14px; text-align:center; font-weight:600;"><?= $member->total_assigned ?></td>
                            <td style="padding:12px 14px; text-align:center; color:#9b5de5; font-weight:600;"><?= $member->total_active ?></td>
                            <td style="padding:12px 14px; text-align:center; color:#00b894; font-weight:600;"><?= $member->total_completed ?></td>
                            <td style="padding:12px 14px; text-align:center; color:<?= $member->total_overdue > 0 ? 'var(--priority-urgent)' : 'var(--text-muted)' ?>; font-weight:600;">
                                <?= $member->total_overdue ?>
                            </td>
                            <td style="padding:12px 14px; text-align:center;">
                                <span style="display:inline-block; padding:3px 8px; border-radius:var(--radius-sm); font-size:11px; font-weight:700; background:rgba(0,184,148,0.15); color:#00b894;">
                                    %<?= $rate ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../Components/footer.php'; ?>
