<?php
// Modules/Mcp/Views/catalog.php
?>
<div class="cu-mcp-catalog">
    <h3><?= htmlspecialchars(__('Mcp::mcp_title', 'MCP Kataloğu')) ?></h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; margin-top:16px;">
        <?php foreach (($mcps ?? []) as $m): ?>
            <div class="cu-mcp-card">
                <h4 style="margin:0;"><?= htmlspecialchars($m->name ?? '') ?></h4>
                <p style="font-size:12px; color:var(--text-muted); margin:0;"><?= htmlspecialchars($m->description ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
