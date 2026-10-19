<?php
// Modules/Docs/Views/viewer.php
?>
<div class="cu-doc-content">
    <?php if (!empty($firstDoc)): ?>
        <h2><?= htmlspecialchars($firstDoc->title ?? '') ?></h2>
        <div class="cu-doc-body cu-doc-body-view">
            <?= $firstDoc->content ?? '' ?>
        </div>
    <?php else: ?>
        <p><?= htmlspecialchars(__('Docs::select_doc')) ?></p>
    <?php endif; ?>
</div>
