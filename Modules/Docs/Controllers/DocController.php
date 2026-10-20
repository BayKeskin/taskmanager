<?php
namespace Modules\Docs\Controllers;

use Core\BaseController;
use Modules\Docs\Models\DocModel;
use Modules\Projects\Models\ProjectModel;

class DocController extends BaseController
{
    private DocModel $docModel;
    private ProjectModel $projectModel;

    public function __construct()
    {
        parent::__construct();
        $this->docModel = new DocModel();
        $this->projectModel = new ProjectModel();
    }

    /**
     * Dökümanlar görünümü için verileri hazırlar
     */
    public function index(?int $activeProjectId = null, ?int $selectedDocId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $allProjects = $this->projectModel ? $this->projectModel->getProjects() : [];
        $docsList = $this->docModel->getDocsList($activeProjectId);

        $firstDoc = null;
        if (!empty($docsList)) {
            if ($selectedDocId) {
                foreach ($docsList as $docItem) {
                    $itemObj = (object)$docItem;
                    if ($itemObj->id == $selectedDocId) {
                        $firstDoc = $itemObj;
                        break;
                    }
                }
            }
            if (!$firstDoc) {
                $firstDoc = (object)$docsList[0];
            }
        }

        return [
            'currentUser'     => $currentUser,
            'isClient'        => $isClient,
            'activeProjectId' => $activeProjectId,
            'allProjects'     => $allProjects,
            'docsList'        => $docsList,
            'firstDoc'        => $firstDoc
        ];
    }

    /**
     * Tüm HMVC Modüllerini ve Kullanım Rehberini Hazırlar
     */
    public function modulesGuide(): array
    {
        $allModules = \Core\ModuleLoader::getAllAvailableModules();
        ksort($allModules);

        $metaFile = dirname(__DIR__) . '/modules_meta.json';
        $metadata = [];
        if (file_exists($metaFile)) {
            $decoded = json_decode((string)file_get_contents($metaFile), true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return [
            'modules'  => $allModules,
            'metadata' => $metadata
        ];
    }
}
