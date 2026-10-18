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

    /**
     * Dinamik API base URL ve token bilgilerini döndürür
     */
    public static function getDynamicApiConfig(): array
    {
        $baseUrl = 'http://localhost/taskmanager';
        $apiToken = '';

        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db || empty($db->connect)) {
                $db = new \Core\DataLayer();
            }

            $sUrl = $db->single_query("SELECT siteurl FROM settings WHERE id = 1");
            if (!empty($sUrl)) {
                $baseUrl = rtrim(trim((string)$sUrl), '/');
            } elseif (!empty($_SERVER['HTTP_HOST'])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . '/taskmanager';
            }

            $adminUser = $db->row_query("
                SELECT id, api_token 
                FROM users 
                WHERE role = 'admin' AND status = 'active' 
                ORDER BY id ASC LIMIT 1
            ");

            if ($adminUser) {
                if (!empty($adminUser->api_token)) {
                    $apiToken = trim((string)$adminUser->api_token);
                } else {
                    $apiToken = 'tm_live_' . bin2hex(random_bytes(16));
                    $db->exec_query("UPDATE users SET api_token = ? WHERE id = ?", [$apiToken, $adminUser->id]);
                }
            }
        } catch (\Throwable $e) {}

        return [
            'base_url'  => $baseUrl,
            'api_token' => $apiToken
        ];
    }
}
