<?php
// Modules/SmokeTest/Views/report.php
?>
<div class="cu-smoketest-report">
    <h3><?= htmlspecialchars(__('SmokeTest::starting_suite')) ?></h3>
    <table class="cu-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars(__('SmokeTest::th_test')) ?></th>
                <th><?= htmlspecialchars(__('SmokeTest::th_status')) ?></th>
                <th><?= htmlspecialchars(__('SmokeTest::th_details')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($results ?? []) as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
                <td><span class="cu-smoke-badge <?= !empty($r['passed']) ? 'cu-smoke-pass' : 'cu-smoke-fail' ?>"><?= !empty($r['passed']) ? 'PASS' : 'FAIL' ?></span></td>
                <td><?= htmlspecialchars($r['details'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
