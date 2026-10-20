<?php
namespace Modules\RestApi\Services;

use Core\BaseModel;
use Core\Language;

/**
 * WebhookService - Proje webhook'ları, Slack/Discord entegrasyonu ve HTTP bildirimlerini yönetir.
 */
class WebhookService extends BaseModel
{
    /**
     * Projeye özel Yapay Zeka (AI Agent) Webhook Olay Tetikleyicisi
     */
    public static function triggerProjectWebhook(int $projectId, string $event, array $data = []): array
    {
        $model = new \Modules\RestApi\Models\RestApiModel();
        $project = $model->getProjectWebhookConfig($projectId);

        if (!$project) {
            return ['sent' => false, 'error' => 'Project not found'];
        }

        $targetUrl = !empty($project->webhook_url) ? trim($project->webhook_url) : 'http://localhost:4000/webhook';

        $apiCfg = RestApiService::getDynamicApiConfig();

        $qaModel = new \Modules\AgentRules\Models\AgentRulesModel();
        $qaGlobal = $qaModel->getQaGlobalInstructions();

        $payload = [
            'event'                  => $event,
            'project_id'             => (int)$project->id,
            'project_code'           => $project->project_code,
            'project_title'          => $project->title,
            'workspace_path'         => $project->workspace_path ?: null,
            'agent_rules'            => $project->agent_rules ?: null,
            'agent_conversation_id'  => $project->agent_conversation_id ?: null,
            'dev_conversation_id'    => $project->dev_conversation_id ?: ($project->agent_conversation_id ?: null),
            'qa_conversation_id'     => $project->qa_conversation_id ?: null,
            'qa_test_instructions'   => $project->qa_test_instructions ?: null,
            'qa_global_instructions' => $qaGlobal ?: null,
            'api_base_url'           => $apiCfg['base_url'],
            'api_token'              => $apiCfg['api_token'],
            'timestamp'              => date('c'),
            'data'                   => $data
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: TaskManager-Agent-Webhook/1.0',
            'X-TaskManager-Event: ' . $event,
            'X-TaskManager-Project: ' . $project->project_code,
        ];

        if (!empty($project->agent_conversation_id)) {
            $headers[] = 'X-TaskManager-Conversation-ID: ' . $project->agent_conversation_id;
        }

        if (!empty($project->webhook_secret)) {
            $signature = hash_hmac('sha256', $jsonPayload, $project->webhook_secret);
            $headers[] = 'X-TaskManager-Signature: sha256=' . $signature;
        }

        if (str_contains($targetUrl, '4000') || str_contains($targetUrl, 'localhost') || empty($targetUrl)) {
            if (class_exists('Modules\\Dispatcher\\Services\\DispatcherService')) {
                $disp = new \Modules\Dispatcher\Services\DispatcherService();
                $disp->ensureRunning();
            }
        }

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $sent = ($httpCode >= 200 && $httpCode < 300);
        $effectiveConvId = $project->agent_conversation_id;

        if (!$sent && (str_contains($targetUrl, 'localhost:4000') || str_contains($targetUrl, '127.0.0.1:4000') || empty($targetUrl))) {
            $dispService = new \Modules\Dispatcher\Services\DispatcherService();
            $directResult = $dispService->dispatchDirect($project, $event, $data);
            if (!empty($directResult['sent'])) {
                $sent = true;
                $httpCode = 200;
                $response = json_encode($directResult, JSON_UNESCAPED_UNICODE);
                $error = null;
                if (!empty($directResult['recipient_id'])) {
                    $effectiveConvId = $directResult['recipient_id'];
                }
            }
        }

        $logDir = dirname(__DIR__, 3) . '/Public/uploads';
        if (is_dir($logDir) && is_writable($logDir)) {
            $logEntry = [
                'time'                  => date('Y-m-d H:i:s'),
                'event'                 => $event,
                'project_code'          => $project->project_code,
                'agent_conversation_id' => $effectiveConvId,
                'url'                   => $project->webhook_url,
                'http_code'             => $httpCode,
                'error'                 => $error ?: null
            ];
            $logFile = $logDir . '/webhook_logs.json';
            $existing = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
            array_unshift($existing, $logEntry);
            if (count($existing) > 50) $existing = array_slice($existing, 0, 50);
            @file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        self::dispatchChatWebhooks($event, $project->title ?? 'TaskManager', $data);

        return [
            'sent'                  => $sent,
            'http_code'             => $httpCode,
            'response'              => $response,
            'error'                 => $error ?: null,
            'agent_conversation_id' => $project->agent_conversation_id
        ];
    }

    /**
     * Slack ve Discord genel bildirim webhooklarını tetikler
     */
    public static function dispatchChatWebhooks(string $event, string $projectTitle, array $data = []): void
    {
        try {
            $model = new \Modules\RestApi\Models\RestApiModel();
            $settings = $model->getChatWebhookSettings();
            if (!$settings) return;

            $taskTitle = $data['title'] ?? ($data['task_code'] ?? 'Task Operation');
            $msgText = "🔔 [{$projectTitle}] {$event}: {$taskTitle}";

            if (!empty($settings->slack_webhook_url)) {
                self::postJsonWebhook(trim($settings->slack_webhook_url), ['text' => $msgText]);
            }

            if (!empty($settings->discord_webhook_url)) {
                self::postJsonWebhook(trim($settings->discord_webhook_url), ['content' => $msgText]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Basit ve güvenli JSON HTTP POST isteği
     */
    public static function postJsonWebhook(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [
            'success' => ($code >= 200 && $code < 300),
            'code'    => $code,
            'response'=> $res,
            'error'   => $err ?: null
        ];
    }
}
