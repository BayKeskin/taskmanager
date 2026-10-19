<?php
// TaskManager Takvim & Gantt Şeması Görünümü (Calendar & Timeline/Gantt View)
$system->title = __('Calendar::gantt_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$viewTab = isset($_GET['tab']) && $_GET['tab'] === 'calendar' ? 'calendar' : 'gantt'; // Varsayılan modern Gantt
$ganttScale = isset($_GET['scale']) && in_array($_GET['scale'], ['week', 'month']) ? $_GET['scale'] : 'month';

$calendarController = new \Modules\Calendar\Controllers\CalendarController();
$calendarData = $calendarController->index($year, $month, $activeProjectId, $ganttScale);
extract($calendarData);

// Önceki ve Sonraki Ay Linkleri
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$todayStr = date('Y-m-d');

// Gantt için Başlangıç ve Bitiş Tarih Aralığını Hesapla
if ($ganttScale === 'week') {
    // Mevcut günün haftası (Pazartesi - Pazar)
    $ts = strtotime($todayStr);
    $dayOfWeek = (int)date('N', $ts);
    $ganttStartTs = strtotime("-" . ($dayOfWeek - 1) . " days", $ts);
    $ganttEndTs = strtotime("+6 days", $ganttStartTs);
    $ganttDaysCount = 7;
} else {
    // Seçili ayın tüm günleri
    $ganttStartTs = mktime(0, 0, 0, $month, 1, $year);
    $ganttDaysCount = $daysInMonth;
    $ganttEndTs = mktime(0, 0, 0, $month, $daysInMonth, $year);
}

$timelineDays = [];
for ($i = 0; $i < $ganttDaysCount; $i++) {
    $dTs = strtotime("+$i days", $ganttStartTs);
    $dStr = date('Y-m-d', $dTs);
    $timelineDays[] = [
        'date'     => $dStr,
        'dayNum'   => (int)date('j', $dTs),
        'dayName'  => date('D', $dTs),
        'isToday'  => ($dStr === $todayStr),
        'isWeekend'=> in_array(date('N', $dTs), [6, 7])
    ];
}

$statusColors = [
    'assigned'               => '#4f8ff7',
    'accepted'               => '#00b4d8',
    'in_progress'            => '#9b5de5',
    'modification_requested' => '#e67e22',
    'submitted'              => '#e056fd',
    'completed'              => '#00b894'
];
?>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header cu-cal-header">
    <div class="cu-view-title-wrap cu-cal-title-wrap">
        <span class="cu-cal-title-icon">📊</span>
        <div>
            <span class="cu-cal-title-text"><?= __('Calendar::gantt_title') ?></span>
            <?php if (!empty($activeProjectId)): ?>
                <span class="cu-cal-project-badge">(#<?= (int)$activeProjectId ?>)</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- GÖRÜNÜM GEÇİŞİ: GANTT vs STANDART TAKVİM -->
    <div class="cu-cal-tab-group">
        <a href="/taskmanager/calendar?tab=gantt&scale=<?= $ganttScale ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
           class="cu-cal-tab-btn <?= $viewTab === 'gantt' ? 'active' : '' ?>">
            <span>📊</span> <span><?= __('Calendar::gantt_view_tab') ?></span>
        </a>
        <a href="/taskmanager/calendar?tab=calendar&year=<?= $year ?>&month=<?= $month ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
           class="cu-cal-tab-btn <?= $viewTab === 'calendar' ? 'active' : '' ?>">
            <span>📅</span> <span><?= __('Calendar::cal_view_tab') ?></span>
        </a>
    </div>

    <!-- GANTT ÖLÇEK & AY NAVİGASYONU -->
    <div class="cu-cal-toolbar">
        <?php if ($viewTab === 'gantt'): ?>
            <!-- Haftalık / Aylık Filtre Sekmeleri -->
            <div class="cu-cal-filter-group">
                <a href="/taskmanager/calendar?tab=gantt&scale=week<?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
                   class="cu-cal-filter-btn <?= $ganttScale === 'week' ? 'active' : '' ?>">
                    <?= __('Calendar::gantt_filter_week') ?>
                </a>
                <a href="/taskmanager/calendar?tab=gantt&scale=month&year=<?= $year ?>&month=<?= $month ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
                   class="cu-cal-filter-btn <?= $ganttScale === 'month' ? 'active' : '' ?>">
                    <?= __('Calendar::gantt_filter_month') ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($viewTab === 'calendar' || $ganttScale === 'month'): ?>
            <a href="/taskmanager/calendar?tab=<?= $viewTab ?>&scale=<?= $ganttScale ?>&year=<?= date('Y') ?>&month=<?= date('n') ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm cu-cal-btn-today">
                <?= __('Calendar::cal_today_badge') ?>
            </a>
            <a href="/taskmanager/calendar?tab=<?= $viewTab ?>&scale=<?= $ganttScale ?>&year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm cu-cal-nav-btn">
                <?= __('Calendar::cal_btn_prev') ?>
            </a>
            <h3 class="cu-cal-month-title">
                <?= htmlspecialchars($monthNames[$month] ?? '') ?> <?= $year ?>
            </h3>
            <a href="/taskmanager/calendar?tab=<?= $viewTab ?>&scale=<?= $ganttScale ?>&year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm cu-cal-nav-btn">
                <?= __('Calendar::cal_btn_next') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="cu-content-scroll cu-cal-content">

    <!-- ====================================================================
         1. GANTT / TIMELINE GÖRÜNÜMÜ (Frappe / DHTMLX Tarzı Vanilla JS)
         ==================================================================== -->
    <?php if ($viewTab === 'gantt'): ?>
        <div class="cu-gantt-card">
            
            <!-- Bilgi ve Legend Çubuğu -->
            <div class="cu-gantt-legend-bar">
                <div class="cu-gantt-tip">
                    <span>💡 <b><?= __('Calendar::gantt_tip') ?></b></span>
                </div>
                <div class="cu-gantt-legend-items">
                    <div class="cu-gantt-legend-item">
                        <span class="cu-gantt-legend-dot overdue"></span>
                        <span class="cu-gantt-legend-text overdue"><?= __('Calendar::gantt_overdue_badge') ?></span>
                    </div>
                    <div class="cu-gantt-legend-item">
                        <span class="cu-gantt-legend-dot completed"></span>
                        <span class="cu-gantt-legend-text completed"><?= __('Calendar::col_completed') ?></span>
                    </div>
                    <div class="cu-gantt-legend-item">
                        <span class="cu-gantt-legend-dot in-progress"></span>
                        <span class="cu-gantt-legend-text in-progress"><?= __('Calendar::col_in_progress') ?></span>
                    </div>
                </div>
            </div>

            <!-- GANTT IZGARA ALANI -->
            <div id="ganttTimelineContainer" class="cu-gantt-timeline-container">
                <div class="cu-gantt-timeline-inner" data-timeline-days-count="<?= (int)$ganttDaysCount ?>">
                    
                    <!-- Gantt Başlık Satırı -->
                    <div class="cu-gantt-header-row">
                        <!-- Sol Sabit Sütun: Görev Başlığı -->
                        <div class="cu-gantt-col-task-title">
                            📋 <?= __('Calendar::gantt_col_task') ?>
                        </div>
                        <!-- Tarih Günleri Başlıkları -->
                        <div class="cu-gantt-days-row">
                            <?php foreach ($timelineDays as $td): ?>
                                <div class="gantt-day-col-header <?= $td['isToday'] ? 'today-col' : '' ?> <?= $td['isWeekend'] ? 'is-weekend' : '' ?>" 
                                     data-date="<?= $td['date'] ?>">
                                    <div class="cu-gantt-day-name"><?= substr($td['dayName'], 0, 2) ?></div>
                                    <div class="cu-gantt-day-num <?= $td['isToday'] ? 'is-today' : '' ?>">
                                        <?= $td['dayNum'] ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Görev Satırları -->
                    <?php 
                    $displayGanttTasks = !empty($allGanttTasks) ? $allGanttTasks : [];
                    if (empty($displayGanttTasks)): 
                    ?>
                        <div class="cu-gantt-empty">
                            <?= __('Calendar::gantt_no_tasks') ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($displayGanttTasks as $gt): 
                            $isOverdue = (!empty($gt->deadline) && $gt->deadline < $todayStr && $gt->status !== 'completed');
                            $gtColor = $isOverdue ? '#e74c3c' : ($statusColors[$gt->status] ?? '#7B68EE');
                            
                            // Başlangıç tarihi (varsa created_at yoksa deadline)
                            $createdDate = !empty($gt->created_at) ? substr($gt->created_at, 0, 10) : $gt->deadline;
                            $deadlineDate = $gt->deadline;
                        ?>
                            <div class="gantt-row">
                                <!-- Sol Başlık ve Atanan -->
                                <div class="cu-gantt-row-title" data-open-task-id="<?= (int)$gt->id ?>">
                                    <div class="cu-gantt-title-ellipsis">
                                        <span class="cu-gantt-task-code cu-dynamic-color" data-color="<?= htmlspecialchars($gtColor) ?>"><?= htmlspecialchars($gt->task_code) ?></span>
                                        <span class="cu-gantt-task-name" title="<?= htmlspecialchars($gt->title) ?>"><?= htmlspecialchars($gt->title) ?></span>
                                    </div>
                                    <div class="cu-gantt-assignee-box">
                                        <?php if (!empty($gt->assignee_name)): ?>
                                            <div class="cu-avatar cu-gantt-avatar cu-dynamic-bg" data-bg="<?= htmlspecialchars($gt->assignee_color ?? '#7B68EE') ?>" title="<?= htmlspecialchars($gt->assignee_name) ?>">
                                                <?= mb_substr($gt->assignee_name, 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Sağ Timeline Gün Izgarası & Görev Çubuğu -->
                                <div class="cu-gantt-timeline-track">
                                    <!-- Gün Arka Plan Hücreleri (Drop Hedefleri) -->
                                    <?php foreach ($timelineDays as $td): ?>
                                        <div class="gantt-drop-cell <?= $td['isToday'] ? 'is-today' : '' ?> <?= $td['isWeekend'] ? 'is-weekend' : '' ?>" 
                                             data-task-id="<?= (int)$gt->id ?>"
                                             data-target-date="<?= $td['date'] ?>">
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Görev Çubuğu (Bar) -->
                                    <?php
                                    // Gün hücrelerinin başlangıç indexini bul
                                    $colStartIndex = null;
                                    $colEndIndex = null;

                                    foreach ($timelineDays as $idx => $td) {
                                        if ($td['date'] === $deadlineDate) {
                                            $colEndIndex = $idx;
                                        }
                                        if ($td['date'] === $createdDate) {
                                            $colStartIndex = $idx;
                                        }
                                    }

                                    // Eğer aralık dışında kalıyorsa hesapla
                                    if ($colEndIndex !== null) {
                                        if ($colStartIndex === null || $colStartIndex > $colEndIndex) {
                                            // En az 2 gün veya tek gün göster
                                            $colStartIndex = max(0, $colEndIndex - 1);
                                        }
                                        $barLeft = ($colStartIndex * 44) + 4;
                                        $barWidth = max(36, (($colEndIndex - $colStartIndex + 1) * 44) - 8);
                                        $barBg = $isOverdue 
                                            ? 'linear-gradient(135deg, #e74c3c, #c0392b)' 
                                            : 'linear-gradient(135deg, ' . $gtColor . ', ' . adjustColorBrightness($gtColor, -20) . ')';
                                    ?>
                                        <div class="gantt-task-bar <?= $isOverdue ? 'overdue-pulse is-overdue' : '' ?>"
                                             draggable="true"
                                             data-task-id="<?= (int)$gt->id ?>"
                                             data-open-task-id="<?= (int)$gt->id ?>"
                                             data-deadline="<?= htmlspecialchars($gt->deadline ?? '') ?>"
                                             data-bar-left="<?= (int)$barLeft ?>"
                                             data-bar-width="<?= (int)$barWidth ?>"
                                             data-bar-bg="<?= htmlspecialchars($barBg) ?>"
                                             title="<?= htmlspecialchars($gt->task_code . ': ' . $gt->title) ?> | <?= htmlspecialchars($gt->deadline ?? '') ?>">
                                            <span><?= $isOverdue ? '⚠️ ' : '' ?></span>
                                            <span class="cu-gantt-task-bar-text"><?= htmlspecialchars($gt->task_code) ?> <?= htmlspecialchars($gt->title) ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- ====================================================================
         2. STANDART TAKVİM GÖRÜNÜMÜ (Ay / Gün Izgarası)
         ==================================================================== -->
    <?php else: ?>
        <div class="cu-calendar-scroll-wrap">
            <div class="cu-calendar-grid-inner">
                <!-- Hafta Günleri Başlığı -->
                <div class="cu-calendar-weekdays-header">
                    <div><?= __('Calendar::cal_days.mon') ?></div>
                    <div><?= __('Calendar::cal_days.tue') ?></div>
                    <div><?= __('Calendar::cal_days.wed') ?></div>
                    <div><?= __('Calendar::cal_days.thu') ?></div>
                    <div><?= __('Calendar::cal_days.fri') ?></div>
                    <div><?= __('Calendar::cal_days.sat') ?></div>
                    <div><?= __('Calendar::cal_days.sun') ?></div>
                </div>

                <!-- Takvim Izgarası -->
                <div class="cu-calendar-grid-body">
                    <!-- Önceki aydan boş hücreler -->
                    <?php for ($i = 1; $i < $firstDayOfWeek; $i++): ?>
                        <div class="cu-calendar-empty-cell"></div>
                    <?php endfor; ?>

                    <!-- Ayın Günleri -->
                    <?php 
                    for ($d = 1; $d <= $daysInMonth; $d++): 
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $isToday = ($dateStr === $todayStr);
                        $dayTasks = $tasksByDate[$dateStr] ?? [];
                    ?>
                        <div class="cal-day-cell <?= $isToday ? 'is-today' : '' ?>" 
                             data-date="<?= $dateStr ?>"
                             title="<?= htmlspecialchars(format_date_localized($dateStr)) ?>">
                            <div class="cu-cal-day-top">
                                <span class="cu-cal-day-num <?= $isToday ? 'is-today' : '' ?>">
                                    <?= $d ?>
                                </span>
                                <?php if ($isToday): ?>
                                    <span class="cu-cal-today-badge"><?= __('Calendar::cal_today_badge') ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Görev Hapları (Sürüklenebilir) -->
                            <div class="cu-cal-tasks-list">
                                <?php foreach ($dayTasks as $t): 
                                    $isOverdue = (!empty($t->deadline) && $t->deadline < $todayStr && $t->status !== 'completed');
                                    $cColor = $isOverdue ? '#e74c3c' : ($statusColors[$t->status] ?? '#7B68EE');
                                ?>
                                    <div class="cal-task-pill cu-dynamic-border <?= $isOverdue ? 'is-overdue' : '' ?>"
                                         draggable="true"
                                         data-task-id="<?= (int)$t->id ?>"
                                         data-open-task-id="<?= (int)$t->id ?>"
                                         data-border-left-color="<?= htmlspecialchars($cColor) ?>"
                                         title="<?= htmlspecialchars($t->title) ?> (<?= htmlspecialchars($t->task_code) ?>)">
                                        <span class="cu-dynamic-color" data-color="<?= htmlspecialchars($cColor) ?>"><?= htmlspecialchars($t->task_code) ?></span>
                                        <span class="cu-cal-task-title-text"><?= htmlspecialchars($t->title) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php 
function adjustColorBrightness($hex, $steps) {
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
    }
    $colorParts = str_split($hex, 2);
    $return = '#';
    foreach ($colorParts as $color) {
        $color   = hexdec($color);
        $color   = max(0, min(255, $color + $steps));
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
    }
    return $return;
}
include dirname(__DIR__, 3) . '/Core/Views/footer.php'; 
?>
