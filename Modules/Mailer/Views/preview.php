<?php
// Modules/Mailer/Views/preview.php
?>
<div class="cu-mailer-preview">
    <h3><?= htmlspecialchars($subject ?? __('Mailer::preview_title')) ?></h3>
    <hr class="cu-mailer-hr">
    <div>
        <?= $body ?? '' ?>
    </div>
</div>
