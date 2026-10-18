<?php
namespace Core;

/**
 * ModuleLoader - PSR-4 Autoloading ve Dinamik Modül Yükleyici
 * Modules/ altındaki aktif modülleri tarar, autoloader'a bağlar ve init.php'lerini başlatır.
 */
class ModuleLoader
{
    private static array $loadedModules = [];
    private static bool $booted = false;

    /**
     * Modül sistemini başlatır
     */
    public static function boot(string $modulesPath): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        // 1. PSR-4 Benzeri Sınıf Yükleyici (Örn: Core\BaseModel, Modules\ExcelExport\Controllers\ExcelExportController)
        spl_autoload_register(function ($class) {
            $baseDir = dirname(__DIR__); // c:\wamp64\www\taskmanager
            $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
            $filePath = $baseDir . DIRECTORY_SEPARATOR . $classPath . '.php';

            if (file_exists($filePath)) {
                require_once $filePath;
            }
        });

        // 2. Modules/ klasörünü tara ve aktif modülleri yükle
        if (!is_dir($modulesPath)) {
            @mkdir($modulesPath, 0755, true);
        }

        $manifests = glob($modulesPath . '/*/module.json');
        if (!$manifests) {
            return;
        }

        // 2. Veritabanından veya yapılandırmadan modül durumlarını öğren
        $enabledModulesMap = null;
        $db = $GLOBALS['db'] ?? null;
        if (isset($db) && is_object($db) && isset($db->connect)) {
            try {
                $row = $db->connect->query("SELECT enabled_modules FROM settings WHERE id = 1 LIMIT 1")->fetch(\PDO::FETCH_OBJ);
                if ($row && !empty($row->enabled_modules)) {
                    $decoded = json_decode($row->enabled_modules, true);
                    if (is_array($decoded)) {
                        $enabledModulesMap = $decoded;
                    }
                }
            } catch (\Throwable $e) {
                // Settings tablosu henüz yoksa veya sütun eklenmediyse varsayılan module.json kullanılır
            }
        }

        foreach ($manifests as $manifestFile) {
            $jsonContent = file_get_contents($manifestFile);
            $config = json_decode($jsonContent, true);

            if (empty($config) || !isset($config['slug'])) {
                continue;
            }

            // Veritabanında kayıtlı açık/kapalı durumu varsa önceliklidir
            $slug = $config['slug'];
            if ($enabledModulesMap !== null && array_key_exists($slug, $enabledModulesMap)) {
                $isActive = (bool)$enabledModulesMap[$slug];
            } else {
                $isActive = !isset($config['active']) || (bool)$config['active'];
            }

            $config['is_active'] = $isActive;
            $moduleDir = dirname($manifestFile);
            $config['_dir'] = $moduleDir;

            if ($isActive) {
                self::$loadedModules[$slug] = $config;

                // Modülün init.php dosyasını yükle (Kancalar, Rotalar, Dinleyiciler)
                $initFile = $moduleDir . DIRECTORY_SEPARATOR . 'init.php';
                if (file_exists($initFile)) {
                    require_once $initFile;
                }
            }
        }
    }

    /**
     * Yüklenen aktif modülleri döndürür
     */
    public static function getLoadedModules(): array
    {
        return self::$loadedModules;
    }

    /**
     * Modules/ klasöründeki TÜM modülleri (aktif ve pasif) bilgileriyle listeler
     */
    public static function getAllAvailableModules(): array
    {
        $modulesPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Modules';
        $manifests = glob($modulesPath . '/*/module.json');
        $all = [];

        $enabledModulesMap = null;
        $db = $GLOBALS['db'] ?? null;
        if (isset($db) && is_object($db) && isset($db->connect)) {
            try {
                $row = $db->connect->query("SELECT enabled_modules FROM settings WHERE id = 1 LIMIT 1")->fetch(\PDO::FETCH_OBJ);
                if ($row && !empty($row->enabled_modules)) {
                    $decoded = json_decode($row->enabled_modules, true);
                    if (is_array($decoded)) {
                        $enabledModulesMap = $decoded;
                    }
                }
            } catch (\Throwable $e) {}
        }

        if ($manifests) {
            foreach ($manifests as $manifestFile) {
                $config = json_decode(file_get_contents($manifestFile), true);
                if (!empty($config) && isset($config['slug'])) {
                    $slug = $config['slug'];
                    if ($enabledModulesMap !== null && array_key_exists($slug, $enabledModulesMap)) {
                        $config['is_active'] = (bool)$enabledModulesMap[$slug];
                    } else {
                        $config['is_active'] = !isset($config['active']) || (bool)$config['active'];
                    }
                    $config['_dir'] = dirname($manifestFile);
                    $all[$slug] = $config;
                }
            }
        }

        return $all;
    }

    /**
     * Belirli bir modülün aktif olup olmadığını denetler
     */
    public static function isModuleActive(string $slug): bool
    {
        return isset(self::$loadedModules[$slug]);
    }

    /**
     * Bir modülün durumunu (aktif/pasif) veritabanına kaydeder
     */
    public static function setModuleStatus(string $slug, bool $active): bool
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || !is_object($db) || !isset($db->connect)) {
            return false;
        }

        try {
            $row = $db->connect->query("SELECT enabled_modules FROM settings WHERE id = 1 LIMIT 1")->fetch(\PDO::FETCH_OBJ);
            $currentMap = [];
            if ($row && !empty($row->enabled_modules)) {
                $decoded = json_decode($row->enabled_modules, true);
                if (is_array($decoded)) {
                    $currentMap = $decoded;
                }
            }

            $currentMap[$slug] = $active;
            $json = json_encode($currentMap, JSON_UNESCAPED_UNICODE);

            $stmt = $db->connect->prepare("UPDATE settings SET enabled_modules = ? WHERE id = 1");
            return $stmt->execute([$json]);
        } catch (\Throwable $e) {
            error_log("Failed to setModuleStatus: " . $e->getMessage());
            return false;
        }
    }
}
