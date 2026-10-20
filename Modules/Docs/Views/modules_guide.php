<?php
// TaskManager HMVC Modüller & Mimari Kullanım Rehberi (Accordion View)
$docController = new \Modules\Docs\Controllers\DocController();
$guideData = $docController->modulesGuide();
$modules = $guideData['modules'];
$metadataMap = $guideData['metadata'];

$activeCount = count(array_filter($modules, fn($m) => !empty($m['is_active'])));
$totalCount = count($modules);

$system->title = __('Docs::modules_guide_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';
?>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap">
        <span class="cu-view-icon-lg">🧩</span>
        <span class="cu-view-title-main"><?= __('Docs::modules_guide_title') ?></span>
    </div>

    <div class="cu-filter-bar">
        <a href="<?= baseUrl('docs') ?>" class="cu-btn-sm cu-btn-docs-back">
            <span>📄</span> <?= __('Docs::view_docs') ?>
        </a>
    </div>
</div>

<div class="cu-modules-guide-container">
    
    <!-- BAŞLIK & İSTATİSTİK KARTI -->
    <div class="cu-modules-header-card">
        <div class="cu-modules-header-left">
            <h2><span>🧩</span> <?= __('Docs::modules_guide_title') ?></h2>
            <p><?= __('Docs::modules_guide_desc') ?></p>
        </div>
        <div class="cu-modules-stats">
            <div class="cu-modules-stat-badge">
                <strong><?= $totalCount ?></strong>
                <span><?= __('Docs::modules_all_count') ?></span>
            </div>
            <div class="cu-modules-stat-badge">
                <strong class="cu-stat-active-val"><?= $activeCount ?></strong>
                <span><?= __('Docs::modules_active_count') ?></span>
            </div>
        </div>
    </div>

    <!-- ARAMA ÇUBUĞU -->
    <div class="cu-modules-search-bar">
        <input type="text" id="moduleSearchInput" class="cu-modules-search-input" placeholder="🔍 <?= htmlspecialchars(__('Docs::modules_search_ph'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
    </div>

    <!-- AKORDEON LİSTESİ -->
    <div class="cu-modules-accordion-list" id="modulesAccordionList">
        <?php foreach ($modules as $slug => $mod): 
            $name = $mod['name'] ?? $slug;
            $desc = $mod['description'] ?? '';
            $isActive = !empty($mod['is_active']);
            $version = $mod['version'] ?? '1.0.0';
            $dirName = basename($mod['_dir'] ?? $slug);

            $meta = $metadataMap[$dirName] ?? [
                'icon' => '📦',
                'capabilities' => ['HMVC component.'],
                'how_to_use' => ['See module docs.'],
                'tech_info' => 'Configured under Modules/' . $dirName,
                'key_files' => ['module.json', 'init.php']
            ];
        ?>
            <div class="cu-accordion-item" data-module-slug="<?= htmlspecialchars(strtolower($slug)) ?>">
                <button type="button" class="cu-accordion-header">
                    <div class="cu-accordion-title-wrap">
                        <div class="cu-accordion-icon">
                            <?= $meta['icon'] ?>
                        </div>
                        <div>
                            <div class="cu-accordion-title">
                                <span><?= htmlspecialchars($name) ?></span>
                                <span class="cu-dir-badge">(<?= htmlspecialchars($dirName) ?>)</span>
                            </div>
                            <div class="cu-accordion-desc">
                                <?= htmlspecialchars($desc) ?>
                            </div>
                        </div>
                    </div>
                    <div class="cu-accordion-meta">
                        <span class="cu-status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                            <?= $isActive ? '● ' . __('Docs::modules_guide_badge_active') : '○ ' . __('Docs::modules_guide_badge_inactive') ?>
                        </span>
                        <span class="cu-ver-badge">v<?= htmlspecialchars($version) ?></span>
                        <span class="cu-accordion-toggle-icon">▼</span>
                    </div>
                </button>

                <div class="cu-accordion-body">
                    <div class="cu-guide-grid">
                        <!-- Yetenekler -->
                        <div class="cu-guide-card">
                            <h4><span>✨</span> <?= __('Docs::modules_capabilities') ?></h4>
                            <ul>
                                <?php foreach ($meta['capabilities'] as $cap): ?>
                                    <li><?= htmlspecialchars($cap) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Nasıl Kullanılır -->
                        <div class="cu-guide-card">
                            <h4><span>💡</span> <?= __('Docs::modules_how_to_use') ?></h4>
                            <ul>
                                <?php foreach ($meta['how_to_use'] as $htu): ?>
                                    <li><?= htmlspecialchars($htu) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Mimari & Dosyalar -->
                    <div class="cu-guide-card cu-guide-card-footer">
                        <h4><span>⚙️</span> <?= __('Docs::modules_tech_details') ?></h4>
                        <p class="cu-guide-tech-desc">
                            <?= htmlspecialchars($meta['tech_info']) ?>
                        </p>
                        <div class="cu-guide-files-title">
                            <?= __('Docs::modules_key_files') ?>:
                        </div>
                        <div class="cu-guide-files">
                            <?php foreach ($meta['key_files'] as $kf): ?>
                                <span class="cu-file-tag"><?= htmlspecialchars($kf) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
