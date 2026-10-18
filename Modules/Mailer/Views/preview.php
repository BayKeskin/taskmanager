<?php
// Modules/Mailer/Views/preview.php
?>
<div class="cu-mailer-preview">
    <h3><?= htmlspecialchars($subject ?? 'E-Posta Önizleme') ?></h3>
    <hr style="border:0; border-top:1px solid #edf2f7; margin:16px 0;">
    <div>
        <?= $body ?? '' ?>
    </div>
</div>
