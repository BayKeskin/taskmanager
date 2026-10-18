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

        foreach ($manifests as $manifestFile) {
            $jsonContent = file_get_contents($manifestFile);
            $config = json_decode($jsonContent, true);

            if (empty($config) || !isset($config['slug'])) {
                continue;
            }

            // Varsayılan olarak active: true kabul edilir (ayarlarda aksi belirtilmedikçe)
            $isActive = !isset($config['active']) || (bool)$config['active'];

            if ($isActive) {
                $moduleDir = dirname($manifestFile);
                $config['_dir'] = $moduleDir;
                self::$loadedModules[$config['slug']] = $config;

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
     * Belirli bir modülün aktif olup olmadığını denetler
     */
    public static function isModuleActive(string $slug): bool
    {
        return isset(self::$loadedModules[$slug]);
    }
}
