<?php
// TaskManager Raporlar & Analitik Paneli (Reports View)
$reportController = new \Modules\Reports\Controllers\ReportController();
$activeProjectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$reportData = $reportController->index($activeProjectId);
extract($reportData);

$system->title = __('Reports::rep_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap cu-rep-header-wrap">
        <span class="cu-rep-header-icon">📊</span>
        <span class="cu-rep-header-title"><?= __('Reports::view_analytics') ?></span>
    </div>
    <div class="cu-filter-bar cu-rep-filter-bar">
        <?php if (!$isClient): ?>
            <div class="cu-rep-filter-group">
                <label for="reportsProjectFilter" class="cu-rep-filter-label"><?= __('Reports::filter_project') ?></label>
                <select id="reportsProjectFilter" class="cu-form-control cu-rep-project-select">
                    <option value=""><?= __('Reports::all_projects') ?></option>
                    <?php foreach (($allProjects ?? []) as $proj): 
                        $pObj = (object)$proj;
                    ?>
                        <option value="<?= (int)$pObj->id ?>" <?= ($activeProjectId === (int)$pObj->id) ? 'selected' : '' ?>>
                            📁 <?= htmlspecialchars($pObj->title) ?> (<?= htmlspecialchars($pObj->project_code) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <button type="button" class="cu-btn-sm cu-rep-btn-delivery-pdf" id="btnDownloadDeliveryReportTop" title="<?= htmlspecialchars(__('Reports::tooltip_pdf_report')) ?>">
            <span>📄</span> <span><?= __('Reports::btn_pdf_report') ?></span>
        </button>
    </div>
</div>

<div class="cu-content-scroll">
    <!-- EFOR, MALİYET VE HAKEDİŞ ÖZETİ KARTI (TSK-194) -->
    <?php if (!empty($billingSummary)): ?>
        <div class="cu-rep-billing-box">
            <div class="cu-rep-billing-top">
                <div class="cu-rep-billing-info">
                    <div class="cu-rep-billing-icon-wrap">
                        💰
                    </div>
                    <div>
                        <h3 class="cu-rep-billing-title"><?= __('Reports::billing_summary_title') ?></h3>
                        <p class="cu-rep-billing-sub">
                            <?= htmlspecialchars($billingSummary['project_title']) ?> • <?= __('Reports::billing_generated_at') ?> <?= htmlspecialchars($billingSummary['generated_at']) ?>
                        </p>
                    </div>
                </div>
                <div class="cu-rep-billing-actions">
                    <button type="button" class="cu-btn-sm cu-rep-btn-print" id="btnPrintDeliveryReport" data-project-id="<?= (int)($billingSummary['project_id'] ?? 0) ?>">
                        <span>🖨️</span> <span><?= __('Reports::btn_print_pdf') ?></span>
                    </button>
                </div>
            </div>

            <!-- Hakediş KPI'ları -->
            <div class="cu-rep-billing-kpi-grid">
                <div class="cu-rep-billing-card">
                    <div class="cu-rep-billing-kpi-label"><?= __('Reports::billing_kpi_tasks') ?></div>
                    <div class="cu-rep-billing-kpi-val cu-rep-color-cyan"><?= (int)$billingSummary['total_completed_tasks'] ?> adet</div>
                    <div class="cu-rep-billing-kpi-sub"><?= __('Reports::billing_kpi_tasks_sub') ?></div>
                </div>

                <div class="cu-rep-billing-card">
                    <div class="cu-rep-billing-kpi-label"><?= __('Reports::billing_kpi_spent') ?></div>
                    <div class="cu-rep-billing-kpi-val cu-rep-color-purple"><?= $billingSummary['total_hours_spent'] ?> saat</div>
                    <div class="cu-rep-billing-kpi-sub"><?= __('Reports::billing_kpi_est_sub') ?> <?= $billingSummary['total_hours_estimated'] ?> sa</div>
                </div>

                <div class="cu-rep-billing-card">
                    <div class="cu-rep-billing-kpi-label"><?= __('Reports::billing_kpi_total_cost') ?></div>
                    <div class="cu-rep-billing-kpi-val cu-rep-color-green"><?= number_format($billingSummary['total_cost'], 2, ',', '.') ?> TL</div>
                    <div class="cu-rep-billing-kpi-sub"><?= __('Reports::billing_kpi_rate_sub') ?> <?= number_format($billingSummary['hourly_rate'], 0, ',', '.') ?> TL</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ÜST METRİK KARTLARI (KPI CARDS) -->
    <div class="cu-reports-kpi-grid cu-rep-kpi-grid">
        <!-- Toplam Görev -->
        <div class="cu-rep-kpi-card">
            <div class="cu-rep-kpi-label"><?= __('Reports::rep_kpi_total') ?></div>
            <div class="cu-rep-kpi-val cu-rep-color-white"><?= $totalTasks ?></div>
            <div class="cu-rep-kpi-sub"><?= __('Reports::rep_kpi_total_sub') ?></div>
        </div>

        <!-- Tamamlanma Oranı -->
        <div class="cu-rep-kpi-card">
            <div class="cu-rep-kpi-label"><?= __('Reports::rep_kpi_rate') ?></div>
            <div class="cu-rep-kpi-val cu-rep-color-green">%<?= $completionRate ?></div>
            <div class="cu-rep-progress-track">
                <div class="cu-rep-progress-fill" data-rate="<?= (float)$completionRate ?>"></div>
            </div>
        </div>

        <!-- Aktif Çalışılan -->
        <div class="cu-rep-kpi-card">
            <div class="cu-rep-kpi-label"><?= __('Reports::rep_kpi_in_progress') ?></div>
            <div class="cu-rep-kpi-val cu-rep-color-lilac"><?= $inProgressTasks + $submittedTasks ?></div>
            <div class="cu-rep-kpi-sub"><?= __('Reports::rep_kpi_in_progress_sub') ?></div>
        </div>

        <!-- Gecikmiş (SLA Riski) -->
        <div class="cu-rep-kpi-card cu-rep-kpi-card-danger">
            <div class="cu-rep-kpi-label cu-rep-color-danger"><?= __('Reports::rep_kpi_overdue') ?></div>
            <div class="cu-rep-kpi-val cu-rep-color-danger"><?= $overdueCount ?></div>
            <div class="cu-rep-kpi-sub"><?= __('Reports::rep_kpi_overdue_sub') ?></div>
        </div>
    </div>

    <!-- PROJE İLERLEME TABLOSU -->
    <div class="cu-rep-section-box">
        <h3 class="cu-rep-section-title">
            <span>📁</span> <?= __('Reports::rep_proj_progress_title') ?>
        </h3>
        <div class="cu-rep-proj-list">
            <?php foreach ($projectStats as $ps): 
                $pRate = $ps->total_tasks > 0 ? round(($ps->completed_tasks / $ps->total_tasks) * 100) : 0;
            ?>
                <div class="cu-rep-proj-item">
                    <div class="cu-rep-proj-header">
                        <div class="cu-rep-proj-meta">
                            <span class="cu-rep-proj-dot" data-color="<?= htmlspecialchars($ps->color ?: '#7B68EE') ?>"></span>
                            <span class="cu-rep-proj-name"><?= htmlspecialchars($ps->title) ?></span>
                            <span class="cu-rep-proj-code">(<?= htmlspecialchars($ps->project_code) ?>)</span>
                        </div>
                        <span class="cu-rep-proj-rate-text"><?= str_replace([':rate', ':completed', ':total'], [$pRate, $ps->completed_tasks, $ps->total_tasks], __('Reports::rep_proj_completed_info')) ?></span>
                    </div>
                    <div class="cu-rep-proj-track">
                        <div class="cu-rep-proj-bar" data-rate="<?= (float)$pRate ?>" data-color="<?= htmlspecialchars($ps->color ?: '#7B68EE') ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- EKİP ÜYESİ PERFORMANS TABLOSU -->
    <div class="cu-rep-section-box">
        <h3 class="cu-rep-section-title">
            <span>👥</span> <?= __('Reports::rep_team_workload_title') ?>
        </h3>
        <div class="cu-rep-table-wrap">
            <table class="cu-rep-table">
                <thead>
                    <tr class="cu-rep-thead-row">
                        <th class="cu-rep-th"><?= __('Reports::rep_th_member') ?></th>
                        <th class="cu-rep-th"><?= __('Reports::rep_th_role') ?></th>
                        <th class="cu-rep-th cu-rep-th-center"><?= __('Reports::rep_th_total_assigned') ?></th>
                        <th class="cu-rep-th cu-rep-th-center"><?= __('Reports::rep_th_active') ?></th>
                        <th class="cu-rep-th cu-rep-th-center"><?= __('Reports::rep_th_completed') ?></th>
                        <th class="cu-rep-th cu-rep-th-center"><?= __('Reports::rep_th_overdue') ?></th>
                        <th class="cu-rep-th cu-rep-th-center"><?= __('Reports::rep_th_rate') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamStats as $member): 
                        $rate = $member->total_assigned > 0 ? round(($member->total_completed / $member->total_assigned) * 100) : 0;
                    ?>
                        <tr class="cu-rep-tbody-row">
                            <td class="cu-rep-td cu-rep-td-member">
                                <div class="cu-avatar cu-rep-avatar" data-avatar-color="<?= htmlspecialchars($member->avatar_color ?: '#7B68EE') ?>">
                                    <?= mb_substr($member->name, 0, 1) ?>
                                </div>
                                <span class="cu-rep-member-name"><?= htmlspecialchars($member->name) ?></span>
                            </td>
                            <td class="cu-rep-td cu-rep-td-role"><?= htmlspecialchars($member->title) ?></td>
                            <td class="cu-rep-td cu-rep-td-center cu-rep-font-bold"><?= $member->total_assigned ?></td>
                            <td class="cu-rep-td cu-rep-td-center cu-rep-color-lilac cu-rep-font-bold"><?= $member->total_active ?></td>
                            <td class="cu-rep-td cu-rep-td-center cu-rep-color-green cu-rep-font-bold"><?= $member->total_completed ?></td>
                            <td class="cu-rep-td cu-rep-td-center cu-rep-font-bold <?= $member->total_overdue > 0 ? 'cu-rep-color-danger' : 'cu-rep-color-muted' ?>">
                                <?= $member->total_overdue ?>
                            </td>
                            <td class="cu-rep-td cu-rep-td-center">
                                <span class="cu-rep-rate-badge">
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

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
