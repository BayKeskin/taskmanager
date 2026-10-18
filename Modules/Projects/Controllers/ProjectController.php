<?php
namespace Modules\Projects\Controllers;

use Core\BaseController;
use Modules\Projects\Models\ProjectModel;

class ProjectController extends BaseController
{
    private ProjectModel $projectModel;

    public function __construct()
    {
        parent::__construct();
        $this->projectModel = new ProjectModel();
    }

    /**
     * Projeleri listeler
     */
    public function index(): array
    {
        $projects = $this->projectModel->getProjects();
        $stats = $this->projectModel->getProjectStats();

        return [
            'projects' => $projects,
            'stats'    => $stats
        ];
    }

    /**
     * Proje detayını çeker
     */
    public function getProject(int $id): ?object
    {
        return $this->projectModel->getProjectById($id);
    }
}
