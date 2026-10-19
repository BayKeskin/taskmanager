<?php
$authController = new \Modules\Team\Controllers\AuthController();
$error = '';
$success = '';

if (isset($_GET['logout'])) {
    $success = __('Team::auth_logout_success');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $loginResult = $authController->attemptLogin($email, $password);
    if ($loginResult['success']) {
        header("Location: " . baseUrl());
        exit;
    } else {
        $error = $loginResult['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Language::current(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('Team::auth_login_title') ?> - TaskManager</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('/Modules/Team/Assets/css/login.css') ?>">
</head>
<body>

<div class="cu-login-card">
    <div class="cu-login-logo">
        <svg viewBox="0 0 24 24">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
        <span>TaskManager</span>
    </div>

    <div class="cu-login-title">
        <h1><?= __('Team::auth_login_title') ?></h1>
        <p><?= __('Team::auth_login_sub') ?></p>
    </div>

    <?php if ($error): ?>
        <div class="cu-alert cu-alert-error">
            <span>⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="cu-alert cu-alert-success">
            <span>✅</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= baseUrl('login') ?>" autocomplete="on">
        <div class="cu-input-group">
            <label for="inputEmail"><?= __('Team::auth_email') ?></label>
            <div class="cu-input-wrapper">
                <span class="icon">✉️</span>
                <input type="email" id="inputEmail" name="email" placeholder="<?= htmlspecialchars(__('Team::auth_email_ph'), ENT_QUOTES, 'UTF-8') ?>" value="" required autofocus>
            </div>
        </div>

        <div class="cu-input-group">
            <label for="inputPassword"><?= __('Team::auth_password') ?></label>
            <div class="cu-input-wrapper">
                <span class="icon">🔒</span>
                <input type="password" id="inputPassword" name="password" placeholder="••••••••" value="" required>
            </div>
        </div>

        <button type="submit" class="cu-btn-submit">
            <?= __('Team::auth_btn_login') ?> ➔
        </button>
    </form>
</div>

</body>
</html>
