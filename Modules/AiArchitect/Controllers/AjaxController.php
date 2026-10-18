<?php
namespace Modules\AiArchitect\Controllers;

use Core\BaseController;
use Modules\AiArchitect\Models\AiArchitectModel;
use Modules\AiArchitect\Services\TaskDecomposerService;

class AjaxController extends BaseController
{
    private AiArchitectModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AiArchitectModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function decomposeBrief(): void
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        if ($this->user->role === 'client') {
            $this->respond(false, __('AiArchitect::unauthorized'));
        }

        $brief = trim($_POST['brief'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 1);

        $docInfo = ['text' => '', 'extracted_imgs' => [], 'original_doc' => null];
        if (isset($_FILES['brief_file'])) {
            $fErr = $_FILES['brief_file']['error'];
            if ($fErr !== UPLOAD_ERR_OK && $fErr !== UPLOAD_ERR_NO_FILE) {
                $maxSize = ini_get('upload_max_filesize') ?: '64M';
                $errMsg = match ($fErr) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('AiArchitect::err_file_size', ['max' => $maxSize]),
                    UPLOAD_ERR_PARTIAL   => __('AiArchitect::err_file_partial'),
                    UPLOAD_ERR_NO_TMP_DIR=> __('AiArchitect::err_file_no_tmp'),
                    UPLOAD_ERR_CANT_WRITE=> __('AiArchitect::err_file_cant_write'),
                    default              => __('AiArchitect::err_file_upload', ['code' => $fErr])
                };
                $this->respond(false, $errMsg);
            }
            if ($fErr === UPLOAD_ERR_OK) {
                $docInfo = TaskDecomposerService::extractContentAndImagesFromDocument($_FILES['brief_file']);
            }
        }

        $res = TaskDecomposerService::decomposeBriefWithAi($brief, $projectId, $docInfo);
        if (!empty($res['success'])) {
            $this->respond(true, __('AiArchitect::decompose_success'), $res);
        } else {
            $this->respond(false, $res['error'] ?? __('AiArchitect::decompose_error'));
        }
    }

    public function bulkCreateTasks(): void
    {
        if ($this->user->role === 'client') {
            $this->respond(false, __('AiArchitect::unauthorized'));
        }

        $projectId = (int)($_POST['project_id'] ?? 1);
        $treeJson  = $_POST['tree_json'] ?? '';
        $imgsJson  = $_POST['extracted_imgs_json'] ?? '[]';
        $origDocJson = $_POST['original_doc_json'] ?? 'null';

        $epics         = json_decode($treeJson, true);
        $extractedImgs = json_decode($imgsJson, true) ?: [];
        $originalDoc   = json_decode($origDocJson, true) ?: null;

        if (empty($epics) || !is_array($epics)) {
            $this->respond(false, __('AiArchitect::no_valid_tree'));
        }

        $createdEpicsCount = 0;
        $createdSubtasksCount = 0;
        $defaultDeadline = date('Y-m-d', strtotime('+7 days'));

        $useTransaction = false;
        try {
            $this->model->beginTransaction();
            $useTransaction = true;
        } catch (\Throwable $e) {}

        try {
            foreach ($epics as $epic) {
                $epicTitle = trim($epic['title'] ?? '');
                if (empty($epicTitle)) continue;

                $epicDesc     = trim($epic['description'] ?? '');
                $epicPriority = in_array($epic['priority'] ?? '', ['urgent', 'high', 'normal', 'low']) ? $epic['priority'] : 'high';
                $execMode     = ($epic['execution_mode'] ?? 'auto') === 'gated' ? 'gated' : 'auto';
                $isGatedVal   = ($execMode === 'gated') ? 1 : 0;

                $epicTags = ($execMode === 'gated') ? 'Epic, chain_gated' : 'Epic, chain_auto';
                if ($execMode === 'gated') {
                    $epicDesc = __('AiArchitect::gated_prefix_note') . "\n\n" . $epicDesc;
                }

                if (!empty($extractedImgs)) {
                    $epicDesc .= "\n\n---\n" . __('AiArchitect::extracted_images_heading') . "\n";
                    foreach ($extractedImgs as $imgItem) {
                        $imgFallback = __('AiArchitect::image_fallback');
                        $epicDesc .= "- ![" . ($imgItem['file_name'] ?? $imgFallback) . "](" . ($imgItem['file_path'] ?? '') . ")\n";
                    }
                }

                $epicCode = $this->model->getNextTaskCode();

                $epicId = $this->model->insertEpicTask([
                    'project_id'  => $projectId,
                    'task_code'   => $epicCode,
                    'title'       => $epicTitle,
                    'description' => $epicDesc,
                    'tags'        => $epicTags,
                    'is_gated'    => $isGatedVal,
                    'priority'    => $epicPriority,
                    'deadline'    => $defaultDeadline,
                    'created_by'  => $this->user->id,
                    'assigned_to' => $this->user->id
                ]);

                if ($epicId > 0) {
                    $createdEpicsCount++;
                    $this->model->assignUserToTask($epicId, $this->user->id);

                    if ($originalDoc && !empty($originalDoc['file_path'])) {
                        $this->model->attachFileToTask($epicId, $this->user->id, $originalDoc);
                    }
                    foreach ($extractedImgs as $imgItem) {
                        if (!empty($imgItem['file_path'])) {
                            $this->model->attachFileToTask($epicId, $this->user->id, $imgItem);
                        }
                    }

                    $subtasks = $epic['subtasks'] ?? [];
                    $subIndex = 0;
                    foreach ($subtasks as $sub) {
                        $subTitle = trim($sub['title'] ?? '');
                        if (empty($subTitle)) continue;

                        $subIndex++;
                        $subCode     = $epicCode . '-' . $subIndex;
                        $subDesc     = trim($sub['description'] ?? $subTitle);
                        $subCategory = trim($sub['category'] ?? 'coding');
                        $subPriority = in_array($sub['priority'] ?? '', ['urgent', 'high', 'normal', 'low']) ? $sub['priority'] : 'normal';
                        $subEstimate = trim($sub['time_estimate'] ?? '2h');
                        $subAssignee = (int)($sub['assigned_to'] ?? $this->user->id) ?: (int)$this->user->id;
                        $useAi       = !empty($sub['is_ai']);

                        $subTagsArr = ['Subtask', $subCategory];
                        if ($useAi) {
                            $subTagsArr[] = 'ai';
                        }
                        $subTagsStr = implode(', ', array_unique(array_filter($subTagsArr)));

                        $newSubId = $this->model->insertSubTask([
                            'project_id'     => $projectId,
                            'parent_task_id' => $epicId,
                            'task_code'      => $subCode,
                            'title'          => $subTitle,
                            'description'    => $subDesc,
                            'priority'       => $subPriority,
                            'deadline'       => $defaultDeadline,
                            'created_by'     => $this->user->id,
                            'assigned_to'    => $subAssignee,
                            'tags'           => $subTagsStr,
                            'time_estimate'  => $subEstimate
                        ]);

                        if ($newSubId > 0) {
                            $createdSubtasksCount++;
                            $this->model->assignUserToTask($newSubId, $subAssignee);

                            foreach ($extractedImgs as $imgItem) {
                                if (!empty($imgItem['file_path'])) {
                                    $this->model->attachFileToTask($newSubId, $this->user->id, $imgItem);
                                }
                            }
                        }
                    }

                    \Modules\SSE\Services\SseBroadcaster::broadcast('task_created', $projectId, $epicId, (int)$this->user->id, [
                        'task_id'   => $epicId,
                        'task_code' => $epicCode,
                        'title'     => $epicTitle,
                        'status'    => 'assigned',
                        'user_name' => $this->user->name
                    ]);
                }
            }

            if ($useTransaction) {
                $this->model->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $this->model->rollBack();
            }
            $this->respond(false, __('AiArchitect::bulk_create_error', ['error' => $e->getMessage()]));
        }

        $this->respond(true, __('AiArchitect::bulk_create_success', [
            'epics'    => $createdEpicsCount,
            'subtasks' => $createdSubtasksCount
        ]), [
            'epics_count'    => $createdEpicsCount,
            'subtasks_count' => $createdSubtasksCount
        ]);
    }
}
