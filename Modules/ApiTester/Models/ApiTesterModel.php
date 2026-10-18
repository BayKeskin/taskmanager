<?php
namespace Modules\ApiTester\Models;

use Core\BaseModel;

class ApiTesterModel extends BaseModel
{
    /**
     * API Test konsolunda ön doldurma için ilk aktif projeyi getirir
     */
    public function getFirstActiveProjectCode(): string
    {
        $sql = "SELECT project_code FROM projects WHERE status = 'active' ORDER BY id ASC LIMIT 1";
        $row = $this->first($sql);
        return (!empty($row->project_code)) ? (string)$row->project_code : '01';
    }

    /**
     * API Test konsolunda ön doldurma için son görevi getirir
     */
    public function getLatestTaskCode(): string
    {
        $sql = "SELECT task_code FROM tasks ORDER BY id DESC LIMIT 1";
        $row = $this->first($sql);
        return (!empty($row->task_code)) ? (string)$row->task_code : 'TSK-101';
    }
}
