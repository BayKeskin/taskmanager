<?php
namespace Modules\RestApi\Services;

use Modules\RestApi\Models\RestApiModel;
use System\Language;

class RestApiService
{
    protected ?RestApiModel $model = null;

    public function __construct(?RestApiModel $model = null)
    {
        $this->model = $model ?? new RestApiModel();
    }

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

        $user = $this->model->getUserByToken($token);
        if (!empty($user)) {
            return (object)$user;
        }

        // Global admin fallback token kontrolü
        if ($token === 'tm_admin_talha_keskin_token_2026') {
            $adminUser = $this->model->getAdminUser();
            if (!empty($adminUser)) {
                return (object)$adminUser;
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
            $model = new RestApiModel();
            $sUrl = $model->getSiteUrlSetting();
            if (!empty($sUrl)) {
                $baseUrl = rtrim(trim((string)$sUrl), '/');
            } elseif (!empty($_SERVER['HTTP_HOST'])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . '/taskmanager';
            }

            $adminUser = $model->getFirstActiveAdmin();

            if ($adminUser) {
                if (!empty($adminUser->api_token)) {
                    $apiToken = trim((string)$adminUser->api_token);
                } else {
                    $apiToken = 'tm_live_' . bin2hex(random_bytes(16));
                    $model->updateUserApiToken((int)$adminUser->id, $apiToken);
                }
            }
        } catch (\Throwable $e) {}

        return [
            'base_url'  => $baseUrl,
            'api_token' => $apiToken
        ];
    }
}
