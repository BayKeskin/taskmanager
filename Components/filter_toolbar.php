<?php
// TaskManager Yan Yana Filtre & Sıralama Araç Çubuğu (Horizontal Filter Toolbar)
if (!isset($allUsers)) {
    $userModel = new \Modules\Team\Models\UserModel();
    $allUsers = $userModel->getUsersForFilter();
}
$currentUserId = $currentUser->id ?? 1;
$totalTasksCount = isset($allTasks) ? count($allTasks) : ($totalCount ?? 0);
?>
<div class="cu-filter-toolbar">
    <!-- Canlı Arama Input -->
    <div class="cu-filter-search">
        <span class="cu-search-icon">🔍</span>
        <input type="text" id="cuListSearch" oninput="applyFilters(<?= $currentUserId ?>)" placeholder="<?= htmlspecialchars(__('tasks.search_placeholder', 'Görev veya etiket ara...')) ?>" class="cu-form-control">
    </div>

    <!-- Sadece Bana Atananlar Toggle Butonu -->
    <button type="button" id="btnFilterMyTasks" onclick="toggleMyTasksFilter(<?= $currentUserId ?>)" class="cu-btn-sm cu-btn-filter" style="background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-primary);">
        <span>👤</span> <span><?= __('tasks.filter_my_tasks', 'Bana Atananlar') ?></span>
    </button>

    <!-- Öncelik Filtresi -->
    <select id="cuFilterPriority" onchange="applyFilters(<?= $currentUserId ?>)" class="cu-form-control">
        <option value=""><?= __('tasks.filter_all_priorities', '🚩 Tüm Öncelikler') ?></option>
        <option value="urgent"><?= __('tasks.priority_urgent', '🔴 Acil (Urgent)') ?></option>
        <option value="high"><?= __('tasks.priority_high', '🟡 Yüksek (High)') ?></option>
        <option value="normal"><?= __('tasks.priority_normal', '🔵 Normal') ?></option>
        <option value="low"><?= __('tasks.priority_low', '⚪ Düşük (Low)') ?></option>
    </select>

    <!-- Atanan Kişi Filtresi -->
    <select id="cuFilterAssignee" onchange="applyFilters(<?= $currentUserId ?>)" class="cu-form-control">
        <option value=""><?= __('tasks.filter_all_assignees', '👥 Tüm Kişiler') ?></option>
        <?php foreach ($allUsers as $u): ?>
            <option value="<?= $u->id ?>"><?= htmlspecialchars($u->name) ?></option>
        <?php endforeach; ?>
    </select>

    <!-- Sıralama -->
    <select id="cuSortBy" onchange="applySorting()" class="cu-form-control">
        <option value="default"><?= __('tasks.sort_default', '🔃 Sıralama: Varsayılan') ?></option>
        <option value="deadline_asc"><?= __('tasks.sort_deadline_asc', '📅 Bitiş Tarihi (En Yakın)') ?></option>
        <option value="priority_desc"><?= __('tasks.sort_priority_desc', '🚩 Öncelik (En Yüksek)') ?></option>
        <option value="title_asc"><?= __('tasks.sort_title_asc', '🔤 Başlık (A-Z)') ?></option>
    </select>

    <!-- Temizle -->
    <button type="button" onclick="resetFilters(<?= $currentUserId ?>)" class="cu-btn-sm cu-btn-filter" style="background:transparent; border:1px dashed var(--border-subtle); color:var(--text-muted);">
        <?= __('tasks.btn_reset_filters', '✕ Sıfırla') ?>
    </button>

    <!-- Sayı Göstergesi -->
    <div class="cu-filter-count">
        <?= __('tasks.total_tasks_count', ['count' => $totalTasksCount], 'Toplam <strong id="cuVisibleTaskCount" style="color:var(--brand-primary);">' . $totalTasksCount . '</strong> görev') ?>
    </div>
</div>
