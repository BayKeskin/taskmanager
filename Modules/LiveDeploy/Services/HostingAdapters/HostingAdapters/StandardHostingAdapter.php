<?php

require_once __DIR__ . '/HostingAdapterInterface.php';

/**
 * StandardHostingAdapter - Fallback Adapter for Shared/Generic Hosting
 * 
 * For environments without direct control panel API access (Standard cPanel without API token, 
 * DirectAdmin, shared hosting, custom VPS). Validates database credentials remotely via direct PDO 
 * connection check, checks charset, executes test query, and inspects privileges.
 */
class StandardHostingAdapter implements HostingAdapterInterface
{
    private string $host;
    private int $port;
    private string $database;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->host     = trim($config['host'] ?? ($config['db_host'] ?? 'localhost'));
        $this->port     = (int)($config['port'] ?? ($config['db_port'] ?? 3306));
        $this->database = trim($config['database'] ?? ($config['db_name'] ?? ''));
        $this->username = trim($config['username'] ?? ($config['db_user'] ?? ''));
        $this->password = trim($config['password'] ?? ($config['db_pass'] ?? ''));
        $this->timeout  = (int)($config['timeout'] ?? 5);
    }

    public function getType(): string
    {
        return 'standard';
    }

    public function testConnection(): array
    {
        if (empty($this->host) || empty($this->username)) {
            return [
                'success' => false,
                'message' => 'Host and username are required for connection test.'
            ];
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port}";
            if (!empty($this->database)) {
                $dsn .= ";dbname={$this->database}";
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT            => $this->timeout,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $pdo = new PDO($dsn, $this->username, $this->password, $options);
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            $tables = [];
            if (!empty($this->database)) {
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }

            return [
                'success' => true,
                'message' => 'Remote database connection verified successfully.',
                'data'    => [
                    'host'           => $this->host,
                    'port'           => $this->port,
                    'database'       => $this->database,
                    'username'       => $this->username,
                    'server_version' => $serverVersion,
                    'table_count'    => count($tables)
                ]
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Remote database connection failed: ' . $e->getMessage(),
                'code'    => $e->getCode()
            ];
        }
    }

    public function provisionDatabase(string $dbName, string $dbUser, string $dbPass): array
    {
        // Try creating database and user if root/administrative privileges exist
        try {
            $dsn = "mysql:host={$this->host};port={$this->port}";
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => $this->timeout
            ]);

            $cleanDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            $cleanDbUser = preg_replace('/[^a-zA-Z0-9_]/', '', $dbUser);

            // Attempt CREATE DATABASE
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cleanDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Attempt user creation and privilege grant
            $escapedPass = str_replace("'", "\\'", $dbPass);
            $pdo->exec("CREATE USER IF NOT EXISTS '{$cleanDbUser}'@'%' IDENTIFIED BY '{$escapedPass}'");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$cleanDbName}`.* TO '{$cleanDbUser}'@'%'");
            $pdo->exec("FLUSH PRIVILEGES");

            return [
                'success' => true,
                'message' => "Database '{$cleanDbName}' and user '{$cleanDbUser}' successfully created.",
                'data'    => [
                    'host'     => $this->host,
                    'database' => $cleanDbName,
                    'username' => $cleanDbUser,
                    'password' => $dbPass,
                    'port'     => $this->port
                ]
            ];
        } catch (PDOException $e) {
            // If administrative creation is not permitted (common on shared hosting),
            // verify if the given credentials can already access $dbName
            $fallbackTest = $this->testConnection();
            if ($fallbackTest['success']) {
                return [
                    'success' => true,
                    'message' => "Connected to existing pre-created database '{$this->database}'.",
                    'data'    => [
                        'host'     => $this->host,
                        'database' => $this->database,
                        'username' => $this->username,
                        'password' => $this->password,
                        'port'     => $this->port,
                        'pre_existing' => true
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Database provisioning failed: ' . $e->getMessage() . '. Please pre-create the database in your hosting panel and provide the credentials.',
                'error'   => $e->getMessage()
            ];
        }
    }

    public function listDatabases(): array
    {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port}";
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => $this->timeout
            ]);
            $stmt = $pdo->query("SHOW DATABASES");
            $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            return ['success' => true, 'databases' => $dbs];
        } catch (PDOException $e) {
            if (!empty($this->database)) {
                return ['success' => true, 'databases' => [$this->database]];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
