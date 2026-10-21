<?php
require_once dirname(__DIR__) . '/System.php';
require_once dirname(__DIR__) . '/Language.php';
Language::init();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header("Location: " . baseUrl('login'));
    exit;
}

$pageTitle = $system->title ?: 'TaskManager';
$siteURL = siteUrl();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Language::current(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        (function() {
            var theme = localStorage.getItem('taskmanager_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
        window.BASE_URL = '<?= addslashes(baseUrl()) ?>';
        window.TASKMANAGER_CORE_PROJECT_ID = '01';
        window.CURRENT_USER_ID = <?= (int)($_SESSION['user']->id ?? 0) ?>;
        window.CURRENT_USER_ROLE = '<?= addslashes($_SESSION['user']->role ?? 'user') ?>';
        window.CURRENT_LANG = '<?= addslashes(Language::current()) ?>';
    </script>
    <!-- TaskManager Design System -->
    <link rel="stylesheet" href="<?= baseUrl('/Public/css/app.css') ?>?v=<?= time() ?>">
    <!-- Dynamic HMVC Module Stylesheets -->
    <?= \Core\AssetManager::renderStyles() ?>
</head>
<body>

<!-- TOPBAR -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- MOBİL SIDEBAR OVERLAY -->
<div id="cuSidebarOverlay" class="cu-sidebar-overlay" onclick="closeMobileSidebar()"></div>

<!-- MAIN LAYOUT -->
<div class="cu-layout">
    <!-- SIDEBAR -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- MAIN WRAPPER -->
    <main class="cu-main">