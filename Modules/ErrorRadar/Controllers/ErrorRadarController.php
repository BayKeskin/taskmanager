<?php
namespace Modules\ErrorRadar\Controllers;

use Core\BaseController;
use Core\EventManager;
use Modules\ErrorRadar\Models\ErrorRadarModel;

/**
 * ErrorRadarController - Otonom Hata Raporlama ve Görev Açma Kontrolörü
 */
class ErrorRadarController extends BaseController
{
    private ErrorRadarModel $radarModel;

    public function __construct()
    {
        parent::__construct();
        $this->radarModel = new ErrorRadarModel();
    }

    /**
     * AJAX & API üzerinden gelen hata paketini işler.
     */
    public function reportError(?array $inputData = null): void
    {
        $payload = $inputData;
        if ($payload === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = array_merge($_POST, $decoded);
            } else {
                $payload = $_POST;
            }
        }

        $res = $this->processReport($payload);
        $this->json($res, $res['status_code'] ?? ($res['success'] ? 200 : 400));
    }

    /**
     * Hata raporunu ayrıştırır, tekilleştirir ve görevi açar.
     */
    public function processReport(array $payload): array
    {
        $errorType = trim($payload['type'] ?? 'js_error');
        $errorMessage = trim($payload['message'] ?? '');
        $url = trim($payload['url'] ?? '');
        $endpoint = trim($payload['endpoint'] ?? '');
        $statusCode = isset($payload['status']) ? (int)$payload['status'] : null;
        $fileLine = trim($payload['location'] ?? ($payload['file'] ?? ''));
        $stackTrace = trim($payload['stack'] ?? '');
        $responseBody = trim($payload['response'] ?? '');
        $projectIdent = $payload['project_id'] ?? ($payload['project_code'] ?? '01');

        if ($errorMessage === '' && $responseBody === '') {
            return [
                'success'     => false,
                'status_code' => 400,
                'message'     => __('ErrorRadar::invalid_error_payload')
            ];
        }

        // Sonsuz döngü ve kendi kendine hata raporlama koruması
        if (
            str_contains($url, 'error-radar') ||
            str_contains($endpoint, 'error-radar') ||
            str_contains($errorMessage, 'error-radar')
        ) {
            return [
                'success'     => true,
                'ignored'     => true,
                'status_code' => 200,
                'message'     => __('ErrorRadar::duplicate_error_ignored')
            ];
        }

        $project = $this->radarModel->findProject($projectIdent);
        if (!$project) {
            return [
                'success'     => false,
                'status_code' => 404,
                'message'     => __('ErrorRadar::project_not_found')
            ];
        }

        $projectId = (int)$project->id;
        $projectCode = $project->project_code;

        // Benzersiz hata parmak izi (fingerprint) oluştur: tip + mesaj + dosya/satır veya endpoint
        $cleanMessageForHash = preg_replace('/\b[0-9a-f]{8,}\b|\b\d{5,}\b/i', '', $errorMessage);
        $fingerprintRaw = $errorType . '|' . $cleanMessageForHash . '|' . $fileLine . '|' . $endpoint;
        $fingerprint = hash('sha256', $fingerprintRaw);

        // Tekilleştirme Kontrolü: Açık olan aynı hata var mı?
        if ($this->radarModel->hasActiveErrorTask($projectId, $fingerprint)) {
            return [
                'success'      => true,
                'duplicate'    => true,
                'fingerprint'  => $fingerprint,
                'status_code'  => 200,
                'message'      => __('ErrorRadar::duplicate_error_ignored')
            ];
        }

        // Görev Başlığı
        $typeLabel = match ($errorType) {
            'network_error'       => __('ErrorRadar::network_error'),
            'php_xdebug'          => __('ErrorRadar::php_xdebug_error'),
            'broken_i18n'         => __('ErrorRadar::broken_i18n_key'),
            'unhandled_rejection' => __('ErrorRadar::unhandled_rejection'),
            'static_asset'        => __('ErrorRadar::static_asset_error'),
            default               => __('ErrorRadar::js_console_error'),
        };

        $shortMsg = mb_substr(preg_replace('/\s+/', ' ', strip_tags($errorMessage)), 0, 70);
        $taskTitle = __('ErrorRadar::task_title_prefix') . ' ' . $typeLabel . ': ' . ($shortMsg ?: $fileLine);

        // Görev Açıklaması Markdown
        $desc = "### 🚨 " . __('ErrorRadar::task_title_prefix') . " " . $typeLabel . "\n\n";
        $desc .= "> **" . __('ErrorRadar::error_source_url') . ":** " . ($url ?: '-') . "\n";
        $desc .= "> **" . __('ErrorRadar::error_type') . ":** `" . $errorType . "`\n";
        if ($statusCode) {
            $desc .= "> **" . __('ErrorRadar::error_http_status') . ":** `" . $statusCode . "`\n";
        }
        if ($endpoint) {
            $desc .= "> **" . __('ErrorRadar::error_request_endpoint') . ":** `" . $endpoint . "`\n";
        }
        if ($fileLine) {
            $desc .= "> **" . __('ErrorRadar::error_location') . ":** `" . $fileLine . "`\n";
        }
        $desc .= "> **" . __('ErrorRadar::error_fingerprint') . ":** `" . $fingerprint . "`\n\n";

        $desc .= "#### 📝 " . __('ErrorRadar::error_message') . ":\n";
        $desc .= "```text\n" . $errorMessage . "\n```\n\n";

        if (!empty($stackTrace)) {
            $desc .= "#### 🔍 " . __('ErrorRadar::error_stack_trace') . ":\n";
            $desc .= "```text\n" . mb_substr($stackTrace, 0, 2000) . "\n```\n\n";
        }

        if (!empty($responseBody)) {
            $cleanResponse = strip_tags($responseBody);
            $cleanResponse = preg_replace("/\n{3,}/", "\n\n", $cleanResponse);
            $desc .= "#### 🌐 " . __('ErrorRadar::error_response_body') . ":\n";
            $desc .= "```html\n" . mb_substr($cleanResponse, 0, 2000) . "\n```\n\n";
        }

        $desc .= "#### 🎯 " . __('ErrorRadar::error_reproduction_steps') . ":\n";
        $desc .= __('ErrorRadar::repro_step_1') . "`" . ($url ?: 'Active Page') . "`\n";
        if ($endpoint) {
            $desc .= __('ErrorRadar::repro_step_2') . "`" . $endpoint . "`\n";
        }
        $desc .= __('ErrorRadar::repro_step_3') . "`" . $errorType . "`\n";

        $taskCode = $this->radarModel->getNextTaskCode($projectCode);
        $creatorId = $this->user->id ?? 1;

        $taskId = $this->radarModel->createRadarTask($projectId, $taskCode, $taskTitle, $desc, $creatorId);
        $this->radarModel->assignUser($taskId, $creatorId);

        // SSE & Webhook tetiklemesi
        if (class_exists('Modules\SSE\Services\SseBroadcaster')) {
            \Modules\SSE\Services\SseBroadcaster::broadcast('task_created', $projectId, $taskId, $creatorId, [
                'task_code' => $taskCode,
                'title'     => $taskTitle,
                'status'    => 'assigned'
            ]);
        }

        EventManager::dispatch('task.created', [
            'task_id'     => $taskId,
            'task_code'   => $taskCode,
            'project_id'  => $projectId,
            'title'       => $taskTitle,
            'status'      => 'assigned',
            'priority'    => 'high',
            'tags'        => 'bug,auto-radar',
            'fingerprint' => $fingerprint
        ]);

        return [
            'success'     => true,
            'status_code' => 201,
            'message'     => __('ErrorRadar::task_created_success'),
            'data'        => [
                'task_id'     => $taskId,
                'task_code'   => $taskCode,
                'title'       => $taskTitle,
                'project_id'  => $projectId,
                'fingerprint' => $fingerprint
            ]
        ];
    }
}
