<?php

require_once __DIR__ . '/HostingAdapterInterface.php';

/**
 * PleskAdapter - Plesk REST API v2 Implementation
 * 
 * Uses Plesk REST API:
 * - GET  /api/v2/databases
 * - POST /api/v2/databases
 * - POST /api/v2/database-users
 */
class PleskAdapter implements HostingAdapterInterface
{
    private string $host;
    private int $port;
    private string $apiKey; // or username & password
    private string $username;
    private string $password;
    private bool $useSsl;

    public function __construct(array $config)
    {
        $this->host     = rtrim($config['host'] ?? 'localhost', '/');
        $this->port     = (int)($config['port'] ?? 8443);
        $this->apiKey   = trim($config['api_key'] ?? ($config['api_token'] ?? ''));
        $this->username = trim($config['username'] ?? 'admin');
        $this->password = trim($config['password'] ?? '');
        $this->useSsl   = (bool)($config['use_ssl'] ?? true);
    }

    public function getType(): string
    {
        return 'plesk';
    }

    public function testConnection(): array
    {
        $res = $this->callApi('GET', '/api/v2/server');
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            return [
                'success' => true,
                'message' => 'Plesk REST API connection successful.',
                'data'    => $res['data'] ?? []
            ];
        }

        return [
            'success' => false,
            'message' => 'Plesk connection failed (HTTP ' . $res['http_code'] . '): ' . ($res['data']['message'] ?? 'Error'),
            'data'    => $res
        ];
    }

    public function provisionDatabase(string $dbName, string $dbUser, string $dbPass): array
    {
        // 1. Create Database
        // In Plesk, databases belong to a subscription or webspace. If subscription_id or domain is given:
        $dbPayload = [
            'name'   => $dbName,
            'type'   => 'mysql'
        ];

        $dbRes = $this->callApi('POST', '/api/v2/databases', $dbPayload);
        if ($dbRes['http_code'] < 200 || $dbRes['http_code'] >= 300) {
            return [
                'success' => false,
                'message' => 'Failed to create database on Plesk: ' . ($dbRes['data']['message'] ?? json_encode($dbRes['data'])),
                'step'    => 'create_database'
            ];
        }

        $createdDbId = $dbRes['data']['id'] ?? null;

        // 2. Create Database User
        $userPayload = [
            'name'        => $dbUser,
            'password'    => $dbPass,
            'database_id' => $createdDbId
        ];

        $userRes = $this->callApi('POST', '/api/v2/database-users', $userPayload);
        if ($userRes['http_code'] < 200 || $userRes['http_code'] >= 300) {
            return [
                'success' => false,
                'message' => 'Failed to create database user on Plesk: ' . ($userRes['data']['message'] ?? json_encode($userRes['data'])),
                'step'    => 'create_user'
            ];
        }

        return [
            'success' => true,
            'message' => "Database '{$dbName}' and user '{$dbUser}' successfully provisioned on Plesk.",
            'data'    => [
                'host'        => 'localhost',
                'database'    => $dbName,
                'username'    => $dbUser,
                'password'    => $dbPass,
                'port'        => 3306,
                'database_id' => $createdDbId
            ]
        ];
    }

    public function listDatabases(): array
    {
        $res = $this->callApi('GET', '/api/v2/databases');
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            return ['success' => true, 'databases' => $res['data'] ?? []];
        }
        return ['success' => false, 'error' => $res['data']['message'] ?? 'Failed to list databases'];
    }

    private function callApi(string $method, string $endpoint, array $payload = []): array
    {
        $proto = $this->useSsl ? 'https://' : 'http://';
        $hostClean = preg_replace('#^https?://#i', '', $this->host);
        $url = "{$proto}{$hostClean}:{$this->port}" . ltrim($endpoint, '/');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if (!empty($this->apiKey)) {
            $headers[] = "X-API-Key: {$this->apiKey}";
        } else {
            $headers[] = "Authorization: Basic " . base64_encode("{$this->username}:{$this->password}");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['http_code' => 0, 'data' => ['message' => "cURL error: {$curlErr}"]];
        }

        $decoded = @json_decode($response, true);
        return [
            'http_code' => $httpCode,
            'data'      => $decoded !== null ? $decoded : ['raw' => $response]
        ];
    }
}
