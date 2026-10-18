<?php
// Replace all __('app.team_ with __('Team::team_ and __('app.modal_profile_ with __('Team::modal_profile_ in Modules/Team
$teamDir = 'Modules/Team';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($teamDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir() || !in_array($file->getExtension(), ['php', 'js'])) continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    
    // Replace __('app.team_ with __('Team::team_
    $newContent = preg_replace("/__\(\s*['\"]app\.team_([a-zA-Z0-9_]+)['\"]/i", "__('Team::team_$1'", $content);
    // Replace __('app.modal_profile_ with __('Team::modal_profile_
    $newContent = preg_replace("/__\(\s*['\"]app\.modal_profile_([a-zA-Z0-9_]+)['\"]/i", "__('Team::modal_profile_$1'", $newContent);
    // Replace __('app.auth_ with __('Team::auth_
    $newContent = preg_replace("/__\(\s*['\"]app\.auth_([a-zA-Z0-9_]+)['\"]/i", "__('Team::auth_$1'", $newContent);
    // Replace layout_role_admin and layout_role_member
    $newContent = str_replace("__('app.layout_role_admin'", "__('Team::role_admin'", $newContent);
    $newContent = str_replace("__('app.layout_role_member'", "__('Team::role_user'", $newContent);
    $newContent = str_replace("__('app.modal_cancel'", "__('Board::btn_cancel', 'İptal'", $newContent);

    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        $count++;
        echo "Updated $path\n";
    }
}
echo "Total updated files: $count\n";
