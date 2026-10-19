<?php
// Modules/Mcp/Views/catalog.php
?>
<div class="cu-mcp-catalog">
    <h3><?= htmlspecialchars(__('Mcp::mcp_title')) ?></h3>
    <div class="cu-mcp-catalog-grid">
        <?php foreach (($mcps ?? []) as $m): ?>
            <div class="cu-mcp-card">
                <h4 class="cu-mcp-card-title"><?= htmlspecialchars($m->name ?? '') ?></h4>
                <p class="cu-mcp-card-desc"><?= htmlspecialchars($m->description ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
