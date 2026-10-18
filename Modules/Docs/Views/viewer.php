<?php
// Modules/Docs/Views/viewer.php
?>
<div class="cu-doc-content">
    <?php if (!empty($firstDoc)): ?>
        <h2><?= htmlspecialchars($firstDoc->title ?? '') ?></h2>
        <div class="cu-doc-body" style="line-height:1.6; margin-top:16px;">
            <?= $firstDoc->content ?? '' ?>
        </div>
    <?php else: ?>
        <p><?= htmlspecialchars(__('Docs::select_doc', 'Bir döküman seçin.')) ?></p>
    <?php endif; ?>
</div>
