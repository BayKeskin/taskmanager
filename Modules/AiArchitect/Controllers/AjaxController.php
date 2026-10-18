<?php
namespace Modules\AiArchitect\Controllers;

use Core\BaseController;

class AjaxController extends BaseController
{
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
            $this->respond(false, 'Yetkisiz erişim.');
        }

        $brief = trim($_POST['brief'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 1);

        $docInfo = ['text' => '', 'extracted_imgs' => [], 'original_doc' => null];
        if (isset($_FILES['brief_file'])) {
            $fErr = $_FILES['brief_file']['error'];
            if ($fErr !== UPLOAD_ERR_OK && $fErr !== UPLOAD_ERR_NO_FILE) {
                $maxSize = ini_get('upload_max_filesize') ?: '64M';
                $errMsg = match ($fErr) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Yüklenen döküman boyutu sunucu limitini aşıyor (Maks: {$maxSize}).",
                    UPLOAD_ERR_PARTIAL   => 'Dosya bağlantı kesintisi nedeniyle sadece kısmen yüklenebildi.',
                    UPLOAD_ERR_NO_TMP_DIR=> 'Sunucuda geçici dosya yükleme dizini bulunamadı.',
                    UPLOAD_ERR_CANT_WRITE=> 'Dosya sunucu diskine yazılamadı.',
                    default              => "Dosya yüklenirken hata oluştu (Kod: {$fErr})."
                };
                $this->respond(false, $errMsg);
            }
            if ($fErr === UPLOAD_ERR_OK) {
                $docInfo = \Modules\AiArchitect\Services\TaskDecomposerService::extractContentAndImagesFromDocument($_FILES['brief_file']);
            }
        }

        $res = \Modules\AiArchitect\Services\TaskDecomposerService::decomposeBriefWithAi($brief, $projectId, $docInfo);
        if (!empty($res['success'])) {
            $this->respond(true, 'Proje brifi ve döküman başarıyla modüllere, uygulama adımlarına ve görsellere ayrıştırıldı!', $res);
        } else {
            $this->respond(false, $res['error'] ?? 'Ayrıştırma sırasında hata oluştu.');
        }
    }

    public function bulkCreateTasks(): void
    {
        if ($this->user->role === 'client') {
            $this->respond(false, 'Yetkisiz erişim.');
        }

        $projectId = (int)($_POST['project_id'] ?? 1);
        $treeJson  = $_POST['tree_json'] ?? '';
        $imgsJson  = $_POST['extracted_imgs_json'] ?? '[]';
        $origDocJson = $_POST['original_doc_json'] ?? 'null';

        $epics         = json_decode($treeJson, true);
        $extractedImgs = json_decode($imgsJson, true) ?: [];
        $originalDoc   = json_decode($origDocJson, true) ?: null;

        if (empty($epics) || !is_array($epics)) {
            $this->respond(false, 'Kaydedilecek geçerli bir görev ağacı bulunamadı.');
        }

        $createdEpicsCount = 0;
        $createdSubtasksCount = 0;
        $defaultDeadline = date('Y-m-d', strtotime('+7 days'));

        $useTransaction = false;
        if (!empty($this->db->connect) && method_exists($this->db->connect, 'beginTransaction')) {
            try {
                $this->db->connect->beginTransaction();
                $useTransaction = true;
            } catch (\Throwable $e) {}
        }

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
                    $epicDesc = "[ONAY_KAPILI] (Bu modüldeki alt görevler yönetici onayıyla adım adım ilerler)\n\n" . $epicDesc;
                }

                if (!empty($extractedImgs)) {
                    $epicDesc .= "\n\n---\n📎 **Dökümandan Çıkarılan Referans Görseller:**\n";
                    foreach ($extractedImgs as $imgItem) {
                        $epicDesc .= "- ![" . ($imgItem['file_name'] ?? 'Görsel') . "](" . ($imgItem['file_path'] ?? '') . ")\n";
                    }
                }

                $lastId = (int)$this->db->single_query("SELECT MAX(id) FROM tasks") ?: 0;
                $epicCode = 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

                $insEpic = $this->db->insert("tasks", [
                    'project_id', 'task_code', 'title', 'description', 'tags', 'is_gated', 'priority',
                    'status', 'deadline', 'created_by', 'assigned_to'
                ], [
                    $projectId, $epicCode, $epicTitle, $epicDesc, $epicTags, $isGatedVal, $epicPriority,
                    'assigned', $defaultDeadline, $this->user->id, $this->user->id
                ]);

                if ($insEpic) {
                    $epicId = (int)$this->db->last_insert_id();
                    $createdEpicsCount++;
                    $this->db->exec_query("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)", [$epicId, $this->user->id]);

                    if ($originalDoc && !empty($originalDoc['file_path'])) {
                        $this->db->exec_query("
                            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ", [$epicId, $this->user->id, $originalDoc['file_name'], $originalDoc['file_path'], $originalDoc['file_type'], (int)$originalDoc['file_size']]);
                    }
                    foreach ($extractedImgs as $imgItem) {
                        if (!empty($imgItem['file_path'])) {
                            $this->db->exec_query("
                                INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ", [$epicId, $this->user->id, $imgItem['file_name'], $imgItem['file_path'], $imgItem['file_type'], (int)$imgItem['file_size']]);
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

                        $insSub = $this->db->insert("tasks", [
                            'project_id', 'parent_task_id', 'task_code', 'title', 'description', 'priority',
                            'status', 'deadline', 'created_by', 'assigned_to', 'tags', 'time_estimate', 'time_spent'
                        ], [
                            $projectId, $epicId, $subCode, $subTitle, $subDesc, $subPriority,
                            'assigned', $defaultDeadline, $this->user->id, $subAssignee, $subTagsStr, $subEstimate, '0m'
                        ]);

                        if ($insSub) {
                            $newSubId = (int)$this->db->last_insert_id();
                            $createdSubtasksCount++;
                            $this->db->exec_query("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)", [$newSubId, $subAssignee]);

                            foreach ($extractedImgs as $imgItem) {
                                if (!empty($imgItem['file_path'])) {
                                    $this->db->exec_query("
                                        INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                                    ", [$newSubId, $this->user->id, $imgItem['file_name'], $imgItem['file_path'], $imgItem['file_type'], (int)$imgItem['file_size']]);
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
                $this->db->connect->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $this->db->connect->rollBack();
            }
            $this->respond(false, 'Toplu kayıt sırasında hata oluştu: ' . $e->getMessage());
        }

        $this->respond(true, "✅ {$createdEpicsCount} Ana Modül ve {$createdSubtasksCount} Detaylı Alt Görev başarıyla projeye işlendi!", [
            'epics_count'    => $createdEpicsCount,
            'subtasks_count' => $createdSubtasksCount
        ]);
    }
}
