<?php

class Auth
{
    private Database $db;
    private ?object $currentUser = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Oturum açmış kullanıcının ID'sini döner
     */
    public function id(): ?int
    {
        return isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;
    }

    /**
     * Kullanıcı oturum açmış mı kontrol eder ve zaman aşımını doğrular
     */
    public function check(): bool
    {
        if (empty($_SESSION['id'])) {
            return false;
        }

        // Oturum Zaman Aşımı Kontrolü (Settings::session_timeout_minutes)
        if (isset($_SESSION['last_activity'])) {
            $timeoutMinutes = 120;
            try {
                $s = $this->db->connect->query("SELECT session_timeout_minutes FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if (!empty($s['session_timeout_minutes']) && (int)$s['session_timeout_minutes'] > 0) {
                    $timeoutMinutes = (int)$s['session_timeout_minutes'];
                }
            } catch (\Throwable $e) {}

            if ((time() - (int)$_SESSION['last_activity']) > ($timeoutMinutes * 60)) {
                $this->logout();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Giriş yapmış kullanıcının tüm verilerini döner
     */
    public function user(): ?object
    {
        if (!$this->check()) {
            return null;
        }

        if ($this->currentUser !== null) {
            return $this->currentUser;
        }

        $result = $this->db->multi_record('users', ['id'], [$this->id()]);
        $this->currentUser = $result ? $result[0] : null;
        return $this->currentUser;
    }

    /**
     * get_user_detail alias
     */
    public function get_user_detail(): ?object
    {
        return $this->user();
    }

    /**
     * Belirtilen ID ile oturum açar
     */
    public function login(int $userId): void
    {
        $_SESSION['id'] = $userId;
        $this->currentUser = null;
        $user = $this->user();
        if ($user) {
            $_SESSION['user'] = $user;
            $lang = !empty($user->language) ? $user->language : 'tr';
            $_SESSION['lang'] = $lang;
            $_SESSION['admin_lang'] = $lang;
            $_SESSION['admin_lang_user_chosen'] = true;
        }
    }

    /**
     * Oturumu sonlandırır
     */
    public function logout(): void
    {
        unset($_SESSION['id']);
        $this->currentUser = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
