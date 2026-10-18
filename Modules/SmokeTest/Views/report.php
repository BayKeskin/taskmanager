<?php
// Modules/SmokeTest/Views/report.php
?>
<div class="cu-smoketest-report">
    <h3><?= htmlspecialchars(__('SmokeTest::starting_suite', 'Smoke Test Raporu')) ?></h3>
    <table class="cu-table">
        <thead>
            <tr>
                <th>Test</th>
                <th>Durum</th>
                <th>Detay</th>
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
