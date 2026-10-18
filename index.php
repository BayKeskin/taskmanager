<?php
/**
 * TaskManager Enterprise Application Entry Point
 * Yönlendirme ve varsayılan görünüm başlatıcı
 */
require_once __DIR__ . '/System/System.php';

// Oturum kontrolü ve yönlendirme
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Giriş yapılmamışsa login ekranına, yapılmışsa doğrudan Kanban panosuna yönlendir
if (empty($_SESSION['user'])) {
    require_once __DIR__ . '/Modules/Team/Views/login.php';
} else {
    require_once __DIR__ . '/Modules/Board/Views/board.php';
}
