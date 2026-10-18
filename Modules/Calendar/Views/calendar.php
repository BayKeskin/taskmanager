<?php
// TaskManager Takvim & Gantt Şeması Görünümü (Calendar & Timeline/Gantt View)
$system->title = __('Calendar::gantt_title', 'Gantt & Zaman Çizelgesi - TaskManager');
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
<div class="cu-view-header" style="flex-wrap:wrap; gap:12px;">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:12px;">
        <span style="font-size:18px;">📊</span>
        <div>
            <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= __('tasks.gantt_title', 'Gantt Şeması ve Takvim') ?></span>
            <?php if (!empty($activeProjectId)): ?>
                <span style="font-size:11px; color:var(--text-muted); margin-left:6px;">(#<?= (int)$activeProjectId ?>)</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- GÖRÜNÜM GEÇİŞİ: GANTT vs STANDART TAKVİM -->
    <div style="display:inline-flex; background:rgba(255,255,255,0.05); padding:3px; border-radius:8px; border:1px solid var(--border-subtle);">
        <a href="/taskmanager/calendar?tab=gantt&scale=<?= $ganttScale ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
           style="padding:5px 14px; font-size:12px; font-weight:700; text-decoration:none; border-radius:6px; display:inline-flex; align-items:center; gap:6px; transition:var(--transition); <?= $viewTab === 'gantt' ? 'background:var(--brand-primary); color:#ffffff; box-shadow:0 2px 8px rgba(123,104,238,0.3);' : 'color:var(--text-muted);' ?>">
            <span>📊</span> <span><?= __('tasks.gantt_view_tab', 'Gantt Şeması') ?></span>
        </a>
        <a href="/taskmanager/calendar?tab=calendar&year=<?= $year ?>&month=<?= $month ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
           style="padding:5px 14px; font-size:12px; font-weight:700; text-decoration:none; border-radius:6px; display:inline-flex; align-items:center; gap:6px; transition:var(--transition); <?= $viewTab === 'calendar' ? 'background:var(--brand-primary); color:#ffffff; box-shadow:0 2px 8px rgba(123,104,238,0.3);' : 'color:var(--text-muted);' ?>">
            <span>📅</span> <span><?= __('tasks.cal_view_tab', 'Standart Takvim') ?></span>
        </a>
    </div>

    <!-- GANTT ÖLÇEK & AY NAVİGASYONU -->
    <div style="display:flex; align-items:center; gap:8px; margin-left:auto; flex-wrap:wrap;">
        <?php if ($viewTab === 'gantt'): ?>
            <!-- Haftalık / Aylık Filtre Sekmeleri -->
            <div style="display:inline-flex; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:6px; padding:2px; margin-right:8px;">
                <a href="/taskmanager/calendar?tab=gantt&scale=week<?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
                   style="padding:4px 10px; font-size:11.5px; font-weight:600; text-decoration:none; border-radius:4px; <?= $ganttScale === 'week' ? 'background:rgba(0,184,148,0.2); color:#00cec9;' : 'color:var(--text-secondary);' ?>">
                    <?= __('tasks.gantt_filter_week', 'Haftalık') ?>
                </a>
                <a href="/taskmanager/calendar?tab=gantt&scale=month&year=<?= $year ?>&month=<?= $month ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" 
                   style="padding:4px 10px; font-size:11.5px; font-weight:600; text-decoration:none; border-radius:4px; <?= $ganttScale === 'month' ? 'background:rgba(0,184,148,0.2); color:#00cec9;' : 'color:var(--text-secondary);' ?>">
                    <?= __('tasks.gantt_filter_month', 'Aylık') ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($viewTab === 'calendar' || $ganttScale === 'month'): ?>
            <a href="/taskmanager/calendar?tab=<?= $viewTab ?>&scale=<?= $ganttScale ?>&year=<?= date('Y') ?>&month=<?= date('n') ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm" style="text-decoration:none; background:rgba(255,255,255,0.06); font-weight:600;">
                <?= __('tasks.cal_today_badge', 'Bugün') ?>
            </a>
            <a href="/taskmanager/calendar?tab=<?= $viewTab ?>&scale=<?= $ganttScale ?>&year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm" style="text-decoration:none;">
                <?= __('tasks.cal_btn_prev', '◀ Önceki') ?>
            </a>
            <h3 style="font-size:13.5px; font-weight:700; color:var(--text-white); margin:0 4px; min-width:130px; text-align:center;">
                <?= htmlspecialchars($monthNames[$month] ?? '') ?> <?= $year ?>
            </h3>
            <a href="/taskmanager/calendar?tab=<?= $viewTab ?>&scale=<?= $ganttScale ?>&year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm" style="text-decoration:none;">
                <?= __('tasks.cal_btn_next', 'Sonraki ▶') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="cu-content-scroll" style="display:flex; flex-direction:column; padding:16px;">

    <!-- ====================================================================
         1. GANTT / TIMELINE GÖRÜNÜMÜ (Frappe / DHTMLX Tarzı Vanilla JS)
         ==================================================================== -->
    <?php if ($viewTab === 'gantt'): ?>
        <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow:hidden; display:flex; flex-direction:column; box-shadow:0 4px 16px rgba(0,0,0,0.2);">
            
            <!-- Bilgi ve Legend Çubuğu -->
            <div style="padding:12px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sidebar); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:14px; font-size:11.5px; color:var(--text-secondary);">
                    <span>💡 <b>İpucu:</b> Görev çubuklarını takvim günlerine sürükleyerek teslim tarihini anında güncelleyebilirsiniz.</span>
                </div>
                <div style="display:flex; align-items:center; gap:12px; font-size:11.5px;">
                    <div style="display:flex; align-items:center; gap:4px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#e74c3c; border:1px solid rgba(231,76,60,0.8);"></span>
                        <span style="color:#e74c3c; font-weight:700;"><?= __('tasks.gantt_overdue_badge', 'Gecikmiş / Riskli') ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#00b894;"></span>
                        <span style="color:#00b894; font-weight:600;"><?= __('tasks.col_completed', 'Tamamlandı') ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#9b5de5;"></span>
                        <span style="color:#9b5de5; font-weight:600;"><?= __('tasks.col_in_progress', 'Çalışılıyor') ?></span>
                    </div>
                </div>
            </div>

            <!-- GANTT IZGARA ALANI -->
            <div id="ganttTimelineContainer" style="overflow-x:auto; overflow-y:auto; max-height:calc(100vh - 220px); position:relative;">
                <div style="min-width:<?= 280 + ($ganttDaysCount * 44) ?>px; display:flex; flex-direction:column;">
                    
                    <!-- Gantt Başlık Satırı -->
                    <div style="display:flex; position:sticky; top:0; z-index:10; background:var(--bg-main); border-bottom:2px solid var(--border-subtle);">
                        <!-- Sol Sabit Sütun: Görev Başlığı -->
                        <div style="width:280px; min-width:280px; padding:10px 14px; font-size:12px; font-weight:700; color:var(--text-white); border-right:1px solid var(--border-subtle); display:flex; align-items:center; background:var(--bg-sidebar);">
                            📋 Görev ve Sorumlu
                        </div>
                        <!-- Tarih Günleri Başlıkları -->
                        <div style="display:flex; flex:1;">
                            <?php foreach ($timelineDays as $td): ?>
                                <div class="gantt-day-col-header <?= $td['isToday'] ? 'today-col' : '' ?>" 
                                     data-date="<?= $td['date'] ?>"
                                     style="width:44px; min-width:44px; text-align:center; padding:6px 0; border-right:1px solid rgba(255,255,255,0.05); <?= $td['isToday'] ? 'background:rgba(123,104,238,0.2); border-bottom:2px solid var(--brand-primary);' : ($td['isWeekend'] ? 'background:rgba(255,255,255,0.02);' : '') ?>">
                                    <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase;"><?= substr($td['dayName'], 0, 2) ?></div>
                                    <div style="font-size:12px; font-weight:<?= $td['isToday'] ? '800' : '600' ?>; color:<?= $td['isToday'] ? 'var(--brand-primary)' : 'var(--text-white)' ?>;">
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
                        <div style="padding:40px; text-align:center; color:var(--text-muted); font-size:13px;">
                            <?= __('tasks.gantt_no_tasks', 'Bu görünüm için henüz takvimli görev bulunmamaktadır.') ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($displayGanttTasks as $gt): 
                            $isOverdue = (!empty($gt->deadline) && $gt->deadline < $todayStr && $gt->status !== 'completed');
                            $gtColor = $isOverdue ? '#e74c3c' : ($statusColors[$gt->status] ?? '#7B68EE');
                            
                            // Başlangıç tarihi (varsa created_at yoksa deadline)
                            $createdDate = !empty($gt->created_at) ? substr($gt->created_at, 0, 10) : $gt->deadline;
                            $deadlineDate = $gt->deadline;
                        ?>
                            <div class="gantt-row" style="display:flex; border-bottom:1px solid rgba(255,255,255,0.05); align-items:center; transition:background 0.2s;" onmouseenter="this.style.background='rgba(255,255,255,0.02)'" onmouseleave="this.style.background='transparent'">
                                <!-- Sol Başlık ve Atanan -->
                                <div style="width:280px; min-width:280px; padding:8px 14px; border-right:1px solid var(--border-subtle); display:flex; align-items:center; justify-content:space-between; gap:8px; cursor:pointer;" onclick="openTaskDrawer(<?= $gt->id ?>)">
                                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <span style="font-size:11px; font-weight:700; color:<?= $gtColor ?>;"><?= htmlspecialchars($gt->task_code) ?></span>
                                        <span style="font-size:12px; color:var(--text-white); margin-left:4px;" title="<?= htmlspecialchars($gt->title) ?>"><?= htmlspecialchars($gt->title) ?></span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
                                        <?php if (!empty($gt->assignee_name)): ?>
                                            <div class="cu-avatar" style="width:20px; height:20px; font-size:9.5px; background:<?= htmlspecialchars($gt->assignee_color ?? '#7B68EE') ?>;" title="<?= htmlspecialchars($gt->assignee_name) ?>">
                                                <?= mb_substr($gt->assignee_name, 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Sağ Timeline Gün Izgarası & Görev Çubuğu -->
                                <div style="display:flex; flex:1; position:relative; height:42px; align-items:center;">
                                    <!-- Gün Arka Plan Hücreleri (Drop Hedefleri) -->
                                    <?php foreach ($timelineDays as $td): ?>
                                        <div class="gantt-drop-cell" 
                                             data-task-id="<?= $gt->id ?>"
                                             data-target-date="<?= $td['date'] ?>"
                                             ondragover="handleGanttDragOver(event)"
                                             ondragleave="handleGanttDragLeave(event)"
                                             ondrop="handleGanttDrop(event, <?= $gt->id ?>, '<?= $td['date'] ?>')"
                                             style="width:44px; min-width:44px; height:100%; border-right:1px solid rgba(255,255,255,0.04); <?= $td['isToday'] ? 'background:rgba(123,104,238,0.05);' : ($td['isWeekend'] ? 'background:rgba(255,255,255,0.015);' : '') ?>">
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
                                    ?>
                                        <div class="gantt-task-bar <?= $isOverdue ? 'overdue-pulse' : '' ?>"
                                             draggable="true"
                                             data-task-id="<?= $gt->id ?>"
                                             data-deadline="<?= $gt->deadline ?>"
                                             ondragstart="handleGanttDragStart(event, <?= $gt->id ?>)"
                                             onclick="openTaskDrawer(<?= $gt->id ?>)"
                                             title="<?= htmlspecialchars($gt->task_code . ': ' . $gt->title) ?> | Teslim: <?= htmlspecialchars($gt->deadline) ?><?= $isOverdue ? ' (GECİKTİ!)' : '' ?>"
                                             style="position:absolute; left:<?= $barLeft ?>px; width:<?= $barWidth ?>px; height:26px; border-radius:5px; background:<?= $isOverdue ? 'linear-gradient(135deg, #e74c3c, #c0392b)' : 'linear-gradient(135deg, '.$gtColor.', '.adjustColorBrightness($gtColor, -20).')' ?>; color:#fff; display:flex; align-items:center; padding:0 8px; font-size:11px; font-weight:700; cursor:grab; box-shadow:0 2px 6px rgba(0,0,0,0.3); z-index:2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border:1px solid <?= $isOverdue ? '#ff7675' : 'rgba(255,255,255,0.2)' ?>;">
                                            <span><?= $isOverdue ? '⚠️ ' : '' ?></span>
                                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($gt->task_code) ?> <?= htmlspecialchars($gt->title) ?></span>
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
        <div class="cu-calendar-scroll-wrap" style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); overflow-x:auto; -webkit-overflow-scrolling:touch; flex:1; display:flex; flex-direction:column;">
            <div class="cu-calendar-grid-inner" style="min-width:650px; display:flex; flex-direction:column; flex:1;">
                <!-- Hafta Günleri Başlığı -->
                <div style="display:grid; grid-template-columns:repeat(7, 1fr); background:var(--bg-sidebar); border-bottom:1px solid var(--border-subtle); text-align:center; font-size:11.5px; font-weight:700; color:var(--text-muted); padding:10px 0; text-transform:uppercase;">
                    <div><?= __('tasks.cal_days.mon', 'Mon') ?></div>
                    <div><?= __('tasks.cal_days.tue', 'Tue') ?></div>
                    <div><?= __('tasks.cal_days.wed', 'Wed') ?></div>
                    <div><?= __('tasks.cal_days.thu', 'Thu') ?></div>
                    <div><?= __('tasks.cal_days.fri', 'Fri') ?></div>
                    <div><?= __('tasks.cal_days.sat', 'Sat') ?></div>
                    <div><?= __('tasks.cal_days.sun', 'Sun') ?></div>
                </div>

                <!-- Takvim Izgarası -->
                <div style="display:grid; grid-template-columns:repeat(7, 1fr); flex:1; auto-rows:minmax(110px, 1fr); background:var(--bg-input); gap:1px;">
                    <!-- Önceki aydan boş hücreler -->
                    <?php for ($i = 1; $i < $firstDayOfWeek; $i++): ?>
                        <div style="background:var(--bg-app); opacity:0.3; padding:8px;"></div>
                    <?php endfor; ?>

                    <!-- Ayın Günleri -->
                    <?php 
                    for ($d = 1; $d <= $daysInMonth; $d++): 
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $isToday = ($dateStr === $todayStr);
                        $dayTasks = $tasksByDate[$dateStr] ?? [];
                    ?>
                        <div class="cal-day-cell" 
                             data-date="<?= $dateStr ?>"
                             ondragover="handleCalendarDragOver(event)"
                             ondragleave="handleCalendarDragLeave(event)"
                             ondrop="handleCalendarDrop(event, '<?= $dateStr ?>')"
                             style="background:var(--bg-card); padding:8px; display:flex; flex-direction:column; border-bottom:1px solid var(--border-subtle); border-right:1px solid var(--border-subtle); transition:background 0.2s; <?= $isToday ? 'outline:2px solid var(--brand-primary); z-index:1;' : '' ?>" title="<?= htmlspecialchars(Helper::format_date_localized($dateStr)) ?>">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <span style="font-size:12px; font-weight:<?= $isToday ? '800' : '600' ?>; color:<?= $isToday ? 'var(--brand-primary)' : 'var(--text-secondary)' ?>;">
                                    <?= $d ?>
                                </span>
                                <?php if ($isToday): ?>
                                    <span style="font-size:9px; font-weight:700; text-transform:uppercase; background:var(--brand-primary); color:#fff; padding:1px 4px; border-radius:3px;"><?= __('tasks.cal_today_badge', 'Bugün') ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Görev Hapları (Sürüklenebilir) -->
                            <div style="display:flex; flex-direction:column; gap:4px; overflow-y:auto; max-height:85px;">
                                <?php foreach ($dayTasks as $t): 
                                    $isOverdue = (!empty($t->deadline) && $t->deadline < $todayStr && $t->status !== 'completed');
                                    $cColor = $isOverdue ? '#e74c3c' : ($statusColors[$t->status] ?? '#7B68EE');
                                ?>
                                    <div draggable="true"
                                         data-task-id="<?= $t->id ?>"
                                         ondragstart="handleCalendarDragStart(event, <?= $t->id ?>)"
                                         onclick="openTaskDrawer(<?= $t->id ?>)" 
                                         style="background:var(--bg-app); border:1px solid var(--border-subtle); border-left:3px solid <?= $cColor ?>; border-radius:4px; padding:3px 6px; font-size:11px; cursor:grab; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; transition:var(--transition); <?= $isOverdue ? 'box-shadow:0 0 6px rgba(231,76,60,0.3);' : '' ?>" title="<?= htmlspecialchars($t->title) ?> (<?= htmlspecialchars($t->task_code) ?>)<?= $isOverdue ? ' - GECİKMİŞ!' : '' ?>">
                                        <span style="font-weight:700; color:<?= $cColor ?>;"><?= htmlspecialchars($t->task_code) ?></span>
                                        <span style="color:var(--text-primary); margin-left:2px;"><?= htmlspecialchars($t->title) ?></span>
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

<!-- CSS VE JAVASCRIPT: GANTT & CALENDAR DRAG-DROP ETKİLEŞİMİ -->
<style>
.gantt-task-bar:hover {
    filter: brightness(1.15);
    transform: translateY(-1px);
}
.gantt-drop-cell.drag-hover {
    background: rgba(0, 184, 148, 0.25) !important;
    outline: 2px dashed #00cec9;
}
.cal-day-cell.drag-hover {
    background: rgba(123, 104, 238, 0.2) !important;
    outline: 2px dashed var(--brand-primary);
}
@keyframes pulseOverdue {
    0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.6); }
    70% { box-shadow: 0 0 0 6px rgba(231, 76, 60, 0); }
    100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
}
.overdue-pulse {
    animation: pulseOverdue 2s infinite;
}
</style>

<script>
let draggedGanttTaskId = null;

function handleGanttDragStart(e, taskId) {
    draggedGanttTaskId = taskId;
    e.dataTransfer.setData('text/plain', taskId);
    e.dataTransfer.effectAllowed = 'move';
}

function handleGanttDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-hover');
}

function handleGanttDragLeave(e) {
    e.currentTarget.classList.remove('drag-hover');
}

function handleGanttDrop(e, taskId, newDate) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-hover');
    const tId = draggedGanttTaskId || taskId;
    if (!tId || !newDate) return;

    updateTaskDeadlineAjax(tId, newDate);
}

// Standart Takvim Drag-Drop
let draggedCalendarTaskId = null;

function handleCalendarDragStart(e, taskId) {
    draggedCalendarTaskId = taskId;
    e.dataTransfer.setData('text/plain', taskId);
    e.dataTransfer.effectAllowed = 'move';
}

function handleCalendarDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-hover');
}

function handleCalendarDragLeave(e) {
    e.currentTarget.classList.remove('drag-hover');
}

function handleCalendarDrop(e, newDate) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-hover');
    if (!draggedCalendarTaskId || !newDate) return;

    updateTaskDeadlineAjax(draggedCalendarTaskId, newDate);
}

// Ortak AJAX Teslim Tarihi Güncelleyici
function updateTaskDeadlineAjax(taskId, newDate) {
    const fd = new FormData();
    fd.append('action', 'update_task_field');
    fd.append('task_id', taskId);
    fd.append('field', 'deadline');
    fd.append('value', newDate);

    fetch('/taskmanager/ajax/update_task_field', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (typeof showToast === 'function') {
                showToast('<?= __('tasks.gantt_drag_success') ?>' + newDate, 'success');
            } else {
                alert('<?= __('tasks.gantt_drag_success') ?>' + newDate);
            }
            setTimeout(() => location.reload(), 400);
        } else {
            alert('❌ ' + (res.message || 'Tarih güncellenemedi.'));
        }
    })
    .catch(err => alert('Sunucu hatası: ' + err.message));
}
</script>

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
