<?php
namespace Modules\Storage\Controllers;

use Core\BaseController;
use Modules\Storage\Services\StorageService;

class StorageController extends BaseController
{
    public function upload(): void
    {
        if (empty($_FILES['file']) && empty($_FILES['attachment'])) {
            $this->json(['error' => __('Storage::upload_error', 'Yüklenecek dosya bulunamadı.')], 400);
            return;
        }

        $f = $_FILES['file'] ?? $_FILES['attachment'];
        $subfolder = trim($_POST['subfolder'] ?? 'tasks');
        $res = StorageService::upload($f, $subfolder);

        if ($res['success']) {
            $this->json($res, 200);
        } else {
            $this->json($res, 400);
        }
    }
}
