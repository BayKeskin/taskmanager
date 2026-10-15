<?php
// TaskManager Takvim Görünümü (Calendar View)
require_once __DIR__ . '/../../Controller/CalendarController.php';
require_once __DIR__ . '/../../System/Language.php';
require_once __DIR__ . '/../../System/Helper.php';

Language::init();
$system->title = __('tasks.cal_title', 'Calendar - TaskManager');
include __DIR__ . '/../../Components/header.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : null;
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

$calendarController = new CalendarController($system);
$calendarData = $calendarController->index($year, $month, $activeProjectId);
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
?>

<div class="cu-view-header">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">📅</span>
        <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= __('tasks.view_calendar', 'Takvim') ?></span>
    </div>

    <!-- Ay Navigasyonu -->
    <div style="display:flex; align-items:center; gap:10px;">
        <a href="/taskmanager/calendar?year=<?= date('Y') ?>&month=<?= date('n') ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm" style="text-decoration:none; background:rgba(255,255,255,0.06); font-weight:600;">
            <?= __('tasks.cal_today_badge', 'Bugün') ?>
        </a>
        <a href="/taskmanager/calendar?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm" style="text-decoration:none;">
            <?= __('tasks.cal_btn_prev', '◀ Önceki') ?>
        </a>
        <h3 style="font-size:14px; font-weight:700; color:var(--text-white); margin:0 4px; min-width:140px; text-align:center;">
            <?= htmlspecialchars($monthNames[$month] ?? '') ?> <?= $year ?>
        </h3>
        <a href="/taskmanager/calendar?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $activeProjectId ? '&project_id='.$activeProjectId : '' ?>" class="cu-btn-sm" style="text-decoration:none;">
            <?= __('tasks.cal_btn_next', 'Sonraki ▶') ?>
        </a>
    </div>
</div>

<div class="cu-content-scroll" style="display:flex; flex-direction:column;">
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
            $todayStr = date('Y-m-d');
            for ($d = 1; $d <= $daysInMonth; $d++): 
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = ($dateStr === $todayStr);
                $dayTasks = $tasksByDate[$dateStr] ?? [];
            ?>
                <div style="background:var(--bg-card); padding:8px; display:flex; flex-direction:column; border-bottom:1px solid var(--border-subtle); border-right:1px solid var(--border-subtle); <?= $isToday ? 'outline:2px solid var(--brand-primary); z-index:1;' : '' ?>" title="<?= htmlspecialchars(Helper::format_date_localized($dateStr)) ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:12px; font-weight:<?= $isToday ? '800' : '600' ?>; color:<?= $isToday ? 'var(--brand-primary)' : 'var(--text-secondary)' ?>;">
                            <?= $d ?>
                        </span>
                        <?php if ($isToday): ?>
                            <span style="font-size:9px; font-weight:700; text-transform:uppercase; background:var(--brand-primary); color:#fff; padding:1px 4px; border-radius:3px;"><?= __('tasks.cal_today_badge', 'Bugün') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Görev Hapları -->
                    <div style="display:flex; flex-direction:column; gap:4px; overflow-y:auto; max-height:85px;">
                        <?php foreach ($dayTasks as $t): 
                            $statusColors = [
                                'assigned' => '#4f8ff7',
                                'in_progress' => '#9b5de5',
                                'modification_requested' => '#e67e22',
                                'submitted' => '#e056fd',
                                'completed' => '#00b894'
                            ];
                            $cColor = $statusColors[$t->status] ?? '#7B68EE';
                        ?>
                            <div onclick="openTaskDrawer(<?= $t->id ?>)" style="background:var(--bg-app); border:1px solid var(--border-subtle); border-left:3px solid <?= $cColor ?>; border-radius:4px; padding:3px 6px; font-size:11px; cursor:pointer; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; transition:var(--transition);" title="<?= htmlspecialchars($t->title) ?> (<?= htmlspecialchars($t->task_code) ?>)">
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
</div>

<?php include __DIR__ . '/../../Components/footer.php'; ?>
