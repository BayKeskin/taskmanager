<?php
namespace Modules\ApiTester\Models;

use Core\BaseModel;

class ApiTesterModel extends BaseModel
{
    /**
     * API Test konsolunda on doldurma icin ilk aktif projeyi getirir
     */
    public function getFirstActiveProjectCode(): string
    {
        $sql = "SELECT project_code FROM projects WHERE status = 'active' ORDER BY id ASC LIMIT 1";
        $row = $this->first($sql);
        return (!empty($row->project_code)) ? (string)$row->project_code : '01';
    }

    /**
     * API Test konsolunda on doldurma icin son gorevi getirir
     */
    public function getLatestTaskCode(): string
    {
        $sql = "SELECT task_code FROM tasks ORDER BY id DESC LIMIT 1";
        $row = $this->first($sql);
        return (!empty($row->task_code)) ? (string)$row->task_code : 'TSK-101';
    }

    /**
     * API Test Konsolu endpoint katalogunu doner
     * Tum REST API uc noktalari, alabilecegi parametreler, ornek govde ve beklenen donusler
     */
    public function getEndpointsCatalog(string $sampleProj = '01', string $sampleTask = '01-01'): array
    {
        return [
            // ==========================================
            // Kategori 1: Authentication & User Profile
            // ==========================================
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'health',
                'method' => 'GET',
                'name' => __('ApiTester::btn_health'),
                'path' => 'health',
                'desc' => __('ApiTester::sec1_ep1'),
                'body' => null,
                'tip' => __('ApiTester::doc_tip_default'),
                'expected_status' => 200,
                'params' => [
                    'query' => [
                        'lang' => 'tr | en | de (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'data' => [
                        'api_name' => 'TaskManager REST API',
                        'version' => '1.0.0',
                        'status' => 'online',
                        'docs_url' => 'http://localhost/taskmanager/api-docs',
                        'authenticated' => true,
                        'user' => [
                            'id' => 1,
                            'name' => 'Talha Keskin',
                            'email' => 'iletisimtktalha@gmail.com',
                            'role' => 'admin'
                        ],
                        'timestamp' => '2026-10-19T22:00:00+03:00'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'auth_login',
                'method' => 'POST',
                'name' => 'POST /auth/login',
                'path' => 'auth/login',
                'desc' => __('ApiTester::sec1_ep1'),
                'body' => [
                    'email' => 'iletisimtktalha@gmail.com',
                    'password' => '145236'
                ],
                'tip' => __('ApiTester::sec1_ep1'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'email' => 'string (required)',
                        'password' => 'string (required)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Login successful',
                    'data' => [
                        'token' => 'tm_live_a1b2c3d4e5f6g7h8i9j0',
                        'user' => [
                            'id' => 1,
                            'name' => 'Talha Keskin',
                            'email' => 'iletisimtktalha@gmail.com',
                            'role' => 'admin',
                            'title' => 'Lead Software Architect',
                            'avatar_color' => '#7B68EE'
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'auth_me',
                'method' => 'GET',
                'name' => __('ApiTester::token_label'),
                'path' => 'auth/me',
                'desc' => __('ApiTester::sec1_ep2'),
                'body' => null,
                'tip' => __('ApiTester::token_desc'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <api_token> (required)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'data' => [
                        'user' => [
                            'id' => 1,
                            'name' => 'Talha Keskin',
                            'email' => 'iletisimtktalha@gmail.com',
                            'role' => 'admin',
                            'title' => 'Lead Software Architect',
                            'avatar_color' => '#7B68EE',
                            'api_token' => 'tm_live_...',
                            'active_tasks' => 4
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'auth_regen',
                'method' => 'POST',
                'name' => __('ApiTester::btn_copy_token'),
                'path' => 'auth/regenerate-token',
                'desc' => __('ApiTester::sec1_ep3'),
                'body' => (object)[],
                'tip' => __('ApiTester::token_desc'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <current_token> (required)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Token regenerated successfully',
                    'data' => [
                        'api_token' => 'tm_live_new_token_string'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'get_profile',
                'method' => 'GET',
                'name' => 'GET /profile',
                'path' => 'profile',
                'desc' => __('ApiTester::sec1_ep2'),
                'body' => null,
                'tip' => __('ApiTester::sec1_ep2'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <token> (required)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'data' => [
                        'id' => 1,
                        'name' => 'Talha Keskin',
                        'email' => 'iletisimtktalha@gmail.com',
                        'role' => 'admin',
                        'title' => 'Lead Software Architect',
                        'avatar_color' => '#7B68EE'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec1_title'),
                'id' => 'update_profile',
                'method' => 'PUT',
                'name' => 'PUT /profile',
                'path' => 'profile',
                'desc' => __('ApiTester::sec1_ep3'),
                'body' => [
                    'name' => 'Talha Keskin',
                    'title' => 'Lead Full-Stack Architect',
                    'avatar_color' => '#7B68EE'
                ],
                'tip' => __('ApiTester::sec1_ep3'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'name' => 'string (optional)',
                        'email' => 'string (optional)',
                        'title' => 'string (optional)',
                        'avatar_color' => 'hex color (#7B68EE)',
                        'current_password' => 'string (required for password change)',
                        'new_password' => 'string (min 6 characters)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Profile updated successfully',
                    'data' => [
                        'id' => 1,
                        'name' => 'Talha Keskin',
                        'email' => 'iletisimtktalha@gmail.com',
                        'title' => 'Lead Full-Stack Architect'
                    ]
                ]
            ],

            // ==========================================
            // Kategori 2: Projects (Spaces)
            // ==========================================
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_projects',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep1'),
                'path' => 'projects',
                'desc' => __('ApiTester::sec2_ep1'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'query' => [
                        'status' => 'active | completed | archived | all (default: active)',
                        'lang' => 'tr | en | de'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'meta' => ['total' => 2],
                    'data' => [
                        [
                            'id' => 1,
                            'project_code' => '01',
                            'title' => 'Task Manager',
                            'status' => 'active',
                            'task_count' => 12
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'create_project',
                'method' => 'POST',
                'name' => __('ApiTester::sec2_ep2'),
                'path' => 'projects',
                'desc' => __('ApiTester::sec2_ep2'),
                'body' => [
                    'title' => 'E-Commerce Platform',
                    'project_code' => 'ETC',
                    'description' => 'Modern B2B & B2C Platform',
                    'color' => '#10B981',
                    'icon' => 'portfolio'
                ],
                'tip' => __('ApiTester::sec2_ep2'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'title' => 'string (required)',
                        'project_code' => 'string (optional, 2-6 chars)',
                        'description' => 'string (optional)',
                        'color' => 'hex color (#10B981)',
                        'icon' => 'string (optional)',
                        'webhook_url' => 'url (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Project created successfully',
                    'data' => [
                        'id' => 3,
                        'project_code' => 'ETC',
                        'title' => 'E-Commerce Platform',
                        'status' => 'active'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_project_detail',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep3'),
                'path' => 'projects/' . $sampleProj,
                'desc' => __('ApiTester::sec2_ep3'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id_or_code' => 'string (required) - Project ID or Code (e.g. 01, ETC)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'data' => [
                        'id' => 1,
                        'project_code' => '01',
                        'title' => 'Task Manager',
                        'status' => 'active',
                        'stats' => [
                            'total' => 10,
                            'completed' => 6,
                            'in_progress' => 2,
                            'assigned' => 2
                        ],
                        'docs_count' => 3
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_project_tasks',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep4'),
                'path' => 'projects/' . $sampleProj . '/tasks',
                'desc' => __('ApiTester::sec2_ep4'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'query' => [
                        'status' => 'created | assigned | in_progress | submitted | completed',
                        'priority' => 'low | normal | high | urgent',
                        'assigned_to' => 'me | {user_id}',
                        'q' => 'string (search query)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'meta' => ['count' => 5],
                    'data' => [
                        [
                            'id' => 10,
                            'task_code' => '01-01',
                            'title' => 'API Test Console',
                            'priority' => 'high',
                            'status' => 'in_progress',
                            'assignees' => []
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'get_project_docs',
                'method' => 'GET',
                'name' => __('ApiTester::sec2_ep5'),
                'path' => 'projects/' . $sampleProj . '/docs',
                'desc' => __('ApiTester::sec2_ep5'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id_or_code' => 'string - Project ID or Code'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'meta' => ['count' => 2],
                    'data' => [
                        [
                            'id' => 97,
                            'title' => 'Project Architecture & Standards',
                            'icon' => 'book',
                            'content' => '# TaskManager Architecture...'
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'post_project_docs',
                'method' => 'POST',
                'name' => __('ApiTester::sec2_ep6'),
                'path' => 'projects/' . $sampleProj . '/docs',
                'desc' => __('ApiTester::sec2_ep6'),
                'body' => [
                    'title' => 'Architecture Document',
                    'icon' => 'file',
                    'content' => "### Architecture Standards\n1. Strict HMVC Architecture\n2. 3-Language Standard (TR, EN, DE)"
                ],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'title' => 'string (required)',
                        'icon' => 'string (default: file)',
                        'content' => 'markdown text (required)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Document created successfully',
                    'data' => [
                        'id' => 102,
                        'title' => 'Architecture Document',
                        'project_id' => 1
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'update_project',
                'method' => 'PUT',
                'name' => __('ApiTester::sec2_ep7'),
                'path' => 'projects/' . $sampleProj,
                'desc' => __('ApiTester::sec2_ep7'),
                'body' => [
                    'title' => 'TaskManager Enterprise',
                    'description' => 'Enhanced task management platform'
                ],
                'tip' => __('ApiTester::sec2_ep7'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'title' => 'string (optional)',
                        'description' => 'string (optional)',
                        'color' => 'hex color (optional)',
                        'webhook_url' => 'string url (optional)',
                        'agent_conversation_id' => 'string (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Project updated successfully',
                    'data' => [
                        'id' => 1,
                        'project_code' => '01',
                        'title' => 'TaskManager Enterprise'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'test_project_webhook',
                'method' => 'POST',
                'name' => __('ApiTester::sec2_ep8'),
                'path' => 'projects/' . $sampleProj . '/webhook/test',
                'desc' => __('ApiTester::sec2_ep8'),
                'body' => (object)[],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id_or_code' => 'string - Project ID or Code'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Webhook ping dispatched',
                    'data' => [
                        'success' => true,
                        'http_code' => 200,
                        'response' => '{"received": true}'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec2_title'),
                'id' => 'delete_project',
                'method' => 'DELETE',
                'name' => __('ApiTester::sec2_ep9'),
                'path' => 'projects/' . $sampleProj,
                'desc' => __('ApiTester::sec2_ep9'),
                'body' => null,
                'tip' => __('ApiTester::sec2_ep9'),
                'expected_status' => 200,
                'params' => [
                    'query' => [
                        'force' => '1 | 0 - 1 deletes permanently, 0 archives'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Project archived or deleted',
                    'data' => null
                ]
            ],

            // ==========================================
            // Kategori 3: Tasks & Kanban Workflows
            // ==========================================
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'get_all_tasks',
                'method' => 'GET',
                'name' => __('ApiTester::sec3_ep1'),
                'path' => 'tasks',
                'desc' => __('ApiTester::sec3_ep1'),
                'body' => null,
                'tip' => __('ApiTester::sec3_ep1'),
                'expected_status' => 200,
                'params' => [
                    'query' => [
                        'project' => 'string - Project Code (e.g. 01, ETC)',
                        'status' => 'created | assigned | in_progress | submitted | completed',
                        'priority' => 'low | normal | high | urgent',
                        'assigned_to' => 'me | {user_id}',
                        'q' => 'string (search text)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'meta' => ['count' => 8],
                    'data' => [
                        [
                            'id' => 15,
                            'task_code' => '01-05',
                            'title' => 'API Endpoint Catalog',
                            'status' => 'in_progress',
                            'priority' => 'normal'
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'create_task',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_ep2'),
                'path' => 'tasks',
                'desc' => __('ApiTester::sec3_ep2'),
                'body' => [
                    'project_id' => 1,
                    'title' => 'API Testing Task',
                    'description' => 'Task created via REST API console with parameters.',
                    'priority' => 'normal',
                    'deadline' => date('Y-m-d', strtotime('+3 days')),
                    'time_estimate' => '4h',
                    'tags' => 'api,test'
                ],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'title' => 'string (required)',
                        'project_id' => 'int (required)',
                        'description' => 'string (optional)',
                        'priority' => 'low | normal | high | urgent (default: normal)',
                        'deadline' => 'YYYY-MM-DD (optional)',
                        'time_estimate' => 'string (e.g. 2h, 1d)',
                        'tags' => 'comma-separated tags'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Task created successfully',
                    'data' => [
                        'id' => 45,
                        'task_code' => '01-08',
                        'title' => 'API Testing Task',
                        'status' => 'created',
                        'priority' => 'normal'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'get_task_detail',
                'method' => 'GET',
                'name' => __('ApiTester::sec3_ep3'),
                'path' => 'tasks/' . $sampleTask,
                'desc' => __('ApiTester::sec3_ep3'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id_or_code' => 'string - Task ID or Code (e.g. 01-01, TSK-101)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'data' => [
                        'id' => 22,
                        'task_code' => '01-01',
                        'title' => 'API Console Architecture',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'time_spent' => '1h 30m',
                        'assignees' => [],
                        'checklists' => [],
                        'comments' => []
                    ]
                ]
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
                    'priority' => 'high',
                    'description' => 'Revised via REST API.'
                ],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'title' => 'string (optional)',
                        'description' => 'string (optional)',
                        'priority' => 'low | normal | high | urgent',
                        'deadline' => 'YYYY-MM-DD',
                        'time_estimate' => 'string'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Task updated successfully',
                    'data' => [
                        'id' => 22,
                        'task_code' => '01-01',
                        'title' => 'Updated Task Title',
                        'priority' => 'high'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec3_title'),
                'id' => 'delete_task',
                'method' => 'DELETE',
                'name' => 'DELETE /tasks/{code}',
                'path' => 'tasks/' . $sampleTask,
                'desc' => 'Deletes the task record permanently.',
                'body' => null,
                'tip' => 'Only admin or task creator can perform this action.',
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id_or_code' => 'string - Task ID or Code'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Task deleted successfully',
                    'data' => null
                ]
            ],

            // ==========================================
            // Kategori 4: Autonomous Workflow (AI Agent)
            // ==========================================
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_accept',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf1'),
                'path' => 'tasks/' . $sampleTask . '/workflow/accept',
                'desc' => __('ApiTester::sec3_wf1'),
                'body' => (object)[],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <agent_token>'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Task accepted successfully',
                    'data' => [
                        'task_code' => '01-01',
                        'status' => 'in_progress',
                        'started_at' => '2026-10-19 22:15:00'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_status',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf2'),
                'path' => 'tasks/' . $sampleTask . '/workflow/status',
                'desc' => __('ApiTester::sec3_wf2'),
                'body' => [
                    'status' => 'in_progress',
                    'note' => 'Development phase started'
                ],
                'tip' => __('ApiTester::sec3_wf2'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'status' => 'created | assigned | in_progress | modification_requested | submitted | completed (required)',
                        'note' => 'string (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Task status updated',
                    'data' => [
                        'task_code' => '01-01',
                        'status' => 'in_progress'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_request_mod',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf3'),
                'path' => 'tasks/' . $sampleTask . '/workflow/request-modification',
                'desc' => __('ApiTester::sec3_wf3'),
                'body' => [
                    'reason' => 'Fix mobile button layout and run tri-lingual test.'
                ],
                'tip' => __('ApiTester::sec3_wf3'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'reason' => 'string (required) - Revision details and notes'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Revision requested',
                    'data' => [
                        'task_code' => '01-01',
                        'status' => 'modification_requested'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_submit',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf4'),
                'path' => 'tasks/' . $sampleTask . '/workflow/submit',
                'desc' => __('ApiTester::sec3_wf4'),
                'body' => [
                    'notes' => "1. What Was Done: Added all REST API endpoints with parameters and responses.\n2. Where to Test: /api-test\n3. How to Test: Select endpoint and click Send.\n4. Test Evidence: HTTP 200 OK received, HMVC audit passed.\n5. Audit Gate: audit_hmvc.php PASS",
                    'attachment_url' => 'https://github.com/BayKeskin/taskmanager'
                ],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'notes' => 'string (required) - 5-stage Definition of Done (DoD) report',
                        'attachment_url' => 'string url (optional) - GitHub PR or commit URL'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Task submitted successfully',
                    'data' => [
                        'task_code' => '01-01',
                        'status' => 'submitted',
                        'submitted_at' => '2026-10-19 22:20:00'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'wf_review',
                'method' => 'POST',
                'name' => __('ApiTester::sec3_wf5'),
                'path' => 'tasks/' . $sampleTask . '/workflow/review',
                'desc' => __('ApiTester::sec3_wf5'),
                'body' => [
                    'decision' => 'approved',
                    'feedback' => 'Code review and automated tests verified.'
                ],
                'tip' => __('ApiTester::sec3_wf5'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'decision' => 'approved | rejected (required)',
                        'feedback' => 'string (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Submission reviewed',
                    'data' => [
                        'task_code' => '01-01',
                        'status' => 'completed'
                    ]
                ]
            ],

            // ==========================================
            // Kategori 5: Checklists, Comments & Assignees
            // ==========================================
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'get_task_checklists',
                'method' => 'GET',
                'name' => 'GET /tasks/{code}/checklists',
                'path' => 'tasks/' . $sampleTask . '/checklists',
                'desc' => __('ApiTester::sec4_ep1'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'code' => 'string - Task Code'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        [
                            'id' => 12,
                            'title' => 'All REST API endpoints cataloged',
                            'is_completed' => 1
                        ],
                        [
                            'id' => 13,
                            'title' => 'Tri-lingual support verified',
                            'is_completed' => 1
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'post_task_checklist',
                'method' => 'POST',
                'name' => __('ApiTester::sec4_ep1'),
                'path' => 'tasks/' . $sampleTask . '/checklists',
                'desc' => __('ApiTester::sec4_ep1'),
                'body' => [
                    'title' => 'Autonomous tests executed'
                ],
                'tip' => __('ApiTester::sec4_ep1'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'title' => 'string (required) - Checklist item title'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Checklist item added',
                    'data' => [
                        'id' => 14,
                        'task_id' => 22,
                        'title' => 'Autonomous tests executed',
                        'is_completed' => 0
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'update_task_checklist',
                'method' => 'PUT',
                'name' => __('ApiTester::sec4_ep2'),
                'path' => 'tasks/' . $sampleTask . '/checklists/1',
                'desc' => __('ApiTester::sec4_ep2'),
                'body' => [
                    'is_completed' => 1,
                    'title' => 'Autonomous tests executed and verified'
                ],
                'tip' => __('ApiTester::sec4_ep2'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'is_completed' => '0 | 1',
                        'title' => 'string (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Checklist item updated',
                    'data' => [
                        'id' => 1,
                        'is_completed' => 1
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'get_task_comments',
                'method' => 'GET',
                'name' => 'GET /tasks/{code}/comments',
                'path' => 'tasks/' . $sampleTask . '/comments',
                'desc' => __('ApiTester::sec4_ep3'),
                'body' => null,
                'tip' => __('ApiTester::sec4_ep3'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'code' => 'string - Task Code'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        [
                            'id' => 88,
                            'comment' => 'Progress report: Tests completed.',
                            'author' => 'Talha Keskin',
                            'created_at' => '2026-10-19 22:18:00'
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'post_task_comment',
                'method' => 'POST',
                'name' => __('ApiTester::sec4_ep3'),
                'path' => 'tasks/' . $sampleTask . '/comments',
                'desc' => __('ApiTester::sec4_ep3'),
                'body' => [
                    'comment' => 'API Test Console: Progress report successfully logged.'
                ],
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'comment' => 'string (required) - Progress note or report'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Comment added',
                    'data' => [
                        'id' => 89,
                        'comment' => 'API Test Console: Progress report successfully logged.'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'assign_user',
                'method' => 'POST',
                'name' => __('ApiTester::sec4_ep4'),
                'path' => 'tasks/' . $sampleTask . '/assignees',
                'desc' => __('ApiTester::sec4_ep4'),
                'body' => [
                    'user_id' => 1
                ],
                'tip' => __('ApiTester::sec4_ep4'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'user_id' => 'int (user ID to assign)',
                        'email' => 'string (optional alternative to user_id)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'User assigned to task',
                    'data' => [
                        'id' => 1,
                        'name' => 'Talha Keskin'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec4_title'),
                'id' => 'remove_assignee',
                'method' => 'DELETE',
                'name' => __('ApiTester::sec4_ep5'),
                'path' => 'tasks/' . $sampleTask . '/assignees/1',
                'desc' => __('ApiTester::sec4_ep5'),
                'body' => null,
                'tip' => __('ApiTester::sec4_ep5'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'code' => 'string - Task Code',
                        'user_id' => 'int - User ID to remove'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Assignee removed',
                    'data' => null
                ]
            ],

            // ==========================================
            // Kategori 6: Documents, Team & Reports
            // ==========================================
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'get_all_docs',
                'method' => 'GET',
                'name' => __('ApiTester::sec5_ep1'),
                'path' => 'docs',
                'desc' => __('ApiTester::sec5_ep1'),
                'body' => null,
                'tip' => __('ApiTester::sec5_ep1'),
                'expected_status' => 200,
                'params' => [
                    'query' => [
                        'project' => 'string - Project Code (e.g. 01)',
                        'project_id' => 'int - Project ID',
                        'lang' => 'tr | en | de'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'meta' => ['total' => 4],
                    'data' => [
                        [
                            'id' => 97,
                            'title' => 'Architecture Guide',
                            'icon' => 'book'
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'get_single_doc',
                'method' => 'GET',
                'name' => __('ApiTester::sec5_ep2'),
                'path' => 'docs/1',
                'desc' => __('ApiTester::sec5_ep2'),
                'body' => null,
                'tip' => __('ApiTester::sec5_ep2'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id' => 'int (required) - Document ID'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        'id' => 1,
                        'title' => 'Getting Started Guide',
                        'icon' => 'book',
                        'content' => '# Getting Started...'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'post_global_doc',
                'method' => 'POST',
                'name' => __('ApiTester::sec5_ep3'),
                'path' => 'docs',
                'desc' => __('ApiTester::sec5_ep3'),
                'body' => [
                    'title' => 'New Global Guide',
                    'project_id' => 1,
                    'icon' => 'book',
                    'content' => '### Global Standards & QA Procedures'
                ],
                'tip' => __('ApiTester::sec5_ep3'),
                'expected_status' => 201,
                'params' => [
                    'body' => [
                        'title' => 'string (required)',
                        'content' => 'markdown text (required)',
                        'project_id' => 'int (optional)',
                        'icon' => 'string'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Document created successfully',
                    'data' => [
                        'id' => 103,
                        'title' => 'New Global Guide'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'put_doc',
                'method' => 'PUT',
                'name' => __('ApiTester::sec5_ep4'),
                'path' => 'docs/1',
                'desc' => __('ApiTester::sec5_ep4'),
                'body' => [
                    'title' => 'Updated Guide Title',
                    'content' => '### Updated Guide Content'
                ],
                'tip' => __('ApiTester::sec5_ep4'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'title' => 'string (optional)',
                        'content' => 'string markdown (optional)',
                        'icon' => 'string (optional)'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Document updated successfully',
                    'data' => [
                        'id' => 1,
                        'title' => 'Updated Guide Title'
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'delete_doc',
                'method' => 'DELETE',
                'name' => __('ApiTester::sec5_ep5'),
                'path' => 'docs/1',
                'desc' => __('ApiTester::sec5_ep5'),
                'body' => null,
                'tip' => __('ApiTester::sec5_ep5'),
                'expected_status' => 200,
                'params' => [
                    'url' => [
                        'id' => 'int - Document ID to delete'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Document deleted successfully',
                    'data' => null
                ]
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'get_users',
                'method' => 'GET',
                'name' => __('ApiTester::sec5_ep6'),
                'path' => 'users',
                'desc' => __('ApiTester::sec5_ep6'),
                'body' => null,
                'tip' => __('ApiTester::endpoints_note'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <token>'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        [
                            'id' => 1,
                            'name' => 'Talha Keskin',
                            'email' => 'iletisimtktalha@gmail.com',
                            'role' => 'admin',
                            'active_tasks' => 4
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec5_title'),
                'id' => 'get_reports',
                'method' => 'GET',
                'name' => __('ApiTester::sec5_ep7'),
                'path' => 'reports',
                'desc' => __('ApiTester::sec5_ep7'),
                'body' => null,
                'tip' => __('ApiTester::sec5_ep7'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <token>'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        'total_tasks' => 120,
                        'completed_tasks' => 84,
                        'completion_rate' => 70,
                        'total_projects' => 5
                    ]
                ]
            ],

            // ==========================================
            // Kategori 7: Webhooks & AI Agent Bridge
            // ==========================================
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'get_webhook_logs',
                'method' => 'GET',
                'name' => __('ApiTester::sec6_ep2'),
                'path' => 'webhooks/logs',
                'desc' => __('ApiTester::sec6_ep2'),
                'body' => null,
                'tip' => __('ApiTester::sec6_ep2'),
                'expected_status' => 200,
                'params' => [
                    'headers' => [
                        'Authorization' => 'Bearer <token>'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        [
                            'event' => 'task.created',
                            'http_code' => 200,
                            'dispatched_at' => '2026-10-19 22:10:00'
                        ]
                    ]
                ]
            ],
            [
                'category' => __('ApiTester::sec6_title'),
                'id' => 'post_webhook_listener',
                'method' => 'POST',
                'name' => __('ApiTester::sec6_ep4'),
                'path' => 'webhooks/listener',
                'desc' => __('ApiTester::sec6_ep4'),
                'body' => [
                    'event' => 'ping',
                    'agent_conversation_id' => 'b2c40da5-82d6-4d49-bea9-34a1a90535ce',
                    'payload' => [
                        'message' => 'AI agent connected and active'
                    ]
                ],
                'tip' => __('ApiTester::sec6_ep4'),
                'expected_status' => 200,
                'params' => [
                    'body' => [
                        'event' => 'string (e.g. ping, task.status_changed)',
                        'agent_conversation_id' => 'string',
                        'payload' => 'object'
                    ]
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Operation completed successfully',
                    'data' => [
                        'received' => true,
                        'event' => 'ping',
                        'received_at' => '2026-10-19T22:25:00+03:00'
                    ]
                ]
            ]
        ];
    }
}
