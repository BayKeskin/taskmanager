<?php
$raw = shell_exec('php scripts/deep_hmvc_audit.php');
$data = json_decode($raw, true);
if (!$data) {
    echo "Failed to decode JSON\n";
    exit(1);
}

$summary = [];
foreach ($data as $mod => $info) {
    $issues = [];
    if (!$info['has_model']) $issues[] = "NO_MODEL";
    if (!empty($info['direct_db_calls'])) $issues[] = "DB: " . count($info['direct_db_calls']);
    if (!empty($info['inline_style_script_views'])) $issues[] = "INLINE_ASSETS: " . count($info['inline_style_script_views']);
    if (!empty($info['general_lang_calls'])) $issues[] = "LANG_APP: " . count($info['general_lang_calls']);
    if (!empty($issues)) {
        $summary[$mod] = $issues;
    }
}
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\nTotal modules with issues: " . count($summary) . "\n\n";

echo "=== DIRECT DB CALLS ===\n";
foreach ($data as $mod => $info) {
    if (!empty($info['direct_db_calls'])) {
        echo "[$mod]:\n";
        print_r($info['direct_db_calls']);
    }
}

echo "=== INLINE ASSETS ===\n";
foreach ($data as $mod => $info) {
    if (!empty($info['inline_style_script_views'])) {
        echo "[$mod]:\n";
        print_r($info['inline_style_script_views']);
    }
}
