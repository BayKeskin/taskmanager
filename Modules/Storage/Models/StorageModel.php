<?php
namespace Modules\Storage\Models;

use Core\BaseModel;

class StorageModel extends BaseModel
{
    /**
     * Görev eklerini ve yüklenen dosyaları listeler
     */
    public function getTaskAttachments(int $taskId): array
    {
        $sql = "SELECT * FROM task_attachments WHERE task_id = ? ORDER BY id DESC";
        return $this->query($sql, [$taskId]);
    }
}
