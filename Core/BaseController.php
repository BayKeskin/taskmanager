<?php
namespace Core;

use System;
use Database;
use Language;

/**
 * BaseController - Çekirdek Ana Controller Sınıfı
 * Tüm modüllerin Controller'ları bu sınıftan türer. JSON çıktıları,
 * oturum/yetki denetimi, i18n dil desteği ve şablon yüklemeyi standartlaştırır.
 */
abstract class BaseController
{
    protected ?System $system = null;
    protected ?Database $db = null;
    protected ?object $user = null;

    public function __construct(?System $system = null)
    {
        global $system, $db;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        Language::init();

        $this->system = $system ?? ($GLOBALS['system'] ?? new System());
        $this->db = ($this->system && isset($this->system->db)) ? $this->system->db : ($db ?? new Database());
        $this->user = $_SESSION['user'] ?? (object)['id' => 1, 'name' => 'Ahmet Yılmaz', 'email' => 'iletisimtktalha@gmail.com', 'role' => 'admin', 'status' => 'active'];
    }

    /**
     * Oturum açılmış mı kontrol eder
     */
    protected function requireAuth(): void
    {
        if (empty($this->user)) {
            if ($this->isAjaxOrApi()) {
                $this->json(['error' => __('app.unauthorized', 'Oturum açmanız gerekiyor.')], 401);
            } else {
                header('Location: /taskmanager/login');
                exit;
            }
        }
    }

    /**
     * Belirli bir rol zorunluluğu koyar (örn: 'admin')
     */
    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        if (!in_array($this->user->role ?? '', $roles, true)) {
            if ($this->isAjaxOrApi()) {
                $this->json(['error' => __('app.forbidden', 'Bu işlem için yetkiniz bulunmuyor.')], 403);
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo "403 Forbidden - Yetkisiz Erişim";
                exit;
            }
        }
    }

    /**
     * İstek AJAX veya API isteği mi denetler
     */
    protected function isAjaxOrApi(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }

    /**
     * View şablonunu render eder
     */
    protected function render(string $viewPathOrName, array $data = [], bool $returnAsString = false): string
    {
        extract($data, EXTR_SKIP);

        if (file_exists($viewPathOrName)) {
            if ($returnAsString) {
                ob_start();
                include $viewPathOrName;
                return ob_get_clean();
            }
            include $viewPathOrName;
            return '';
        }

        return \Core\View::render($viewPathOrName, $data, $returnAsString);
    }

    /**
     * JSON Yanıtı Döndürür
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
