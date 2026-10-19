<?php
namespace Modules\VisualPins\Controllers;

use Core\BaseController;
use Core\EventManager;
use Modules\VisualPins\Models\PinModel;

/**
 * PinController - Görsel hata bildirimi (BugHerd) isteklerini işler
 */
class PinController extends BaseController
{
    private PinModel $pinModel;

    public function __construct()
    {
        parent::__construct();
        $this->pinModel = new PinModel();
    }

    /**
     * POST /api/v1/visual-feedback isteğini karşılar
     */
    public function handleFeedback(array $requestData, ?object $currentUser = null): array
    {
        $projectCode = trim($requestData['project_code'] ?? ($requestData['project'] ?? '01'));
        
        $projectModel = new \Modules\Projects\Models\ProjectModel();
        $projects = $projectModel->getProjects();
        $proj = null;
        foreach ($projects as $p) {
            if ($p->project_code === $projectCode || (string)$p->id === $projectCode) {
                $proj = $p;
                break;
            }
        }
        if (!$proj && !empty($projects)) {
            $proj = $projects[0];
        }

        if (!$proj) {
            return ['success' => false, 'error' => __('VisualPins::project_not_found'), 'status_code' => 404];
        }

        $title = trim($requestData['title'] ?? '');
        if ($title === '') {
            $title = __('VisualPins::pin_title');
        }
        $userDesc = trim($requestData['description'] ?? '');
        $selector = trim($requestData['selector'] ?? '');
        $url = trim($requestData['url'] ?? '');
        $viewport = trim($requestData['viewport'] ?? '');
        $userAgent = trim($requestData['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $coords = trim($requestData['coords'] ?? '');
        $priority = in_array($requestData['priority'] ?? '', ['low', 'normal', 'high', 'urgent']) ? $requestData['priority'] : 'normal';

        $fullDesc = "### " . __('VisualPins::pin_title') . " (In-House BugHerd)\n\n";
        if ($userDesc) {
            $fullDesc .= "#### 📝 " . __('VisualPins::pin_desc') . ":\n" . $userDesc . "\n\n";
        }
        $fullDesc .= "#### 🔍 " . __('VisualPins::tech_meta') . ":\n";
        if ($url) $fullDesc .= "- **" . __('VisualPins::page_url') . ":** " . $url . "\n";
        if ($selector) $fullDesc .= "- **" . __('VisualPins::selector') . ":** `" . $selector . "`\n";
        if ($coords) $fullDesc .= "- **" . __('VisualPins::coords') . ":** " . $coords . "\n";
        if ($viewport) $fullDesc .= "- **" . __('VisualPins::viewport') . ":** " . $viewport . "\n";
        if ($userAgent) $fullDesc .= "- **" . __('VisualPins::user_agent') . ":** `" . $userAgent . "`\n";

        $taskCode = $this->pinModel->getNextTaskCode($proj->project_code);
        $creatorId = $currentUser->id ?? 1;

        // Screenshot İşleme (Base64 -> JPG/PNG Dosyası)
        $screenshotBase64 = $requestData['screenshot'] ?? '';
        $screenshotUrl = null;
        $fileName = null;
        $ext = 'jpg';
        $fileSize = 0;

        if (!empty($screenshotBase64) && is_string($screenshotBase64)) {
            if (preg_match('/^data:image\/(\w+);base64,/', $screenshotBase64, $mType)) {
                $ext = strtolower($mType[1]);
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) $ext = 'jpg';
                $screenshotBase64 = substr($screenshotBase64, strpos($screenshotBase64, ',') + 1);
            }
            $decoded = base64_decode($screenshotBase64);
            if ($decoded !== false && strlen($decoded) > 100) {
                $pinsDir = dirname(__DIR__, 3) . '/Public/uploads/pins';
                if (!is_dir($pinsDir)) {
                    @mkdir($pinsDir, 0777, true);
                }
                $safeCode = preg_replace('/[^a-zA-Z0-9_\-]/', '', $taskCode);
                $fileName = 'pin_' . $safeCode . '_' . time() . '.' . $ext;
                $fullFilePath = $pinsDir . '/' . $fileName;
                if (@file_put_contents($fullFilePath, $decoded)) {
                    $screenshotUrl = '/taskmanager/Public/uploads/pins/' . $fileName;
                    $fileSize = strlen($decoded);
                    $fullDesc .= "\n#### 📸 " . __('VisualPins::screenshot_section') . ":\n";
                    $fullDesc .= "![" . $title . "](" . $screenshotUrl . ")\n";
                }
            }
        }

        $newTaskId = $this->pinModel->createPinTask($proj->id, $taskCode, $title, $fullDesc, $priority, $creatorId);
        $this->pinModel->assignUser($newTaskId, $creatorId);

        if ($screenshotUrl && $fileName) {
            $this->pinModel->attachScreenshot($newTaskId, $creatorId, $fileName, $screenshotUrl, 'image/' . $ext, $fileSize);
        }

        // Event / Hook fırlat: task.created
        EventManager::dispatch('task.created', [
            'task_id'        => $newTaskId,
            'task_code'      => $taskCode,
            'project_id'     => $proj->id,
            'title'          => $title,
            'status'         => 'assigned',
            'priority'       => $priority,
            'screenshot_url' => $screenshotUrl,
            'source'         => 'visual-pin'
        ]);

        return [
            'success' => true,
            'message' => __('VisualPins::success_created'),
            'data'    => [
                'task_id'        => $newTaskId,
                'task_code'      => $taskCode,
                'title'          => $title,
                'project_id'     => $proj->id,
                'project_title'  => $proj->title,
                'screenshot_url' => $screenshotUrl,
                'status'         => 'assigned'
            ]
        ];
    }
}
