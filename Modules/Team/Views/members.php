<?php
// Modules/Team/Views/members.php
?>
<div class="cu-team-grid">
    <?php foreach (($usersList ?? []) as $u): 
        $uObj = (object)$u;
    ?>
        <div class="cu-member-card" data-role="<?= htmlspecialchars($uObj->role ?? 'user') ?>">
            <div style="width:40px; height:40px; border-radius:50%; background:<?= htmlspecialchars($uObj->avatar_color ?: '#7B68EE') ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">
                <?= strtoupper(substr($uObj->name ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h4 style="margin:0; font-size:14px;"><?= htmlspecialchars($uObj->name ?? '') ?></h4>
                <p style="margin:2px 0 0 0; font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($uObj->title ?: ucfirst($uObj->role ?? 'user')) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
