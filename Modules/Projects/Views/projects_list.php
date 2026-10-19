<?php
// Modules/Projects/Views/projects_list.php
?>
<div class="cu-projects-container">
    <?php foreach (($projects ?? []) as $p): 
        $pObj = (object)$p;
    ?>
        <div class="cu-project-card">
            <div class="cu-proj-card-header">
                <span class="cu-proj-card-dot"></span>
                <h4 class="cu-proj-card-title"><?= htmlspecialchars($pObj->title ?? '') ?></h4>
                <span class="cu-proj-card-code">
                    <?= htmlspecialchars($pObj->project_code ?? '') ?>
                </span>
            </div>
            <p class="cu-proj-card-desc">
                <?= htmlspecialchars($pObj->description ?? '') ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>
