<?php

class Database
{
    private $host;
    private $database;
    private $user;
    private $pass;
    private $charset = "utf8mb4";
    private $collation = "utf8mb4_turkish_ci";
    public $connect;
    public $connect2;
    public $get_information = array();

    public function __construct(?array $config = null)
    {
        // 1. Dışarıdan config gönderilmediyse config dosyasından oku
        if ($config === null) {
            $configFile = __DIR__ . '/Config/database.php';
            if (!file_exists($configFile)) {
                $configFile = __DIR__ . '/../config/database.php';
            }
            if (file_exists($configFile)) {
                $fileConfig = require $configFile;
                $config = $fileConfig['default'] ?? [];
            }
        }

        if (empty($config) || empty($config['database'])) {
            throw new InvalidArgumentException("Veritabanı yapılandırması bulunamadı. Lütfen config/database.php dosyasını kontrol edin.");
        }

        // 2. Yapılandırma değerlerini ata
        $this->host = $config['host'] ?? 'localhost';
        $this->database = $config['database'];
        $this->user = $config['user'] ?? 'root';
        $this->pass = $config['pass'] ?? '';
        $this->charset = $config['charset'] ?? $this->charset;
        $this->collation = $config['collation'] ?? $this->collation;

        // 3. Bağlantıyı kur
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connect = new PDO($dsn, $this->user, $this->pass, $options);
            $this->connect->exec("SET NAMES '{$this->charset}' COLLATE '{$this->collation}'");
        } catch (PDOException $e) {
            error_log("Database Connection Error ({$this->host}): " . $e->getMessage());
            throw new RuntimeException("Veritabanı bağlantısı sağlanamadı. Lütfen sistem yöneticisiyle iletişime geçin.");
        }
    }

    public function connect_server($host, $db, $user, $pass, $charset = 'utf8mb4', $collation = 'utf8mb4_turkish_ci')
    {
        try {
            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connect2 = new PDO($dsn, $user, $pass, $options);
            $this->connect2->exec("SET NAMES '{$charset}' COLLATE '{$collation}'");
        } catch (PDOException $e) {
            error_log("Secondary Database Connection Error ({$host}): " . $e->getMessage());
            throw new RuntimeException("İkinci veritabanı sunucusuna bağlanılamadı.");
        }
    }

    /**
     * Aktif PDO bağlantısını döner
     */
    private function getConnection($connect = '')
    {
        return (!empty($connect) && $this->connect2 instanceof PDO) ? $this->connect2 : $this->connect;
    }

    /**
     * Merkezi sorgu hazırlayıcı ve çalıştırıcı
     */
    public function executeQuery($sql, $params = array(), $connect = '')
    {
        $pdo = $this->getConnection($connect);
        if (!$pdo) {
            return false;
        }
        $stmt = $pdo->prepare($sql);
        if (empty($params)) {
            $stmt->execute();
        } else {
            $isAssoc = is_array($params) && (array_keys($params) !== range(0, count($params) - 1));
            $stmt->execute($isAssoc ? $params : array_values($params));
        }
        return $stmt;
    }

    public function get_single($table, $area, $condition, $conditions, $connect = '')
    {
        $query = "SELECT {$area} FROM {$table} WHERE {$condition} = ? LIMIT 1";
        $stmt = $this->executeQuery($query, array($conditions), $connect);
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row ? $row->{$area} : null;
        }
        return null;
    }

    public function get_single_multi($table, $area, $condition = array(), $conditions = array(), $parameters = 'AND', $sign = '=', $order = null, $order_tyoe = 'ASC', $limit = null, $connect = '')
    {
        $sign = !empty($sign) ? trim($sign) : '=';
        $parameters = !empty($parameters) ? trim($parameters) : 'AND';

        $whereParts = array_map(fn($col) => "{$col} {$sign} ?", $condition);
        $whereSql = !empty($whereParts) ? "WHERE " . implode(" {$parameters} ", $whereParts) : "";

        $orderSql = ($order !== null) ? "ORDER BY {$order} {$order_tyoe}" : "";
        $limitSql = ($limit !== null) ? "LIMIT {$limit}" : "LIMIT 1";

        $query = "SELECT {$area} FROM {$table} {$whereSql} {$orderSql} {$limitSql}";
        $stmt = $this->executeQuery($query, $conditions, $connect);

        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row ? $row->{$area} : null;
        }
        return null;
    }

    public function update_not_params($table, $condition = array(), $where = '', $id = '', $exceptional = array(), $exceptionals = array(), $connect = '', $sourceData = null)
    {
        $fields = array_merge($condition, $exceptional);
        $setClause = implode(', ', array_map(fn($col) => "{$col} = ?", $fields));

        $dataSource = $sourceData ?? $_POST;
        $values = array();
        foreach ($condition as $row) {
            $values[] = $dataSource[$row] ?? null;
        }
        foreach ($exceptionals as $item) {
            $values[] = $item;
        }
        $values[] = $id;

        $query = "UPDATE {$table} SET {$setClause} WHERE {$where} = ?";
        $stmt = $this->executeQuery($query, $values, $connect);
        return $stmt ? 1 : 0;
    }

    public function update($table, $condition = array(), $conditions = array(), $where = '', $connect = '')
    {
        $setClause = implode(', ', array_map(fn($col) => "{$col} = ?", $condition));
        $whereClause = !empty($where) ? "WHERE {$where} = ?" : "";

        $query = "UPDATE {$table} SET {$setClause} {$whereClause}";
        $stmt = $this->executeQuery($query, $conditions, $connect);
        return $stmt ? 1 : 0;
    }

    public function delete($table = '', $condition = array(), $conditions = array(), $parameters = '', $connect = '')
    {
        $op = !empty($parameters) ? trim($parameters) : '=';
        $whereClause = implode(' AND ', array_map(fn($col) => "{$col} {$op} ?", $condition));

        $query = "DELETE FROM {$table} WHERE {$whereClause}";
        $stmt = $this->executeQuery($query, $conditions, $connect);
        return $stmt ? 1 : 0;
    }

    public function insert($table, $condition = array(), $conditions = array(), $connect = '')
    {
        $hasPlaceholders = false;
        foreach ($condition as $item) {
            $trimmed = strtoupper(trim((string)$item));
            if ($trimmed === '?' || $trimmed === 'NULL') {
                $hasPlaceholders = true;
                break;
            }
        }

        if ($hasPlaceholders) {
            $placeholders = implode(', ', $condition);
            $query = "INSERT INTO {$table} VALUES ({$placeholders})";
        } else {
            $cols = implode(', ', $condition);
            $placeholders = implode(', ', array_fill(0, count($condition), '?'));
            $query = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";
        }

        $stmt = $this->executeQuery($query, $conditions, $connect);
        return $stmt ? 1 : 0;
    }

    public function insert_return_count($table, $condition = array(), $conditions = array(), $connect = '')
    {
        $inserted = $this->insert($table, $condition, $conditions, $connect);
        if ($inserted) {
            $pdo = $this->getConnection($connect);
            return (int) $pdo->lastInsertId();
        }
        return 0;
    }

    public function multi_record_query($query, $params = array(), $return_type = "", $connect = '')
    {
        if (is_string($params) && !empty($params)) {
            $return_type = $params;
            $params = array();
        } elseif (!is_array($params)) {
            $params = array();
        }

        $this->get_information = null;
        $stmt = $this->executeQuery($query, $params, $connect);

        if (!$stmt || $stmt->rowCount() < 1) {
            return false;
        }

        $records = $stmt->fetchAll(PDO::FETCH_OBJ);
        if ($return_type === "") {
            return $records;
        }

        return array($records, $stmt->rowCount());
    }

    /**
     * Tek bir satırı nesne (Object) olarak döner
     */
    public function row_query($query, $params = array(), $connect = '')
    {
        $stmt = $this->executeQuery($query, $params, $connect);
        if (!$stmt || $stmt->rowCount() < 1) {
            return null;
        }
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Tek bir değeri (skaler/sütun) döner
     */
    public function single_query($query, $params = array(), $connect = '')
    {
        $stmt = $this->executeQuery($query, $params, $connect);
        if (!$stmt || $stmt->rowCount() < 1) {
            return null;
        }
        return $stmt->fetchColumn();
    }

    /**
     * SQL sorgusunu çalıştırır ve başarılıysa etkilenen satır sayısını veya true döner
     */
    public function exec_query($query, $params = array(), $connect = '')
    {
        $stmt = $this->executeQuery($query, $params, $connect);
        return $stmt !== false ? ($stmt->rowCount() ?: true) : false;
    }

    /**
     * İki sütunlu sorguları Anahtar => Değer dizisi (KEY_PAIR) olarak döner
     */
    public function pair_query($query, $params = array(), $connect = '')
    {
        $stmt = $this->executeQuery($query, $params, $connect);
        if (!$stmt) {
            return array();
        }
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: array();
    }

    /**
     * Son eklenen satır ID'sini döner
     */
    public function last_insert_id($connect = '')
    {
        $pdo = $this->getConnection($connect);
        return $pdo ? (int)$pdo->lastInsertId() : 0;
    }

    public function multi_record($table, $condition = array(), $conditions = array(), $operators = "=", $order = "", $order_tyoe = "ASC", $limit = "", $areas = "*", $connect = '')
    {
        $this->get_information = null;
        $areas = !empty($areas) ? $areas : "*";
        $op = !empty($operators) ? trim($operators) : "=";

        $whereClause = "";
        if (!empty($condition)) {
            $parts = array_map(fn($col) => "{$col} {$op} ?", $condition);
            $whereClause = "WHERE " . implode(" AND ", $parts);
        }

        $orderClause = !empty($order) ? "ORDER BY {$order} {$order_tyoe}" : "";
        $limitClause = !empty($limit) ? "LIMIT {$limit}" : "";

        $query = trim("SELECT {$areas} FROM {$table} {$whereClause} {$orderClause} {$limitClause}");
        $stmt = $this->executeQuery($query, $conditions, $connect);

        if (!$stmt || $stmt->rowCount() < 1) {
            return false;
        }

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirtilen koşula göre tek bir satırı nesne (Object) olarak döner
     */
    public function single_record($table, $condition = array(), $conditions = array(), $operators = "=", $areas = "*", $connect = '')
    {
        $rows = $this->multi_record($table, $condition, $conditions, $operators, "", "ASC", "1", $areas, $connect);
        return (!empty($rows) && count($rows) > 0) ? $rows[0] : null;
    }

    public function count_record($table, $condition = array(), $conditions = array(), $operators = "=", $order = "", $order_tyoe = "ASC", $limit = "", $connect = '')
    {
        $this->get_information = null;
        $op = !empty($operators) ? trim($operators) : "=";

        $whereClause = "";
        if (!empty($condition)) {
            $parts = array_map(fn($col) => "{$col} {$op} ?", $condition);
            $whereClause = "WHERE " . implode(" AND ", $parts);
        }

        if (empty($limit)) {
            $query = trim("SELECT COUNT(*) as total FROM {$table} {$whereClause}");
            $stmt = $this->executeQuery($query, $conditions, $connect);
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_OBJ);
                return (int) ($row->total ?? 0);
            }
            return 0;
        }

        $orderClause = !empty($order) ? "ORDER BY {$order} {$order_tyoe}" : "";
        $limitClause = "LIMIT {$limit}";
        $query = trim("SELECT 1 FROM {$table} {$whereClause} {$orderClause} {$limitClause}");
        $stmt = $this->executeQuery($query, $conditions, $connect);
        return $stmt ? $stmt->rowCount() : 0;
    }

    public function count_record_query($query, $condition = array(), $connect = '')
    {
        $stmt = $this->executeQuery($query, $condition, $connect);
        return $stmt ? $stmt->rowCount() : 0;
    }

    public function upsert_provision($fieldGroupId, $value, $postId, $name, $connect = '')
    {
        $query = "INSERT INTO field_groups_provision (field_groups_id, value, post_id, name)
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE value = VALUES(value), name = VALUES(name)";
        $stmt = $this->executeQuery($query, array($fieldGroupId, $value, $postId, $name), $connect);
        return $stmt !== false;
    }

    public function __destruct()
    {
        $this->connect = null;
        $this->connect2 = null;
    }
}