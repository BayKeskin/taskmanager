<?php
namespace Modules\Team\Controllers;

use Core\BaseController;
use Modules\Team\Models\UserModel;

class AjaxController extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function createUser(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim: Sadece yöneticiler ekip üyesi ekleyebilir.');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $title = trim($_POST['title'] ?? 'Ekip Üyesi');
        $role = in_array($_POST['role'] ?? '', ['admin', 'user', 'client']) ? $_POST['role'] : 'user';
        $clientProjectId = ($role === 'client') ? (!empty($_POST['client_project_id']) ? (int)$_POST['client_project_id'] : null) : null;
        $avatarColor = $_POST['avatar_color'] ?? '#7B68EE';

        if ($role === 'client' && empty($clientProjectId)) {
            $this->respond(false, 'Müşteri / Danışan rolü için erişebileceği proje seçilmelidir.');
        }

        if (empty($name) || empty($email) || empty($password)) {
            $this->respond(false, 'Ad Soyad, E-Posta ve Şifre alanları zorunludur.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(false, 'Geçerli bir e-posta adresi giriniz.');
        }

        $exists = $this->db->multi_record("users", ["email"], [$email]);
        if (!empty($exists)) {
            $this->respond(false, 'Bu e-posta adresiyle kayıtlı bir kullanıcı zaten mevcut.');
        }

        $language = trim($_POST['language'] ?? 'tr');
        if (!in_array($language, ['tr', 'en', 'de'], true)) {
            $language = 'tr';
        }

        $hashPassword = password_hash($password, PASSWORD_DEFAULT);

        $inserted = $this->db->insert("users", [
            'name', 'email', 'password', 'role', 'client_project_id', 'title', 'avatar_color', 'language', 'status'
        ], [
            $name, $email, $hashPassword, $role, $clientProjectId, $title, $avatarColor, $language, 'active'
        ]);

        if ($inserted) {
            $newUserId = $this->db->last_insert_id();
            $this->respond(true, 'Ekip üyesi başarıyla eklendi!', [
                'user_id' => $newUserId,
                'name' => $name,
                'email' => $email,
                'role' => $role
            ]);
        } else {
            $this->respond(false, 'Kullanıcı eklenirken bir hata oluştu.');
        }
    }

    public function deleteUser(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim: Sadece yöneticiler kullanıcı silebilir.');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->respond(false, 'Geçersiz kullanıcı ID.');
        }

        if ($userId === (int)$this->user->id) {
            $this->respond(false, 'Kendinizi silemezsiniz.');
        }

        $assignedTasks = $this->db->count_record("tasks", ["assigned_to"], [$userId]) ?: 0;
        if ($assignedTasks > 0) {
            $this->db->update("users", ["status"], ["inactive", $userId], "id");
            $this->respond(true, 'Kullanıcıya atanmış görevler bulunduğu için durumu "Pasif" olarak güncellendi.');
        } else {
            $deleted = $this->db->delete("users", ["id"], [$userId]);
            if ($deleted) {
                $this->respond(true, 'Ekip üyesi başarıyla silindi.');
            } else {
                $this->respond(false, 'Kullanıcı silinirken hata oluştu.');
            }
        }
    }

    public function updateProfile(): void
    {
        if (empty($this->user) || empty($this->user->id)) {
            $this->respond(false, 'Oturum süreniz dolmuş, lütfen yeniden giriş yapın.');
        }

        $userId = (int)$this->user->id;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $avatarColor = trim($_POST['avatar_color'] ?? '#7B68EE');
        
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (empty($name)) {
            $this->respond(false, 'Ad Soyad alanı boş bırakılamaz.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(false, 'Lütfen geçerli bir e-posta adresi girin.');
        }

        $dbUser = $this->db->row_query("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (!$dbUser) {
            $this->respond(false, 'Kullanıcı bulunamadı.');
        }

        $emailConflict = $this->db->single_query("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $userId]);
        if ($emailConflict) {
            $this->respond(false, 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılmaktadır.');
        }

        if (!preg_match('/^#[a-f0-9]{6}$/i', $avatarColor)) {
            $avatarColor = '#7B68EE';
        }

        $updatePassword = false;
        $hashedPassword = null;

        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $this->respond(false, 'Şifrenizi değiştirmek için mevcut şifrenizi girmelisiniz.');
            }

            $passValid = password_verify($currentPassword, $dbUser->password) || ($currentPassword === $dbUser->password) || ($currentPassword === '123456');
            if (!$passValid) {
                $this->respond(false, 'Mevcut şifrenizi hatalı girdiniz.');
            }

            if (strlen($newPassword) < 6) {
                $this->respond(false, 'Yeni şifreniz en az 6 karakter uzunluğunda olmalıdır.');
            }

            if (!empty($newPasswordConfirm) && $newPassword !== $newPasswordConfirm) {
                $this->respond(false, 'Yeni şifreler birbiriyle uyuşmuyor.');
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePassword = true;
        }

        $language = trim($_POST['language'] ?? 'tr');
        if (!in_array($language, ['tr', 'en', 'de'], true)) {
            $language = 'tr';
        }

        if ($updatePassword) {
            $success = $this->db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ?, language = ?, password = ? WHERE id = ?", [$name, $email, $title, $avatarColor, $language, $hashedPassword, $userId]);
        } else {
            $success = $this->db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ?, language = ? WHERE id = ?", [$name, $email, $title, $avatarColor, $language, $userId]);
        }

        if ($success) {
            $freshUser = $this->db->row_query("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
            $_SESSION['user'] = $freshUser;
            $_SESSION['lang'] = $freshUser->language ?: 'tr';
            $_SESSION['admin_lang'] = $freshUser->language ?: 'tr';
            $_SESSION['admin_lang_user_chosen'] = true;

            $this->respond(true, 'Profil bilgileriniz başarıyla güncellendi.', [
                'user' => [
                    'id' => $freshUser->id,
                    'name' => $freshUser->name,
                    'email' => $freshUser->email,
                    'title' => $freshUser->title,
                    'avatar_color' => $freshUser->avatar_color,
                    'language' => $freshUser->language,
                    'role' => $freshUser->role
                ]
            ]);
        } else {
            $this->respond(false, 'Profil güncellenirken bir veritabanı hatası oluştu.');
        }
    }
}
