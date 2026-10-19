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

    /**
     * API Test Konsolu endpoint kataloğunu döner
     */
    public function getEndpointsCatalog(string $sampleProj = '01', string $sampleTask = '01-01'): array
    {
        return [
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'health',
                'method' => 'GET',
                'name' => __('ApiTester::btn_health'),
                'path' => 'health',
                'desc' => __('ApiTester::sec1_ep1'),
                'body' => null,
                'tip' => __('ApiTester::doc_tip_default')
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'auth_me',
                'method' => 'GET',
                'name' => __('ApiTester::token_label'),
                'path' => 'auth/me',
                'desc' => __('ApiTester::sec1_ep2'),
                'body' => null,
                'tip' => __('ApiTester::token_desc')
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'auth_regen',
                'method' => 'POST',
                'name' => __('ApiTester::btn_copy_token'),
                'path' => 'auth/regenerate-token',
                'desc' => __('ApiTester::sec1_ep3'),
                'body' => (object)[],
                'tip' => __('ApiTester::token_desc')
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_projects',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep1'),
                'path' => 'projects',
                'desc' => __('ApiTester::sec2_ep1'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_project_detail',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep3'),
                'path' => 'projects/' . $sampleProj,
                'desc' => __('ApiTester::sec2_ep3'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_project_tasks',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep4'),
                'path' => 'projects/' . $sampleProj . '/tasks',
                'desc' => __('ApiTester::sec2_ep4'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_project_docs',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep5'),
                'path' => 'projects/' . $sampleProj . '/docs',
                'desc' => __('ApiTester::sec2_ep5'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'post_project_docs',
                'method' => 'POST',
                'name' => __('ApiTester::sec2_ep6'),
                'path' => 'projects/' . $sampleProj . '/docs',
                'desc' => __('ApiTester::sec2_ep6'),
                'body' => [
                    'title' => 'Test Document',
                    'icon' => '📄',
                    'content' => '### Sample Content'
                ],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'test_project_webhook',
                'method' => 'POST',
                'name' => __('ApiTester::sec2_ep8'),
                'path' => 'projects/' . $sampleProj . '/webhook/test',
                'desc' => __('ApiTester::sec2_ep8'),
                'body' => (object)[],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'get_task_detail',
                'method' => 'GET',
                'name' => __('ApiTester::sec3_ep3'),
                'path' => 'tasks/' . $sampleTask,
                'desc' => __('ApiTester::sec3_ep3'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'create_task',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_ep2'),
                'path' => 'projects/' . $sampleProj . '/tasks',
                'desc' => __('ApiTester::sec3_ep2'),
                'body' => [
                    'title' => 'New Task',
                    'description' => 'Created via API Test Console',
                    'priority' => 'normal',
                    'tags' => 'test,api',
                    'time_estimate' => '2h'
                ],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'update_task',
                'method' => 'PUT',
                'name' => __('ApiTester::sec3_ep4'),
                'path' => 'tasks/' . $sampleTask,
                'desc' => __('ApiTester::sec3_ep4'),
                'body' => [
                    'title' => 'Updated Task Title',
                    'priority' => 'high'
                ],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'get_task_checklists',
                'method' => 'GET',
                'name' => __('ApiTester::sec4_ep1'),
                'path' => 'tasks/' . $sampleTask . '/checklists',
                'desc' => __('ApiTester::sec4_ep1'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'post_task_comment',
                'method' => 'POST',
                'name' => __('ApiTester::sec4_ep3'),
                'path' => 'tasks/' . $sampleTask . '/comments',
                'desc' => __('ApiTester::sec4_ep3'),
                'body' => [
                    'comment' => 'API Test Console: Progress reported successfully.'
                ],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_accept',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf1'),
                'path' => 'tasks/' . $sampleTask . '/workflow/accept',
                'desc' => __('ApiTester::sec3_wf1'),
                'body' => (object)[],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_submit',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf4'),
                'path' => 'tasks/' . $sampleTask . '/workflow/submit',
                'desc' => __('ApiTester::sec3_wf4'),
                'body' => [
                    'notes' => "1. What Was Done: Verified endpoints.\n2. Where to Test: /board\n3. How to Test: Completed checks.\n4. Test Evidence: HTTP 200 OK.",
                    'attachment_url' => 'https://github.com/BayKeskin/taskmanager'
                ],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_approve',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf5'),
                'path' => 'tasks/' . $sampleTask . '/workflow/approve',
                'desc' => __('ApiTester::sec3_wf5'),
                'body' => (object)[],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_reject',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf3'),
                'path' => 'tasks/' . $sampleTask . '/workflow/reject',
                'desc' => __('ApiTester::sec3_wf3'),
                'body' => [
                    'reason' => 'Revision requested via API test console'
                ],
                'tip' => __('ApiTester::endpoints_note')
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'get_users',
                'method' => 'GET',
                'name' => __('ApiTester::sec5_ep6'),
                'path' => 'users',
                'desc' => __('ApiTester::sec5_ep6'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note')
            ]
        ];
    }
}
