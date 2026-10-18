<?php
// Modules/Projects/Views/projects_list.php
?>
<div class="cu-projects-container">
    <?php foreach (($projects ?? []) as $p): 
        $pObj = (object)$p;
    ?>
        <div class="cu-project-card">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="width:12px; height:12px; border-radius:50%; background:<?= htmlspecialchars($pObj->color ?: '#7B68EE') ?>;"></span>
                <h4 style="margin:0; font-size:15px;"><?= htmlspecialchars($pObj->title ?? '') ?></h4>
                <span style="font-size:10px; font-family:monospace; background:rgba(255,255,255,0.06); padding:2px 6px; border-radius:4px;">
                    <?= htmlspecialchars($pObj->project_code ?? '') ?>
                </span>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin:0;">
                <?= htmlspecialchars($pObj->description ?? '') ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>
