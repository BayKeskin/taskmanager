<?php

require_once __DIR__ . '/HostingAdapterInterface.php';

/**
 * CyberPanelAdapter - CyberPanel LiteSpeed Server API Implementation
 * 
 * Uses CyberPanel API endpoints:
 * - /api/verifyLogin
 * - /api/createDatabase
 * - /api/fetchDatabases
 */
class CyberPanelAdapter implements HostingAdapterInterface
{
    private string $host;
    private int $port;
    private string $adminUser;
    private string $adminPass;
    private string $websiteName;
    private bool $useSsl;

    public function __construct(array $config)
    {
        $this->host        = rtrim($config['host'] ?? 'localhost', '/');
        $this->port        = (int)($config['port'] ?? 8090); // Default CyberPanel port
        $this->adminUser   = trim($config['username'] ?? 'admin');
        $this->adminPass   = trim($config['password'] ?? ($config['api_token'] ?? ''));
        $this->websiteName = trim($config['website_name'] ?? ($config['domain'] ?? ''));
        $this->useSsl      = (bool)($config['use_ssl'] ?? true);
    }

    public function getType(): string
    {
        return 'cyberpanel';
    }

    public function testConnection(): array
    {
        $res = $this->callApi('/api/verifyLogin', [
            'adminUser' => $this->adminUser,
            'adminPass' => $this->adminPass
        ]);

        if (!empty($res['loginStatus']) && $res['loginStatus'] == 1) {
            return [
                'success' => true,
                'message' => 'CyberPanel connection and credentials verified.',
                'data'    => $res
            ];
        }

        return [
            'success' => false,
            'message' => 'CyberPanel login failed: ' . ($res['error_message'] ?? 'Authentication error'),
            'data'    => $res
        ];
    }

    public function provisionDatabase(string $dbName, string $dbUser, string $dbPass): array
    {
        $cleanDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        $cleanDbUser = preg_replace('/[^a-zA-Z0-9_]/', '', $dbUser);

        $payload = [
            'adminUser'   => $this->adminUser,
            'adminPass'   => $this->adminPass,
            'databaseWebsite' => $this->websiteName,
            'dbName'      => $cleanDbName,
            'dbUsername'  => $cleanDbUser,
            'dbPassword'  => $dbPass
        ];

        $res = $this->callApi('/api/createDatabase', $payload);

        if (!empty($res['createDBStatus']) && $res['createDBStatus'] == 1) {
            return [
                'success' => true,
                'message' => "Database '{$cleanDbName}' and user '{$cleanDbUser}' created on CyberPanel.",
                'data'    => [
                    'host'     => 'localhost',
                    'database' => $cleanDbName,
                    'username' => $cleanDbUser,
                    'password' => $dbPass,
                    'port'     => 3306,
                    'website'  => $this->websiteName
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create database on CyberPanel: ' . ($res['error_message'] ?? json_encode($res)),
            'data'    => $res
        ];
    }

    public function listDatabases(): array
    {
        $payload = [
            'adminUser'       => $this->adminUser,
            'adminPass'       => $this->adminPass,
            'databaseWebsite' => $this->websiteName
        ];

        $res = $this->callApi('/api/fetchDatabases', $payload);
        if (!empty($res['fetchDBStatus']) && $res['fetchDBStatus'] == 1) {
            $data = json_decode($res['data'] ?? '[]', true);
            return ['success' => true, 'databases' => $data];
        }

        return ['success' => false, 'error' => $res['error_message'] ?? 'Failed to list databases'];
    }

    private function callApi(string $endpoint, array $payload): array
    {
        $proto = $this->useSsl ? 'https://' : 'http://';
        $hostClean = preg_replace('#^https?://#i', '', $this->host);
        $url = "{$proto}{$hostClean}:{$this->port}" . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['status' => 0, 'error_message' => "cURL error: {$curlErr}"];
        }

        $decoded = @json_decode($response, true);
        return $decoded !== null ? $decoded : ['status' => 0, 'error_message' => 'Invalid JSON from CyberPanel'];
    }
}
