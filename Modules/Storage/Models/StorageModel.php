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

    /**
     * Depolama yapılandırma ve kısıtlama ayarlarını getirir
     */
    public function getStorageSettings(): ?array
    {
        $sql = "SELECT max_file_upload_mb, allowed_file_types FROM settings WHERE id = 1";
        return $this->row($sql);
    }
}
