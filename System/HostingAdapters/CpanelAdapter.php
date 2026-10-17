<?php

require_once __DIR__ . '/HostingAdapterInterface.php';

/**
 * CpanelAdapter - cPanel UAPI Implementation
 * 
 * Uses cPanel UAPI Mysql module endpoints:
 * - /execute/Mysql/create_database
 * - /execute/Mysql/create_user
 * - /execute/Mysql/set_privileges_on_database
 */
class CpanelAdapter implements HostingAdapterInterface
{
    private string $host;
    private int $port;
    private string $username;
    private string $apiToken; // or cPanel password
    private bool $useSsl;

    public function __construct(array $config)
    {
        $this->host     = rtrim($config['host'] ?? 'localhost', '/');
        $this->port     = (int)($config['port'] ?? 2083); // 2083 default for cPanel SSL
        $this->username = trim($config['username'] ?? '');
        $this->apiToken = trim($config['api_token'] ?? ($config['password'] ?? ''));
        $this->useSsl   = (bool)($config['use_ssl'] ?? true);
    }

    public function getType(): string
    {
        return 'cpanel';
    }

    public function testConnection(): array
    {
        $res = $this->callUapi('Mysql', 'get_server_information');
        if ($res['status'] === 1) {
            return [
                'success' => true,
                'message' => 'cPanel UAPI connection successful.',
                'data'    => $res['data'] ?? []
            ];
        }

        return [
            'success' => false,
            'message' => 'cPanel UAPI connection failed: ' . ($res['errors'][0] ?? 'Unknown error'),
            'data'    => $res
        ];
    }

    public function provisionDatabase(string $dbName, string $dbUser, string $dbPass): array
    {
        // 1. Format database name (cPanel prepends username_ if not already present)
        $cleanDbName = $this->formatCpanelName($dbName);
        $cleanDbUser = $this->formatCpanelName($dbUser, 16); // cPanel user max 16 chars including prefix

        // Step 1: Create Database
        $dbRes = $this->callUapi('Mysql', 'create_database', ['name' => $cleanDbName]);
        if ($dbRes['status'] !== 1) {
            return [
                'success' => false,
                'message' => 'Failed to create database on cPanel: ' . ($dbRes['errors'][0] ?? 'Error'),
                'step'    => 'create_database',
                'details' => $dbRes
            ];
        }

        // Step 2: Create Database User
        $userRes = $this->callUapi('Mysql', 'create_user', [
            'name'     => $cleanDbUser,
            'password' => $dbPass
        ]);
        if ($userRes['status'] !== 1) {
            // User might already exist, check error
            $err = $userRes['errors'][0] ?? '';
            if (!str_contains(strtolower($err), 'already exists')) {
                return [
                    'success' => false,
                    'message' => 'Failed to create database user on cPanel: ' . $err,
                    'step'    => 'create_user',
                    'details' => $userRes
                ];
            }
        }

        // Step 3: Grant ALL PRIVILEGES
        $privRes = $this->callUapi('Mysql', 'set_privileges_on_database', [
            'user'       => $cleanDbUser,
            'database'   => $cleanDbName,
            'privileges' => 'ALL PRIVILEGES'
        ]);

        if ($privRes['status'] !== 1) {
            return [
                'success' => false,
                'message' => 'Failed to grant privileges on cPanel: ' . ($privRes['errors'][0] ?? 'Error'),
                'step'    => 'set_privileges',
                'details' => $privRes
            ];
        }

        return [
            'success' => true,
            'message' => "Database '{$cleanDbName}' and user '{$cleanDbUser}' successfully provisioned on cPanel.",
            'data'    => [
                'host'     => 'localhost',
                'database' => $cleanDbName,
                'username' => $cleanDbUser,
                'password' => $dbPass,
                'port'     => 3306
            ]
        ];
    }

    public function listDatabases(): array
    {
        $res = $this->callUapi('Mysql', 'list_databases');
        if ($res['status'] === 1) {
            $list = [];
            foreach (($res['data'] ?? []) as $item) {
                $list[] = [
                    'database' => $item['database'] ?? '',
                    'disk_usage' => $item['disk_usage'] ?? 0,
                    'users' => $item['users'] ?? []
                ];
            }
            return ['success' => true, 'databases' => $list];
        }
        return ['success' => false, 'error' => $res['errors'][0] ?? 'Failed to list databases'];
    }

    private function formatCpanelName(string $name, int $maxLen = 64): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        if (!empty($this->username) && !str_starts_with($clean, $this->username . '_')) {
            $prefix = $this->username . '_';
            $remaining = max(1, $maxLen - strlen($prefix));
            $clean = $prefix . substr($clean, 0, $remaining);
        }
        return substr($clean, 0, $maxLen);
    }

    private function callUapi(string $module, string $function, array $params = []): array
    {
        $proto = $this->useSsl ? 'https://' : 'http://';
        $hostClean = preg_replace('#^https?://#i', '', $this->host);
        $url = "{$proto}{$hostClean}:{$this->port}/execute/{$module}/{$function}";

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            "Authorization: cpanel {$this->username}:{$this->apiToken}",
            "Accept: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'status' => 0,
                'errors' => ["cURL error: {$curlErr}"]
            ];
        }

        $decoded = @json_decode($response, true);
        if ($decoded === null) {
            return [
                'status' => 0,
                'errors' => ["Invalid JSON response from cPanel: " . substr($response, 0, 150)]
            ];
        }

        return $decoded;
    }
}
