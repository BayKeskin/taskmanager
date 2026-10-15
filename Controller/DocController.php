<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/DocModel.php';
require_once __DIR__ . '/../Model/ProjectModel.php';

class DocController extends Controller
{
    private DocModel $docModel;
    private ProjectModel $projectModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->docModel = new DocModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
    }

    /**
     * Dökümanlar görünümü için verileri hazırlar
     */
    public function index(?int $activeProjectId = null, ?int $selectedDocId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $allProjects = $this->projectModel->getProjects();
        $docsList = $this->docModel->getDocsList($activeProjectId);

        $firstDoc = null;
        if (!empty($docsList)) {
            if ($selectedDocId) {
                foreach ($docsList as $docItem) {
                    if ($docItem->id == $selectedDocId) {
                        $firstDoc = $docItem;
                        break;
                    }
                }
            }
            if (!$firstDoc) {
                $firstDoc = $docsList[0];
            }
        }

        return [
            'currentUser'     => $currentUser,
            'activeProjectId' => $activeProjectId,
            'allProjects'     => $allProjects,
            'docsList'        => $docsList,
            'firstDoc'        => $firstDoc
        ];
    }
}
