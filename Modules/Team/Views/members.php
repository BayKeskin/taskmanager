<?php
// Modules/Team/Views/members.php
?>
<div class="cu-team-grid">
    <?php foreach (($usersList ?? []) as $u): 
        $uObj = (object)$u;
    ?>
        <div class="cu-member-card" data-role="<?= htmlspecialchars($uObj->role ?? 'user') ?>">
            <div class="cu-member-card-avatar cu-avatar-<?= $uObj->id ?>">
                <?= strtoupper(substr($uObj->name ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h4 class="cu-member-card-name"><?= htmlspecialchars($uObj->name ?? '') ?></h4>
                <p class="cu-member-card-title"><?= htmlspecialchars($uObj->title ?: ucfirst($uObj->role ?? 'user')) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
