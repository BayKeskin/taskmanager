<?php
namespace Core;

class AjaxRouter
{
    /**
     * Eylemleri işleyecek modül Controller eşleştirme tablosu
     */
    private static array $routeMap = [
        // Projects Modülü
        'create_project'            => ['Modules\Projects\Controllers\AjaxController', 'createProject'],
        'get_project'               => ['Modules\Projects\Controllers\AjaxController', 'getProject'],
        'update_project'            => ['Modules\Projects\Controllers\AjaxController', 'updateProject'],
        'sync_project_rules'        => ['Modules\Projects\Controllers\AjaxController', 'syncProjectRules'],
        'refine_project_rules'      => ['Modules\Projects\Controllers\AjaxController', 'refineProjectRules'],
        'test_project_webhook'      => ['Modules\Projects\Controllers\AjaxController', 'testProjectWebhook'],
        'toggle_project_status'     => ['Modules\Projects\Controllers\AjaxController', 'toggleProjectStatus'],
        'delete_project'            => ['Modules\Projects\Controllers\AjaxController', 'deleteProject'],

        // Team Modülü
        'create_user'               => ['Modules\Team\Controllers\AjaxController', 'createUser'],
        'delete_user'               => ['Modules\Team\Controllers\AjaxController', 'deleteUser'],
        'update_profile'            => ['Modules\Team\Controllers\AjaxController', 'updateProfile'],

        // Settings, Telegram & Mailer
        'save_system_settings'      => ['Modules\Settings\Controllers\AjaxController', 'saveSettings'],
        'toggle_module_status'      => ['Modules\Settings\Controllers\AjaxController', 'toggleModuleStatus'],
        'test_telegram_connection'  => ['Modules\Settings\Controllers\AjaxController', 'testTelegram'],
        'test_smtp_connection'      => ['Modules\Settings\Controllers\AjaxController', 'testSmtp'],
        'test_slack_webhook'        => ['Modules\Settings\Controllers\AjaxController', 'testSlack'],
        'test_discord_webhook'      => ['Modules\Settings\Controllers\AjaxController', 'testDiscord'],

        // Docs Modülü
        'get_doc'                   => ['Modules\Docs\Controllers\AjaxController', 'getDoc'],
        'create_doc'                => ['Modules\Docs\Controllers\AjaxController', 'createDoc'],
        'save_doc'                  => ['Modules\Docs\Controllers\AjaxController', 'saveDoc'],
        'delete_doc'                => ['Modules\Docs\Controllers\AjaxController', 'deleteDoc'],
        'upload_doc_attachment'     => ['Modules\Docs\Controllers\AjaxController', 'uploadAttachment'],
        'get_doc_attachments'       => ['Modules\Docs\Controllers\AjaxController', 'getAttachments'],
        'delete_doc_attachment'     => ['Modules\Docs\Controllers\AjaxController', 'deleteAttachment'],

        // Chat Modülü
        'get_chat_messages'         => ['Modules\Chat\Controllers\AjaxController', 'getMessages'],
        'send_chat_message'         => ['Modules\Chat\Controllers\AjaxController', 'sendMessage'],
        'get_chat_conversations'    => ['Modules\Chat\Controllers\AjaxController', 'getConversations'],

        // Reports Modülü
        'get_daily_standup'         => ['Modules\Reports\Controllers\AjaxController', 'getDailyStandup'],
        'send_standup_email'        => ['Modules\Reports\Controllers\AjaxController', 'sendStandupEmail'],

        // AiArchitect Modülü
        'refine_task_specification'      => ['Modules\AiArchitect\Controllers\AiArchitectController', 'refineSpecification'],
        'decompose_brief_with_ai'        => ['Modules\AiArchitect\Controllers\AjaxController', 'decomposeBrief'],
        'bulk_create_hierarchical_tasks' => ['Modules\AiArchitect\Controllers\AjaxController', 'bulkCreateTasks'],

        // GithubDevops Modülü
        'save_github_settings'      => ['Modules\GithubDevops\Controllers\AjaxController', 'saveSettings'],
        'sync_project_github'       => ['Modules\GithubDevops\Controllers\AjaxController', 'syncProject'],
        'rollback_task'             => ['Modules\GithubDevops\Controllers\AjaxController', 'rollbackTask'],

        // Mcp Modülü
        'get_project_mcps'          => ['Modules\Mcp\Controllers\AjaxController', 'getProjectMcps'],
        'save_project_mcp'          => ['Modules\Mcp\Controllers\AjaxController', 'saveProjectMcp'],
        'toggle_project_mcp'        => ['Modules\Mcp\Controllers\AjaxController', 'toggleProjectMcp'],
        'sync_project_mcps'         => ['Modules\Mcp\Controllers\AjaxController', 'syncProjectMcps'],

        // LiveDeploy Modülü
        'analyze_project'           => ['Modules\LiveDeploy\Controllers\AjaxController', 'analyzeProject'],
        'test_hosting_panel'        => ['Modules\LiveDeploy\Controllers\AjaxController', 'testHostingPanel'],
        'provision_hosting_db'      => ['Modules\LiveDeploy\Controllers\AjaxController', 'provisionHostingDb'],
        'generate_live_config'      => ['Modules\LiveDeploy\Controllers\AjaxController', 'generateLiveConfig'],
        'dump_project_schema'       => ['Modules\LiveDeploy\Controllers\AjaxController', 'dumpProjectSchema'],
        'delta_diff'                => ['Modules\LiveDeploy\Controllers\AjaxController', 'deltaDiff'],
        'record_deployment'         => ['Modules\LiveDeploy\Controllers\AjaxController', 'recordDeployment'],
        'live_healthcheck'          => ['Modules\LiveDeploy\Controllers\AjaxController', 'liveHealthcheck'],
        'rollback_deployment'       => ['Modules\LiveDeploy\Controllers\AjaxController', 'rollbackDeployment'],
    ];

    /**
     * Gelen isteği ilgili modülün Controller'ına yönlendirir.
     * Eğer harici bir modül bu action'ı sahipleniyorsa onu çalıştırır ve true döner.
     * Sahiplenmiyorsa false döner (Board modülünün kendi varsayılan AjaxController'ı devam eder).
     */
    public static function dispatch(string $action): bool
    {
        if (!isset(self::$routeMap[$action])) {
            return false;
        }

        [$controllerClass, $method] = self::$routeMap[$action];

        if (!class_exists($controllerClass)) {
            // Controller dosyasını dinamik olarak yüklemeyi dene
            $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $controllerClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

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
     * Dışarıdan modül bazlı yeni action rota kaydetme (Eklenebilirlik)
     */
    public static function register(string $action, string $controllerClass, string $method): void
    {
        self::$routeMap[$action] = [$controllerClass, $method];
    }
}
