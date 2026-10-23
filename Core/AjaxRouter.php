<?php
namespace Core;

/**
 * TaskManager HMVC Modüler AJAX Yönlendiricisi (Zero-Touch / %100 Otomatik Keşif)
 * 
 * ⚠️ DİKKAT: BU DOSYAYA ASLA MANUEL EYLEM, LİSTE VEYA METOT EKLEMEYİN!
 * Her modül kendi AJAX eylemlerini `Modules/{ModuleName}/Controllers/AjaxController.php`
 * sınıfında public metot olarak tanımlar. Bu yönlendirici, aktif modülleri Reflection
 * ile otomatik tarar ve eylemleri sıfır konfigürasyonla çalıştırır.
 */
class AjaxRouter
{
    /**
     * Önceden taranıp hafızaya alınan otomatik eylem eşlemeleri:
     * ['action_name' => ['ControllerNamespace', 'methodName']]
     */
    private static ?array $autoDiscoveredMap = null;

    /**
     * Gelen eylemi aktif modüllerin Controllers/AjaxController sınıflarında otomatik arar ve çalıştırır.
     */
    public static function dispatch(string $action): bool
    {
        if (empty($action)) {
            return false;
        }

        if (self::$autoDiscoveredMap === null) {
            self::discoverAllModuleActions();
        }

        if (!isset(self::$autoDiscoveredMap[$action])) {
            return false;
        }

        [$controllerClass, $method] = self::$autoDiscoveredMap[$action];

        if (class_exists($controllerClass)) {
            $controller = new $controllerClass();
            if (method_exists($controller, $method)) {
                $controller->$method();
                return true;
            }
        }

        return false;
    }

    /**
     * Modules/ altındaki tüm aktif modüllerin AjaxController sınıflarını otomatik tarar
     * ve içlerindeki public metotları eylem (action) olarak kaydeder.
     */
    private static function discoverAllModuleActions(): void
    {
        self::$autoDiscoveredMap = [];
        $baseDir = dirname(__DIR__);

        $loadedModules = ModuleLoader::getLoadedModules();
        if (empty($loadedModules)) {
            $modulesDirs = glob($baseDir . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        } else {
            $modulesDirs = array_column($loadedModules, '_dir');
        }

        foreach ($modulesDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $ajaxControllerFile = $moduleDir . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'AjaxController.php';

            if (!file_exists($ajaxControllerFile)) {
                continue;
            }

            $className = "Modules\\{$moduleName}\\Controllers\\AjaxController";
            if (!class_exists($className)) {
                require_once $ajaxControllerFile;
            }

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $methodObj) {
                // BaseController'dan gelen genel yardımcı metotları atla
                if ($methodObj->isConstructor() || $methodObj->class === 'Core\BaseController') {
                    continue;
                }

                $methodName = $methodObj->getName();

                // 1. camelCase metodu snake_case action'a çevir:
                // Örn: createProject -> create_project
                // Örn: getProjectTimeline -> get_project_timeline
                $snakeAction = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $methodName));

                self::$autoDiscoveredMap[$snakeAction] = [$className, $methodName];
                self::$autoDiscoveredMap[$methodName]  = [$className, $methodName]; // Olası camelCase çağrılar için
            }
        }
    }

    /**
     * Debug amaçlı keşfedilen tüm otomatik eylemleri döner
     */
    public static function getDiscoveredActions(): array
    {
        if (self::$autoDiscoveredMap === null) {
            self::discoverAllModuleActions();
        }
        return self::$autoDiscoveredMap;
    }
}
