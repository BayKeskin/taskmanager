<?php
// Replace all __('app.modal_ with __('Projects::modal_ in Modules/Projects
$projectsDir = 'Modules/Projects';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectsDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir() || !in_array($file->getExtension(), ['php', 'js'])) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    // Replace __('app.modal_ with __('Projects::modal_
    $newContent = preg_replace("/__\(\s*['\"]app\.modal_([a-zA-Z0-9_]+)['\"]/i", "__('Projects::modal_$1'", $content);
    
    // Replace modal_cancel with Board::btn_cancel
    $newContent = str_replace("__('Projects::modal_cancel')", "__('Board::btn_cancel', 'İptal')", $newContent);

    // Replace deploy and mcp buttons with their respective modules
    $newContent = str_replace("__('app.deploy_btn_wizard')", "__('LiveDeploy::deploy_btn_wizard', '🚀 Canlı Dağıtım')", $newContent);
    $newContent = str_replace("__('app.deploy_wizard_desc')", "__('LiveDeploy::deploy_wizard_desc')", $newContent);
    $newContent = str_replace("__('app.mcp_btn_open')", "__('Mcp::mcp_btn_open', '🧩 MCP Araçları')", $newContent);
    $newContent = str_replace("__('app.mcp_marketplace_desc')", "__('Mcp::mcp_marketplace_desc')", $newContent);

    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        $count++;
        echo "Updated $path\n";
    }
}
echo "Total updated files: $count\n";
