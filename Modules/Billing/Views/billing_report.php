<?php
/**
 * TaskManager - Hakediş & İş Teslim Raporu (PDF Baskı ve Önizleme Şablonu)
 * TSK-194: Müşteri Portalı ve Raporlar İçin Efor, Maliyet ve Hakediş Özeti
 */
$currentUser = $_SESSION['user'] ?? null;
$billingController = new \Modules\Billing\Controllers\BillingController();

$projectId = isset($_GET['project_id']) && (int)$_GET['project_id'] > 0 ? (int)$_GET['project_id'] : null;
$isClient = ($currentUser->role === 'client');
if ($isClient && !empty($currentUser->client_project_id)) {
    $projectId = (int)$currentUser->client_project_id;
}

$hourlyRate = isset($_GET['rate']) && (float)$_GET['rate'] > 0 ? (float)$_GET['rate'] : null;
$billing = $billingController->getBillingData($projectId, $hourlyRate);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Language::current(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($billing['project_title']) ?> - <?= __('tasks.pdf_report_title', 'Hakediş ve İş Teslim Raporu') ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Billing Report Scoped CSS -->
    <link rel="stylesheet" href="/taskmanager/Modules/Billing/Assets/css/billing-report.css">
</head>
<body class="billing-report-page">

    <div class="action-bar">
        <a href="/taskmanager/reports" class="btn-back">⬅ <?= __('tasks.btn_back_reports', 'Raporlara Geri Dön') ?></a>
        <button type="button" class="btn-print" onclick="window.print()">
            <span>🖨️</span> <span><?= __('tasks.btn_print_pdf', 'PDF Olarak Kaydet / Yazdır') ?></span>
        </button>
    </div>

    <div class="report-page">
        <!-- HEADER -->
        <div class="header-bar">
            <div class="brand-wrap">
                <div class="brand-logo">TM</div>
                <div>
                    <h1 class="brand-title">TaskManager Enterprise</h1>
                    <div class="brand-sub"><?= __('tasks.report_header_sub', 'Resmi Hakediş, Efor ve İş Teslimat Belgesi') ?></div>
                </div>
            </div>
            <div class="report-meta">
                <div><b><?= __('tasks.rep_th_project', 'Proje:') ?></b> <?= htmlspecialchars($billing['project_title']) ?> (<?= htmlspecialchars($billing['project_code']) ?>)</div>
                <div><b><?= __('tasks.report_date_label', 'Rapor Tarihi:') ?></b> <?= htmlspecialchars($billing['generated_at']) ?></div>
                <div><b><?= __('tasks.report_status_label', 'Durum:') ?></b> <span style="color:var(--success); font-weight:700;">Tamamlandı & Teslim Edildi</span></div>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-label"><?= __('tasks.billing_kpi_tasks', 'Tamamlanan Görev') ?></div>
                <div class="kpi-val" style="color:#0984e3;"><?= $billing['total_completed_tasks'] ?></div>
                <div class="kpi-sub">Teslim edilen iş paketi</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><?= __('tasks.billing_kpi_spent', 'Toplam Harcanan Efor') ?></div>
                <div class="kpi-val" style="color:var(--primary);"><?= $billing['total_hours_spent'] ?> sa</div>
                <div class="kpi-sub">Tahmini: <?= $billing['total_hours_estimated'] ?> sa</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Saatlik Birim Ücret</div>
                <div class="kpi-val"><?= number_format($billing['hourly_rate'], 0, ',', '.') ?> TL</div>
                <div class="kpi-sub">Sözleşmeli birim oran</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><?= __('tasks.billing_kpi_total_cost', 'Toplam Hakediş Bedeli') ?></div>
                <div class="kpi-val" style="color:var(--success);"><?= number_format($billing['total_cost'], 2, ',', '.') ?> TL</div>
                <div class="kpi-sub">KDV Hariç Net Tutar</div>
            </div>
        </div>

        <!-- TASKS TABLE -->
        <table class="task-table">
            <thead>
                <tr>
                    <th style="width:110px;">Kod</th>
                    <th>Görev Başlığı ve Teslim Notları</th>
                    <th style="width:120px;">Sorumlu</th>
                    <th style="width:80px; text-align:center;">Harcanan</th>
                    <th style="width:110px; text-align:right;">Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($billing['tasks'])): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">
                            Bu proje için henüz onaylanmış ve tamamlanmış bir görev bulunmuyor.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($billing['tasks'] as $t): ?>
                        <tr>
                            <td>
                                <span class="task-code"><?= htmlspecialchars($t['task_code']) ?></span>
                            </td>
                            <td>
                                <div style="font-weight:700; color:var(--dark); margin-bottom:2px;">
                                    <?= htmlspecialchars($t['title']) ?>
                                </div>
                                <?php if (!empty($t['delivery_notes'])): ?>
                                    <div class="notes-box">
                                        <b>🔍 Teslimat Notu & Kanıtı:</b><br>
                                        <?= htmlspecialchars(mb_strimwidth($t['delivery_notes'], 0, 200, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['assignee_name'] ?: 'Ekip') ?></td>
                            <td style="text-align:center; font-weight:600;"><?= $t['spent_hours'] ?> sa</td>
                            <td style="text-align:right; font-weight:700; color:var(--success);">
                                <?= number_format($t['task_cost'], 2, ',', '.') ?> TL
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SUMMARY TOTALS -->
        <div class="summary-totals">
            <div class="total-row">
                <span>Tamamlanan Görev Sayısı:</span>
                <b><?= $billing['total_completed_tasks'] ?> Adet</b>
            </div>
            <div class="total-row">
                <span>Toplam Çalışma Süresi:</span>
                <b><?= $billing['total_hours_spent'] ?> Saat</b>
            </div>
            <div class="total-row">
                <span>Birim Saatlik Ücret:</span>
                <b><?= number_format($billing['hourly_rate'], 2, ',', '.') ?> TL / sa</b>
            </div>
            <div class="total-row grand">
                <span>Genel Hakediş Toplamı:</span>
                <span><?= number_format($billing['total_cost'], 2, ',', '.') ?> TL</span>
            </div>
        </div>

        <!-- SIGNATURES -->
        <div class="signatures">
            <div class="sig-block">
                <div style="font-weight:700; font-size:13px;">Geliştirici & Hizmet Sağlayıcı</div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">TaskManager / Yazılım & Danışmanlık Ekibi</div>
                <div class="sig-line"></div>
                <div style="font-size:11px; color:var(--text-muted);">İmza & Kaşe</div>
            </div>
            <div class="sig-block">
                <div style="font-weight:700; font-size:13px;">Müşteri / Proje Yetkilisi</div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($billing['project_title']) ?> Temsilcisi</div>
                <div class="sig-line"></div>
                <div style="font-size:11px; color:var(--text-muted);">İş Teslim Onayı & İmza</div>
            </div>
        </div>
    </div>

</body>
</html>
