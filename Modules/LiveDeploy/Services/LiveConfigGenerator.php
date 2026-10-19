<?php
use Modules\LiveDeploy\Models\DeployModel;

/**
 * LiveConfigGenerator - Canlı Ortam Yapılandırma ve Veritabanı İzolasyon Üreteci
 * 
 * Bu sınıf:
 * 1. Projelerin kaynak kodlarındaki ortam değişkenlerini (.env, config.php vb.) canlı sunucuya özel dönüştürür.
 * 2. Yerel geliştirme dosyalarına ve yerel veritabanına asla zarar vermez (100% Non-destructive).
 * 3. SQL şema/veri dışa aktarımı sırasında domain ve URL eşitlemelerini otomatik gerçekleştirir.
 */
class LiveConfigGenerator
{
    /**
     * .env formatındaki içeriği canlı ortam değerleriyle günceller.
     *
     * @param string $sourceContent Orijinal .env dosya içeriği
     * @param array $replacements Değiştirilecek anahtar-değer çiftleri (Örn: ['APP_URL' => 'https://live.com'])
     * @return string Güncellenmiş .env içeriği
     */
    public static function generateDotEnv(string $sourceContent, array $replacements = []): string
    {
        $lines = explode("\n", $sourceContent);
        $result = [];
        $handledKeys = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '#')) {
                $result[] = $line;
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);

                if (array_key_exists($key, $replacements)) {
                    $newVal = $replacements[$key];
                    if (is_bool($newVal)) {
                        $newVal = $newVal ? 'true' : 'false';
                    }
                    if (is_string($newVal) && (strpos($newVal, ' ') !== false || strpos($newVal, '#') !== false)) {
                        $newVal = '"' . addcslashes($newVal, '"') . '"';
                    }
                    $result[] = "{$key}={$newVal}";
                    $handledKeys[] = $key;
                    continue;
                }
            }

            $result[] = $line;
        }

        // Kaynakta olmayan ancak replacements içinde tanımlanmış yeni anahtarları ekle
        foreach ($replacements as $k => $v) {
            if (!in_array($k, $handledKeys)) {
                if (is_bool($v)) {
                    $v = $v ? 'true' : 'false';
                }
                $result[] = "{$k}={$v}";
            }
        }

        return implode("\n", $result);
    }

    /**
     * Standart PHP array config dosyalarındaki veritabanı ve URL parametrelerini günceller.
     *
     * @param string $sourceCode Orijinal PHP config dosyası kodu
     * @param array $dbConfig Canlı DB parametreleri ['host', 'database', 'username', 'password', 'port']
     * @return string Güncellenmiş PHP kodu
     */
    public static function generatePhpConfig(string $sourceCode, array $dbConfig = []): string
    {
        $code = $sourceCode;

        if (!empty($dbConfig['host'])) {
            $code = preg_replace("/('host'\s*=>\s*)'[^']*'/", "\${1}'" . addslashes($dbConfig['host']) . "'", $code);
            $code = preg_replace('/("host"\s*=>\s*)"[^"]*"/', "\${1}\"" . addslashes($dbConfig['host']) . "\"", $code);
        }

        if (!empty($dbConfig['database'])) {
            $code = preg_replace("/('database'|'dbname'\s*=>\s*)'[^']*'/", "\${1}'" . addslashes($dbConfig['database']) . "'", $code);
            $code = preg_replace('/("database"|"dbname"\s*=>\s*)"[^"]*"/', "\${1}\"" . addslashes($dbConfig['database']) . "\"", $code);
        }

        if (!empty($dbConfig['username'])) {
            $code = preg_replace("/('username'|'user'\s*=>\s*)'[^']*'/", "\${1}'" . addslashes($dbConfig['username']) . "'", $code);
            $code = preg_replace('/("username"|"user"\s*=>\s*)"[^"]*"/', "\${1}\"" . addslashes($dbConfig['username']) . "\"", $code);
        }

        if (isset($dbConfig['password'])) {
            $code = preg_replace("/('password'|'pass'\s*=>\s*)'[^']*'/", "\${1}'" . addslashes($dbConfig['password']) . "'", $code);
            $code = preg_replace('/("password"|"pass"\s*=>\s*)"[^"]*"/', "\${1}\"" . addslashes($dbConfig['password']) . "\"", $code);
        }

        if (!empty($dbConfig['port'])) {
            $code = preg_replace("/('port'\s*=>\s*)[0-9]+/", "\${1}" . (int)$dbConfig['port'], $code);
        }

        return $code;
    }

    /**
     * DeployModel kullanarak veritabanı şemasını ve verilerini dışa aktarır.
     *
     * @param DeployModel $model Veritabanı modeli
     * @param array $options ['dump_data' => bool, 'exclude_tables' => []]
     * @return string SQL çıktısı
     */
    public static function dumpDatabaseWithModel(DeployModel $model, array $options = []): string
    {
        $dumpData = $options['dump_data'] ?? true;
        $excludeTables = $options['exclude_tables'] ?? [];

        $tables = $model->getTables();

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

            $createSql = $model->getTableCreateSql($table);

            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Table structure for `{$table}`\n";
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createSql . ";\n\n";

            if ($dumpData) {
                $rows = $model->getTableRows($table);

                if (!empty($rows)) {
                    $sql .= "-- Dumping data for table `{$table}`\n";
                    $firstRow = is_object($rows[0]) ? get_object_vars($rows[0]) : (array)$rows[0];
                    $columns = array_keys($firstRow);
                    $colList = implode('`, `', $columns);

                    $sql .= "INSERT INTO " . "`{$table}` (`{$colList}`) VALUES\n";
                    $valueRows = [];
                    foreach ($rows as $row) {
                        $rowArr = is_object($row) ? get_object_vars($row) : (array)$row;
                        $escapedValues = array_map(function ($val) {
                            if ($val === null) return "NULL";
                            return "'" . addslashes((string)$val) . "'";
                        }, array_values($rowArr));
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
     * SQL dökümündeki yerel alan adlarını canlı alan adıyla güvenle değiştirir.
     */
    public static function replaceDomainsInSql(string $sql, string $oldDomain, string $newDomain): string
    {
        $oldDomain = rtrim($oldDomain, '/');
        $newDomain = rtrim($newDomain, '/');

        $replacements = [
            $oldDomain => $newDomain,
            json_encode($oldDomain) => json_encode($newDomain),
            str_replace('/', '\/', $oldDomain) => str_replace('/', '\/', $newDomain),
            urlencode($oldDomain) => urlencode($newDomain)
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $sql);
    }

    /**
     * Canlı hedef PDO bağlantısında alan adı eşitlemesi yapar.
     */
    public static function synchronizeLiveDatabaseDomains(PDO $livePdo, string $oldDomain, string $newDomain): array
    {
        $oldDomain = rtrim($oldDomain, '/');
        $newDomain = rtrim($newDomain, '/');

        $updates = DeployModel::updateLiveDomainSettings($livePdo, $oldDomain, $newDomain);

        return [
            'success'      => true,
            'old_domain'   => $oldDomain,
            'new_domain'   => $newDomain,
            'actions_taken'=> $updates
        ];
    }
}
