<?php
namespace Modules\AiArchitect\Controllers;

use Core\BaseController;
use Modules\AiArchitect\Services\GeminiClient;

class AiArchitectController extends BaseController
{
    private GeminiClient $geminiClient;

    public function __construct()
    {
        parent::__construct();
        $this->geminiClient = new GeminiClient();
    }

    /**
     * AJAX endpoint: refine_task_specification
     */
    public function refineSpecification(): void
    {
        $projectId = (int)($_POST['project_id'] ?? 1);
        $instruction = trim($_POST['instruction'] ?? '');

        if (empty($instruction)) {
            $this->json([
                'status'  => 'error',
                'success' => false,
                'message' => __('AiArchitect::empty_instruction', 'Lütfen ne yapmak istediğinize dair kısa bir açıklama veya yönerge girin.')
            ]);
            return;
        }

        $refImages = [];
        $refFiles = [];

        // 1. Eklenen / Yapıştırılan Görseller
        if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
            $rawImgs = $_FILES['images'];
            for ($i = 0; $i < count($rawImgs['name']); $i++) {
                if (!empty($rawImgs['tmp_name'][$i]) && $rawImgs['error'][$i] === UPLOAD_ERR_OK) {
                    $mime = $rawImgs['type'][$i] ?? 'image/jpeg';
                    $content = @file_get_contents($rawImgs['tmp_name'][$i]);
                    if ($content !== false) {
                        $refImages[] = [
                            'name'   => $rawImgs['name'][$i],
                            'mime'   => $mime,
                            'base64' => base64_encode($content)
                        ];
                    }
                }
            }
        } elseif (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $content = @file_get_contents($_FILES['image']['tmp_name']);
            if ($content !== false) {
                $refImages[] = [
                    'name'   => $_FILES['image']['name'],
                    'mime'   => $_FILES['image']['type'] ?? 'image/jpeg',
                    'base64' => base64_encode($content)
                ];
            }
        }

        // 2. Eklenen Kod Dosyaları
        if (!empty($_FILES['code_files']) && is_array($_FILES['code_files']['name'])) {
            $rawCode = $_FILES['code_files'];
            for ($i = 0; $i < count($rawCode['name']); $i++) {
                if (!empty($rawCode['tmp_name'][$i]) && $rawCode['error'][$i] === UPLOAD_ERR_OK) {
                    $content = @file_get_contents($rawCode['tmp_name'][$i]);
                    if ($content !== false) {
                        $refFiles[] = [
                            'name'    => $rawCode['name'][$i],
                            'content' => $content
                        ];
                    }
                }
            }
        } elseif (!empty($_FILES['code_file']['tmp_name']) && $_FILES['code_file']['error'] === UPLOAD_ERR_OK) {
            $content = @file_get_contents($_FILES['code_file']['tmp_name']);
            if ($content !== false) {
                $refFiles[] = [
                    'name'    => $_FILES['code_file']['name'],
                    'content' => $content
                ];
            }
        }

        $result = $this->geminiClient->refineTaskSpecification($projectId, $instruction, $refImages, $refFiles);

        if (!empty($result['success']) && !empty($result['specification'])) {
            $this->json([
                'status'        => 'success',
                'success'       => true,
                'message'       => __('AiArchitect::spec_generated_success', 'Şartname başarıyla üretildi!'),
                'specification' => $result['specification'],
                'engine'        => $result['engine'] ?? 'ai'
            ]);
        } else {
            $this->json([
                'status'  => 'error',
                'success' => false,
                'message' => $result['error'] ?? __('AiArchitect::spec_generate_failed', 'Şartname üretilemedi.')
            ]);
        }
    }
}
