<?php
/**
 * LiveConfigGenerator - Canlıya Özel İzolasyonlu Konfigürasyon ve Şema Kurulum Motoru
 * 
 * Bu sınıf:
 * 1. Yerel dosyalara ASLA yazmadan bellekte (in-memory) canlıya özel konfigürasyon dosyalarını (.env, config/database.php, wp-config.php) üretir.
 * 2. Yerel veritabanı şemasını (schema dumper) dışa aktarır ve canlı sunucuda çalıştırılabilir SQL üretir/uygular.
 * 3. Canlı veritabanındaki tablo içi URL kayıtlarını (wp_options, settings, options vb.) hedef canlı domain ile otomatik günceller.
 * 4. Yerel geliştirme dosyalarının %100 temiz ve dokunulmaz kalmasını garanti eder.
 */
class LiveConfigGenerator
{
    /**
     * Canlıya özel .env dosyasını bellekte üretir.
     */
    public static function generateDotEnv(string $originalContent, array $replacements): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $originalContent));
        $result = [];
        $handledKeys = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                $result[] = $line;
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $keyTrim = trim($key);
                if (isset($replacements[$keyTrim])) {
                    $newVal = self::formatEnvValue($replacements[$keyTrim]);
                    $result[] = "{$keyTrim}={$newVal}";
                    $handledKeys[$keyTrim] = true;
                } else {
                    $result[] = $line;
                }
            } else {
                $result[] = $line;
            }
        }

        // Eksik kalan kritik değişkenleri dosya sonuna ekle
        foreach ($replacements as $k => $v) {
            if (!isset($handledKeys[$k])) {
                $newVal = self::formatEnvValue($v);
                $result[] = "{$k}={$newVal}";
            }
        }

        return implode("\n", $result);
    }

    /**
     * Canlıya özel PHP config/database.php veya benzeri array konfigürasyonunu üretir.
     */
    public static function generatePhpConfig(string $originalContent, array $dbConfig): string
    {
        $content = $originalContent;

        $replacements = [
            'host'     => $dbConfig['host'] ?? 'localhost',
            'database' => $dbConfig['database'] ?? '',
            'dbname'   => $dbConfig['database'] ?? '',
            'user'     => $dbConfig['username'] ?? '',
            'username' => $dbConfig['username'] ?? '',
            'pass'     => $dbConfig['password'] ?? '',
            'password' => $dbConfig['password'] ?? '',
            'port'     => $dbConfig['port'] ?? 3306
        ];

        foreach ($replacements as $key => $val) {
            $pattern = "/(['\"]" . preg_quote($key, '/') . "['\"]\s*=>\s*['\"])[^'\"]*(['\"])/i";
            if (preg_match($pattern, $content)) {
                $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$val);
                $content = preg_replace($pattern, "\${1}{$escaped}\${2}", $content);
            }
        }

        return $content;
    }

    /**
     * Canlıya özel wp-config.php içeriğini üretir.
     */
    public static function generateWpConfig(string $originalContent, array $dbConfig, string $liveDomain): string
    {
        $content = $originalContent;
        $liveDomain = rtrim($liveDomain, '/');
        if (!str_starts_with($liveDomain, 'http://') && !str_starts_with($liveDomain, 'https://')) {
            $liveDomain = 'https://' . $liveDomain;
        }

        $constants = [
            'DB_NAME'     => $dbConfig['database'] ?? '',
            'DB_USER'     => $dbConfig['username'] ?? '',
            'DB_PASSWORD' => $dbConfig['password'] ?? '',
            'DB_HOST'     => $dbConfig['host'] ?? 'localhost',
            'WP_SITEURL'  => $liveDomain,
            'WP_HOME'     => $liveDomain,
            'WP_DEBUG'    => 'false'
        ];

        foreach ($constants as $key => $val) {
            $pattern = "/define\s*\(\s*['\"]" . preg_quote($key, '/') . "['\"]\s*,\s*['\"]?[^'\"]*?['\"]?\s*\)\s*;/i";
            $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$val);
            $replaceVal = ($key === 'WP_DEBUG') ? "define('{$key}', false);" : "define('{$key}', '{$escaped}');";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replaceVal, $content);
            } else {
                // Eklenmemişse ekle
                $content .= "\ndefine('{$key}', '{$escaped}');";
            }
        }

        return $content;
    }

    /**
     * Yerel veritabanı şemasını ve verilerini dışa aktarır (Schema Dumper).
     *
     * @param PDO $pdo Yerel veritabanı bağlantısı
     * @param array $options ['dump_data' => bool, 'exclude_tables' => array]
     * @return string SQL çıktısı
     */
    public static function dumpDatabase(PDO $pdo, array $options = []): string
    {
        $dumpData = $options['dump_data'] ?? true;
        $excludeTables = $options['exclude_tables'] ?? [];

        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $sql = "-- ========================================================\n";
        $sql .= "-- TaskManager Enterprise Deployment Schema Dump\n";
        $sql .= "-- Generated At: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- ========================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            if (in_array($table, $excludeTables)) {
                continue;
            }

            // CREATE TABLE ifadesini al
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $createSql = $createRow[1] ?? '';

            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Table structure for `{$table}`\n";
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createSql . ";\n\n";

            if ($dumpData) {
                $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $sql .= "-- Dumping data for table `{$table}`\n";
                    $columns = array_keys($rows[0]);
                    $colList = implode('`, `', $columns);

                    $sql .= "INSERT INTO `{$table}` (`{$colList}`) VALUES\n";
                    $valueRows = [];
                    foreach ($rows as $row) {
                        $escapedValues = array_map(function ($val) use ($pdo) {
                            if ($val === null) return "NULL";
                            return $pdo->quote($val);
                        }, array_values($row));
                        $valueRows[] = "(" . implode(', ', $escapedValues) . ")";
                    }
                    $sql .= implode(",\n", $valueRows) . ";\n\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $sql;
    }

    /**
     * Dışa aktarılan SQL içindeki ve canlı veritabanındaki eski local domainleri canlı domain ile günceller.
     * 
     * @param string $sql Ham SQL dökümü
     * @param string $oldDomain Örn: http://localhost/taskmanager veya http://127.0.0.1
     * @param string $newDomain Örn: https://example.com
     * @return string Dönüştürülmüş SQL
     */
    public static function replaceDomainsInSql(string $sql, string $oldDomain, string $newDomain): string
    {
        $oldDomain = rtrim($oldDomain, '/');
        $newDomain = rtrim($newDomain, '/');
        if (empty($oldDomain) || empty($newDomain) || $oldDomain === $newDomain) {
            return $sql;
        }

        // 1. Düz URL değişimi
        $replaced = str_replace($oldDomain, $newDomain, $sql);

        // 2. Escape edilmiş JSON URL değişimi (http:\/\/localhost...)
        $oldEscaped = str_replace('/', '\\/', $oldDomain);
        $newEscaped = str_replace('/', '\\/', $newDomain);
        $replaced = str_replace($oldEscaped, $newEscaped, $replaced);

        return $replaced;
    }

    /**
     * Canlı veritabanındaki ayar/seçenek tablolarında domain eşitlemesi yapar.
     * 
     * Otomatik olarak bilinen WordPress wp_options ve özel MVC settings tablolarını kontrol eder.
     */
    public static function synchronizeLiveDatabaseDomains(PDO $livePdo, string $oldDomain, string $newDomain): array
    {
        $oldDomain = rtrim($oldDomain, '/');
        $newDomain = rtrim($newDomain, '/');
        $updates = [];

        $stmt = $livePdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($tables as $t) {
            $tLower = strtolower($t);

            // 1. WordPress wp_options
            if (str_ends_with($tLower, 'options')) {
                // siteurl ve home
                $optStmt = $livePdo->prepare("UPDATE `{$t}` SET option_value = ? WHERE option_name IN ('siteurl', 'home')");
                $optStmt->execute([$newDomain]);
                $updates[] = "Updated {$t} (siteurl, home) -> {$newDomain} ({$optStmt->rowCount()} rows)";
            }

            // 2. Custom MVC settings / options / config tablosu
            if ($tLower === 'settings' || $tLower === 'setting' || $tLower === 'options' || $tLower === 'config') {
                $colsStmt = $livePdo->query("SHOW COLUMNS FROM `{$t}`");
                $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                foreach (['siteurl', 'site_url', 'base_url', 'app_url', 'url', 'directory'] as $c) {
                    if (in_array($c, $columns)) {
                        $upStmt = $livePdo->prepare("UPDATE `{$t}` SET `{$c}` = ? WHERE `{$c}` LIKE ?");
                        $upStmt->execute([$newDomain, "%{$oldDomain}%"]);
                        if ($upStmt->rowCount() > 0) {
                            $updates[] = "Updated {$t}.{$c} -> {$newDomain} ({$upStmt->rowCount()} rows)";
                        }
                    }
                }
            }
        }

        return [
            'success'      => true,
            'old_domain'   => $oldDomain,
            'new_domain'   => $newDomain,
            'actions_taken'=> $updates
        ];
    }

    /**
     * Şema dökümünü canlı hedef veritabanında çalıştırır.
     */
    public static function executeSchemaOnLive(PDO $livePdo, string $sqlDump): array
    {
        try {
            $livePdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            $livePdo->exec($sqlDump);
            return [
                'success' => true,
                'message' => 'Schema successfully deployed to live database.'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Failed to deploy schema on live database: ' . $e->getMessage()
            ];
        }
    }

    private static function formatEnvValue(mixed $val): string
    {
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }
        $str = (string)$val;
        if (str_contains($str, ' ') || str_contains($str, '#') || str_contains($str, '$')) {
            return '"' . addcslashes($str, '"') . '"';
        }
        return $str;
    }
}
