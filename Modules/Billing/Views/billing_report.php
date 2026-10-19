<?php
/**
 * TaskManager - Hakediş & İş Teslim Raporu (PDF Baskı ve Önizleme Şablonu)
 * TSK-194: Müşteri Portalı ve Raporlar İçin Efor, Maliyet ve Hakediş Özeti
 */
if (!isset($billing) || !is_array($billing)) {
    $billingController = new \Modules\Billing\Controllers\BillingController();
    $projectId = isset($_GET['project_id']) && (int)$_GET['project_id'] > 0 ? (int)$_GET['project_id'] : null;
    $hourlyRate = isset($_GET['rate']) && (float)$_GET['rate'] > 0 ? (float)$_GET['rate'] : null;
    $billing = $billingController->getBillingData($projectId, $hourlyRate);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Language::current(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($billing['project_title']) ?> - <?= htmlspecialchars(__('Billing::report_title')) ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Billing Report Scoped CSS -->
    <link rel="stylesheet" href="<?= baseUrl('/Modules/Billing/Assets/css/billing-report.css') ?>">
</head>
<body class="billing-report-page">

    <div class="action-bar">
        <a href="<?= baseUrl('reports') ?>" class="btn-back">⬅ <?= htmlspecialchars(__('Billing::btn_back_reports')) ?></a>
        <button type="button" class="btn-print" id="btnBillingPrint">
            <span>🖨️</span> <span><?= htmlspecialchars(__('Billing::btn_print_pdf')) ?></span>
        </button>
    </div>

    <div class="report-page">
        <!-- HEADER -->
        <div class="header-bar">
            <div class="brand-wrap">
                <div class="brand-logo">TM</div>
                <div>
                    <h1 class="brand-title">TaskManager Enterprise</h1>
                    <div class="brand-sub"><?= htmlspecialchars(__('Billing::report_header_sub')) ?></div>
                </div>
            </div>
            <div class="report-meta">
                <div><b><?= htmlspecialchars(__('Billing::rep_th_project')) ?></b> <?= htmlspecialchars($billing['project_title']) ?> (<?= htmlspecialchars($billing['project_code']) ?>)</div>
                <div><b><?= htmlspecialchars(__('Billing::report_date_label')) ?></b> <?= htmlspecialchars($billing['generated_at']) ?></div>
                <div><b><?= htmlspecialchars(__('Billing::report_status_label')) ?></b> <span class="status-delivered"><?= htmlspecialchars(__('Billing::status_completed_deliv')) ?></span></div>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-label"><?= htmlspecialchars(__('Billing::billing_kpi_tasks')) ?></div>
                <div class="kpi-val kpi-val-tasks"><?= $billing['total_completed_tasks'] ?></div>
                <div class="kpi-sub"><?= htmlspecialchars(__('Billing::kpi_sub_tasks')) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><?= htmlspecialchars(__('Billing::billing_kpi_spent')) ?></div>
                <div class="kpi-val kpi-val-hours"><?= $billing['total_hours_spent'] ?> <?= htmlspecialchars(__('Billing::hours_unit')) ?></div>
                <div class="kpi-sub"><?= htmlspecialchars(__('Billing::kpi_sub_est')) ?> <?= $billing['total_hours_estimated'] ?> <?= htmlspecialchars(__('Billing::hours_unit')) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><?= htmlspecialchars(__('Billing::billing_kpi_rate')) ?></div>
                <div class="kpi-val"><?= number_format($billing['hourly_rate'], 0, ',', '.') ?> <?= htmlspecialchars(__('Billing::currency_unit')) ?></div>
                <div class="kpi-sub"><?= htmlspecialchars(__('Billing::kpi_sub_contract')) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><?= htmlspecialchars(__('Billing::billing_kpi_total_cost')) ?></div>
                <div class="kpi-val kpi-val-cost"><?= number_format($billing['total_cost'], 2, ',', '.') ?> <?= htmlspecialchars(__('Billing::currency_unit')) ?></div>
                <div class="kpi-sub"><?= htmlspecialchars(__('Billing::kpi_sub_vat')) ?></div>
            </div>
        </div>

        <!-- TASKS TABLE -->
        <table class="task-table">
            <thead>
                <tr>
                    <th class="th-code"><?= htmlspecialchars(__('Billing::th_code')) ?></th>
                    <th><?= htmlspecialchars(__('Billing::th_title_notes')) ?></th>
                    <th class="th-assignee"><?= htmlspecialchars(__('Billing::th_assignee')) ?></th>
                    <th class="th-spent"><?= htmlspecialchars(__('Billing::th_spent')) ?></th>
                    <th class="th-cost"><?= htmlspecialchars(__('Billing::th_cost')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($billing['tasks'])): ?>
                    <tr>
                        <td colspan="5" class="td-empty">
                            <?= htmlspecialchars(__('Billing::no_tasks_found')) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($billing['tasks'] as $t): ?>
                        <tr>
                            <td>
                                <span class="task-code"><?= htmlspecialchars($t['task_code']) ?></span>
                            </td>
                            <td>
                                <div class="task-title-text">
                                    <?= htmlspecialchars($t['title']) ?>
                                </div>
                                <?php if (!empty($t['delivery_notes'])): ?>
                                    <div class="notes-box">
                                        <b>🔍 <?= htmlspecialchars(__('Billing::delivery_note_prefix')) ?></b><br>
                                        <?= htmlspecialchars(mb_strimwidth($t['delivery_notes'], 0, 200, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['assignee_name'] ?: __('Billing::team_default')) ?></td>
                            <td class="td-spent"><?= $t['spent_hours'] ?> <?= htmlspecialchars(__('Billing::hours_unit')) ?></td>
                            <td class="td-cost">
                                <?= number_format($t['task_cost'], 2, ',', '.') ?> <?= htmlspecialchars(__('Billing::currency_unit')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SUMMARY TOTALS -->
        <div class="summary-totals">
            <div class="total-row">
                <span><?= htmlspecialchars(__('Billing::summary_completed')) ?></span>
                <b><?= $billing['total_completed_tasks'] ?> <?= htmlspecialchars(__('Billing::summary_unit_qty')) ?></b>
            </div>
            <div class="total-row">
                <span><?= htmlspecialchars(__('Billing::summary_total_time')) ?></span>
                <b><?= $billing['total_hours_spent'] ?> <?= htmlspecialchars(__('Billing::summary_unit_hours')) ?></b>
            </div>
            <div class="total-row">
                <span><?= htmlspecialchars(__('Billing::summary_unit_rate')) ?></span>
                <b><?= number_format($billing['hourly_rate'], 2, ',', '.') ?> <?= htmlspecialchars(__('Billing::summary_rate_per_hour')) ?></b>
            </div>
            <div class="total-row grand">
                <span><?= htmlspecialchars(__('Billing::summary_grand_total')) ?></span>
                <span><?= number_format($billing['total_cost'], 2, ',', '.') ?> <?= htmlspecialchars(__('Billing::currency_unit')) ?></span>
            </div>
        </div>

        <!-- SIGNATURES -->
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-title"><?= htmlspecialchars(__('Billing::sig_provider_title')) ?></div>
                <div class="sig-sub"><?= htmlspecialchars(__('Billing::sig_provider_sub')) ?></div>
                <div class="sig-line"></div>
                <div class="sig-note"><?= htmlspecialchars(__('Billing::sig_stamp')) ?></div>
            </div>
            <div class="sig-block">
                <div class="sig-title"><?= htmlspecialchars(__('Billing::sig_client_title')) ?></div>
                <div class="sig-sub"><?= htmlspecialchars($billing['project_title']) ?> <?= htmlspecialchars(__('Billing::sig_representative')) ?></div>
                <div class="sig-line"></div>
                <div class="sig-note"><?= htmlspecialchars(__('Billing::sig_approval')) ?></div>
            </div>
        </div>
    </div>

    <?= \Core\AssetManager::renderScripts() ?>
</body>
</html>
