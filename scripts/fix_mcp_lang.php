<?php
$file = 'Modules/Mcp/Views/modals/mcp_marketplace.php';
$content = file_get_contents($file);

// Replace all __('app.mcp_ with __('Mcp::mcp_
$content = preg_replace("/__\(\s*'app\.mcp_([a-zA-Z0-9_]+)'\s*\)/", "__('Mcp::mcp_$1')", $content);

// Replace __('app.btn_cancel') with __('Board::btn_cancel', 'İptal')
$content = str_replace("__('app.btn_cancel')", "__('Board::btn_cancel', 'İptal')", $content);

// Replace __('app.modal_profile_save') with __('Board::btn_save', 'Kaydet')
$content = str_replace("__('app.modal_profile_save')", "__('Board::btn_save', 'Kaydet')", $content);

file_put_contents($file, $content);
echo "Successfully updated Mcp modal with Mcp:: language keys!\n";
