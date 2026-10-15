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
    require_once __DIR__ . '/View/index/login.php';
} else {
    require_once __DIR__ . '/View/index/board.php';
}
