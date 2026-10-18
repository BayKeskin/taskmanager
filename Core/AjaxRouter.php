<?php
namespace Core;

class AjaxRouter
{
    /**
     * Önceden taranıp hafızaya alınan otomatik eylem eşlemeleri:
     * ['action_name' => ['ControllerNamespace', 'methodName']]
     */
    private static ?array $autoDiscoveredMap = null;

    /**
     * Gelen eylemi aktif modüllerin Controllers/AjaxController sınıflarında otomatik arar ve çalıştırır.
     * Hiçbir manuel kod veya liste yazılmasına gerek YOKTUR (Tam Tak-Çalıştır / Zero-Touch).
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
            // ModuleLoader henüz tetiklenmediyse Modules klasörünü dinamik tara
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
                // Örn: syncProjectRules -> sync_project_rules
                // Örn: testTelegram -> test_telegram (ve test_telegram_connection)
                $snakeAction = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $methodName));

                self::$autoDiscoveredMap[$snakeAction] = [$className, $methodName];
                self::$autoDiscoveredMap[$methodName]  = [$className, $methodName]; // Olası birebir camelCase çağrılar için
            }
        }

        // 3. Geçmişten gelen özel veya alias adlandırmalar varsa (Geriye Dönük Uyumluluk):
        $aliases = [
            'test_telegram_connection' => ['Modules\Settings\Controllers\AjaxController', 'testTelegram'],
            'test_smtp_connection'     => ['Modules\Settings\Controllers\AjaxController', 'testSmtp'],
            'test_slack_webhook'       => ['Modules\Settings\Controllers\AjaxController', 'testSlack'],
            'test_discord_webhook'     => ['Modules\Settings\Controllers\AjaxController', 'testDiscord'],
            'save_system_settings'     => ['Modules\Settings\Controllers\AjaxController', 'saveSettings'],
            'save_github_settings'     => ['Modules\GithubDevops\Controllers\AjaxController', 'saveSettings'],
            'sync_project_github'      => ['Modules\GithubDevops\Controllers\AjaxController', 'syncProject'],
            'bulk_create_hierarchical_tasks' => ['Modules\AiArchitect\Controllers\AjaxController', 'bulkCreateTasks'],
            'decompose_brief_with_ai'  => ['Modules\AiArchitect\Controllers\AjaxController', 'decomposeBrief'],
            'upload_doc_attachment'    => ['Modules\Docs\Controllers\AjaxController', 'uploadAttachment'],
            'get_doc_attachments'      => ['Modules\Docs\Controllers\AjaxController', 'getAttachments'],
            'delete_doc_attachment'    => ['Modules\Docs\Controllers\AjaxController', 'deleteAttachment'],
            'get_chat_messages'        => ['Modules\Chat\Controllers\AjaxController', 'getMessages'],
            'send_chat_message'        => ['Modules\Chat\Controllers\AjaxController', 'sendMessage'],
            'get_chat_conversations'   => ['Modules\Chat\Controllers\AjaxController', 'getConversations'],
            'get_daily_standup'        => ['Modules\Reports\Controllers\AjaxController', 'getDailyStandup'],
            'send_standup_email'       => ['Modules\Reports\Controllers\AjaxController', 'sendStandupEmail'],
            'refine_task_specification'=> ['Modules\AiArchitect\Controllers\AiArchitectController', 'refineSpecification'],
        ];

        foreach ($aliases as $act => $target) {
            self::$autoDiscoveredMap[$act] = $target;
        }
    }

    /**
     * Dışarıdan dinamik rota kaydetme (Gerektiğinde)
     */
    public static function register(string $action, string $controllerClass, string $method): void
    {
        if (self::$autoDiscoveredMap === null) {
            self::discoverAllModuleActions();
        }
        self::$autoDiscoveredMap[$action] = [$controllerClass, $method];
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
