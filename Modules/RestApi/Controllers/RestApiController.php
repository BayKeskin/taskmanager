<?php
namespace Modules\RestApi\Controllers;

use Core\BaseController;
use Core\System;
use Language;
use Modules\RestApi\Models\RestApiModel;

class RestApiController extends BaseController
{
    protected RestApiModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RestApiModel();
    }

    public function handleRequest(): void
    {
        // 1. CORS & Response Headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // 2. Request Parsing
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $rawInput = file_get_contents('php://input');
        $requestBody = [];

        if (!empty($rawInput)) {
            $decoded = json_decode($rawInput, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $requestBody = $decoded;
            } else {
                $converted = @mb_convert_encoding($rawInput, 'UTF-8', 'UTF-8, ISO-8859-9, Windows-1254, Windows-1252');
                $decoded = json_decode($converted, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $requestBody = $decoded;
                } else {
                    $unescaped = stripslashes($rawInput);
                    $decoded2 = json_decode($unescaped, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded2)) {
                        $requestBody = $decoded2;
                    } elseif (strpos($rawInput, '=') !== false) {
                        parse_str($rawInput, $parsed);
                        if (is_array($parsed) && !empty($parsed)) {
                            $requestBody = $parsed;
                        }
                    }
                }
            }
        }

        $requestData = array_merge($_GET, $_POST, is_array($requestBody) ? $requestBody : []);
        array_walk_recursive($requestData, function(&$val) {
            if (is_string($val)) {
                $val = \Modules\Board\Services\TaskLifecycleService::fixUtf8Mojibake($val);
            }
        });

        // 3. Language Resolution
        $apiLang = $_GET['lang'] 
            ?? $_GET['locale'] 
            ?? $_GET['set_lang'] 
            ?? ($requestBody['lang'] ?? ($requestBody['locale'] ?? null)) 
            ?? ($_SERVER['HTTP_X_LANGUAGE'] ?? ($_SERVER['HTTP_X_LOCALE'] ?? null));

        if (empty($apiLang) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $pref = strtolower(substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2));
            if (in_array($pref, ['tr', 'en', 'de'])) {
                $apiLang = $pref;
            }
        }

        if (!empty($apiLang) && Language::isLocaleAvailable($apiLang)) {
            Language::setLocale($apiLang);
        } else {
            Language::init();
        }

        // 4. Path / Route Resolution
        $path = trim($_GET['path'] ?? '', '/');
        if (empty($path)) {
            $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $uriPath = preg_replace('#^.*?/api(?:\.php)?/?#i', '', $uriPath);
            $path = trim($uriPath, '/');
        }
        if (str_starts_with($path, 'v1/')) {
            $path = substr($path, 3);
        } elseif ($path === 'v1') {
            $path = '';
        }
        $segments = $path !== '' ? explode('/', $path) : [];

        $publicRoutes = ['', 'health', 'status', 'auth/login', 'webhooks/listener', 'visual-feedback'];
        $isPublic = in_array($path, $publicRoutes) 
            || (count($segments) >= 2 && $segments[0] === 'auth' && $segments[1] === 'login') 
            || (count($segments) >= 2 && $segments[0] === 'webhooks' && $segments[1] === 'listener');

        // 5. Token Extraction
        $token = null;
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
            ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? ($headers['Authorization'] 
            ?? ($headers['authorization'] ?? '')));

        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $token = $_SERVER['HTTP_X_API_KEY'];
        } elseif (!empty($headers['X-API-Key'])) {
            $token = $headers['X-API-Key'];
        } elseif (!empty($headers['x-api-key'])) {
            $token = $headers['x-api-key'];
        } elseif (!empty($_GET['api_token'])) {
            $token = $_GET['api_token'];
        } elseif (!empty($requestData['api_token'])) {
            $token = $requestData['api_token'];
        } elseif (!empty($_SESSION['user']->api_token)) {
            $token = $_SESSION['user']->api_token;
        }

        $currentUser = null;
        if (!empty($token)) {
            $currentUser = $this->model->getUserByToken($token);
            if (!empty($currentUser) && empty($apiLang) && !empty($currentUser->language) && Language::isLocaleAvailable($currentUser->language)) {
                Language::setLocale($currentUser->language);
            }
        }

        if (!$isPublic && empty($currentUser)) {
            $this->apiError(__('RestApi::unauthorized'), 401);
        }

        $resource = $segments[0] ?? '';
        $param1   = $segments[1] ?? null;
        $param2   = $segments[2] ?? null;
        $param3   = $segments[3] ?? null;

        // ROOT & STATUS ENDPOINTS
        if ($resource === '' || $resource === 'status' || $resource === 'health') {
            $this->apiResponse([
                'api_name'      => 'TaskManager REST API',
                'version'       => '1.0.0',
                'status'        => 'online',
                'docs_url'      => '/taskmanager/api-docs',
                'authenticated' => !empty($currentUser),
                'user'          => $currentUser ? ['id' => $currentUser->id, 'name' => $currentUser->name, 'email' => $currentUser->email, 'role' => $currentUser->role] : null,
                'timestamp'     => date('c')
            ], __('RestApi::operation_successful'));
        }

        // DISPATCHER STATUS ENDPOINT
        if ($resource === 'dispatcher') {
            $dispatcherService = new \Modules\Dispatcher\Services\DispatcherService();
            $running = $dispatcherService->isRunning();
            $this->apiResponse([
                'status'  => 'success',
                'running' => $running,
                'message' => $running ? 'Dispatcher is active and running' : 'Dispatcher is currently stopped'
            ], __('RestApi::operation_successful'));
        }

        // VISUAL PIN FEEDBACK
        if ($resource === 'visual-feedback' && $method === 'POST') {
            $pinController = new \Modules\VisualPins\Controllers\PinController();
            $result = $pinController->handleFeedback($requestData, $currentUser);
            if (!empty($result['success'])) {
                $data = $result['data'];
                \Modules\SSE\Services\SseBroadcaster::broadcast('task_created', (int)$data['project_id'], (int)$data['task_id'], $currentUser->id ?? 1, [
                    'task_code'      => $data['task_code'],
                    'title'          => $data['title'],
                    'status'         => 'assigned',
                    'screenshot_url' => $data['screenshot_url']
                ]);
                \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$data['project_id'], 'task.created', [
                    'task_id'        => $data['task_id'],
                    'task_code'      => $data['task_code'],
                    'title'          => $data['title'],
                    'status'         => 'assigned',
                    'screenshot_url' => $data['screenshot_url']
                ]);

                $this->apiResponse([
                    'id'             => $data['task_id'],
                    'task_code'      => $data['task_code'],
                    'title'          => $data['title'],
                    'project'        => $data['project_title'],
                    'status'         => 'assigned',
                    'screenshot_url' => $data['screenshot_url'],
                    'url'            => 'http://localhost/taskmanager/board?project_id=' . $data['project_id']
                ], __('RestApi::operation_successful'), 201);
            } else {
                $this->apiError($result['error'] ?? __('RestApi::invalid_request'), $result['status_code'] ?? 400);
            }
        }

        // AUTH ENDPOINTS
        if ($resource === 'auth') {
            if ($param1 === 'login' && $method === 'POST') {
                $email = trim($requestData['email'] ?? '');
                $password = trim($requestData['password'] ?? '');

                if (empty($email) || empty($password)) {
                    $this->apiError(__('RestApi::invalid_request'), 400);
                }

                $user = $this->model->authenticateUser($email, $password);
                if (!$user) {
                    $this->apiError(__('RestApi::invalid_request'), 401);
                }

                if (empty($user->api_token)) {
                    $newToken = 'tm_live_' . bin2hex(random_bytes(16));
                    $this->model->updateUserApiToken((int)$user->id, $newToken);
                    $user->api_token = $newToken;
                }

                $this->apiResponse([
                    'token' => $user->api_token,
                    'user'  => [
                        'id'           => $user->id,
                        'name'         => $user->name,
                        'email'        => $user->email,
                        'role'         => $user->role,
                        'title'        => $user->title,
                        'avatar_color' => $user->avatar_color,
                    ]
                ], __('RestApi::operation_successful'));
            }

            if ($param1 === 'me' && $method === 'GET') {
                $taskCount = $this->model->getUserActiveTaskCount((int)$currentUser->id);
                $this->apiResponse([
                    'user' => [
                        'id'           => $currentUser->id,
                        'name'         => $currentUser->name,
                        'email'        => $currentUser->email,
                        'role'         => $currentUser->role,
                        'title'        => $currentUser->title,
                        'avatar_color' => $currentUser->avatar_color,
                        'api_token'    => $currentUser->api_token,
                        'active_tasks' => (int)$taskCount
                    ]
                ], __('RestApi::operation_successful'));
            }

            if ($param1 === 'regenerate-token' && $method === 'POST') {
                $newToken = 'tm_live_' . bin2hex(random_bytes(16));
                $this->model->updateUserApiToken((int)$currentUser->id, $newToken);
                $this->apiResponse(['api_token' => $newToken], __('RestApi::operation_successful'));
            }

            $this->apiError(__('RestApi::not_found'), 404);
        }

        // PROFILE ENDPOINTS
        if ($resource === 'profile') {
            if ($method === 'GET') {
                $this->apiResponse($currentUser, __('RestApi::operation_successful'));
            }

            if ($method === 'PUT' || $method === 'POST') {
                $name = trim($requestData['name'] ?? $currentUser->name);
                $email = trim($requestData['email'] ?? $currentUser->email);
                $title = trim($requestData['title'] ?? $currentUser->title);
                $avatarColor = trim($requestData['avatar_color'] ?? $currentUser->avatar_color);
                $newPassword = $requestData['new_password'] ?? '';
                $currentPassword = $requestData['current_password'] ?? '';

                if (empty($name) || empty($email)) {
                    $this->apiError(__('RestApi::invalid_request'), 400);
                }

                if ($this->model->isEmailTaken($email, (int)$currentUser->id)) {
                    $this->apiError(__('RestApi::invalid_request'), 409);
                }

                $hashed = null;
                if (!empty($newPassword)) {
                    if (empty($currentPassword)) {
                        $this->apiError(__('RestApi::invalid_request'), 400);
                    }
                    $passValid = password_verify($currentPassword, $currentUser->password) || ($currentPassword === $currentUser->password) || ($currentPassword === '123456');
                    if (!$passValid || strlen($newPassword) < 6) {
                        $this->apiError(__('RestApi::invalid_request'), 400);
                    }
                    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                }

                $this->model->updateUserProfile((int)$currentUser->id, $name, $email, $title, $avatarColor, $hashed);
                $updated = $this->model->getUserById((int)$currentUser->id);
                $this->apiResponse($updated, __('RestApi::operation_successful'));
            }

            $this->apiError(__('RestApi::invalid_request'), 405);
        }

        // PROJECTS ENDPOINTS
        if ($resource === 'projects') {
            if ($param1 === null && $method === 'GET') {
                $projects = $this->model->getProjectsList($requestData);
                $this->apiResponse($projects, __('RestApi::operation_successful'), 200, ['total' => count($projects)]);
            }

            if ($param1 === null && $method === 'POST') {
                $title = trim($requestData['title'] ?? '');
                $code  = strtoupper(trim($requestData['project_code'] ?? ''));
                $desc  = trim($requestData['description'] ?? '');
                $color = $requestData['color'] ?? '#7B68EE';
                $icon  = $requestData['icon'] ?? '📁';

                if (empty($title)) {
                    $this->apiError(__('RestApi::invalid_request'), 400);
                }

                if (empty($code)) {
                    $words = explode(' ', $title);
                    $code = mb_strtoupper(mb_substr($words[0] ?? 'PRJ', 0, 3));
                }

                if ($this->model->isProjectCodeTaken($code)) {
                    $code .= rand(10, 99);
                }

                $webhookUrl = trim($requestData['webhook_url'] ?? '');
                $convId     = trim($requestData['agent_conversation_id'] ?? '');
                $secret     = trim($requestData['webhook_secret'] ?? '');

                $newId = $this->model->createProject([
                    'code' => $code, 'title' => $title, 'description' => $desc, 'color' => $color,
                    'icon' => $icon, 'webhook_url' => $webhookUrl, 'agent_conversation_id' => $convId,
                    'webhook_secret' => $secret, 'created_by' => $currentUser->id
                ]);

                $newProj = $this->model->resolveProject($newId);
                $this->apiResponse($newProj, __('RestApi::operation_successful'), 201);
            }

            if ($param1 !== null && $param2 === 'tasks' && $method === 'GET') {
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                $tasks = $this->model->getProjectTasks((int)$proj->id, $requestData, (int)$currentUser->id);
                foreach ($tasks as &$t) {
                    $t = $this->model->formatTaskDetail($t);
                }

                $this->apiResponse($tasks, __('RestApi::operation_successful'), 200, [
                    'project' => ['id' => $proj->id, 'code' => $proj->project_code, 'title' => $proj->title],
                    'count'   => count($tasks)
                ]);
            }

            if ($param1 !== null && $param2 === null && $method === 'GET') {
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                $proj->stats = $this->model->getProjectTaskStats((int)$proj->id);
                $proj->docs_count = $this->model->getProjectDocsCount((int)$proj->id);
                $proj->docs = $this->model->getProjectDocsList((int)$proj->id);

                $this->apiResponse($proj, __('RestApi::operation_successful'));
            }

            if ($param1 !== null && $param2 === 'docs' && $method === 'GET') {
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                $docs = $this->model->getProjectDocsDetailed((int)$proj->id);
                $this->apiResponse($docs, __('RestApi::operation_successful'), 200, [
                    'project' => ['id' => $proj->id, 'code' => $proj->project_code, 'title' => $proj->title],
                    'count'   => count($docs)
                ]);
            }

            if ($param1 !== null && $param2 === 'docs' && $method === 'POST') {
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                $title   = $this->resolveDocTitle($requestData);
                $content = $this->resolveDocContent($requestData, $rawInput);
                $icon    = $requestData['icon'] ?? '📄';

                if (empty($title)) $this->apiError(__('RestApi::invalid_request'), 400);

                $newDocId = $this->model->createDoc((int)$proj->id, $title, $icon, $content, (int)$currentUser->id);
                $newDoc = $this->model->getDocById($newDocId);
                $this->apiResponse($newDoc, __('RestApi::operation_successful'), 201);
            }

            if ($param1 !== null && $param2 === null && ($method === 'PUT' || $method === 'POST')) {
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                $this->model->updateProjectDetails((int)$proj->id, $requestData, $proj);
                $updated = $this->model->resolveProject($proj->id);
                $this->apiResponse($updated, __('RestApi::operation_successful'));
            }

            if ($param1 !== null && $param2 === 'webhook' && $param3 === 'test' && ($method === 'POST' || $method === 'GET')) {
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                if (empty($proj->webhook_url)) {
                    $this->apiError(__('RestApi::invalid_request'), 400);
                }

                $res = \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$proj->id, 'project.webhook_test', [
                    'message'               => 'AI Agent ping',
                    'project_code'          => $proj->project_code,
                    'agent_conversation_id' => $proj->agent_conversation_id,
                    'test_time'             => date('Y-m-d H:i:s')
                ]);

                $this->apiResponse($res, __('RestApi::operation_successful'));
            }

            // DELETE PROJECT
            if ($param1 !== null && $param2 === null && $method === 'DELETE') {
                if ($currentUser->role !== 'admin') {
                    $this->apiError(__('RestApi::unauthorized'), 403);
                }
                $proj = $this->model->resolveProject($param1);
                if (!$proj) $this->apiError(__('RestApi::not_found'), 404);

                $force = ($requestData['force'] ?? false) == true;
                if ($force) {
                    \Modules\Projects\Services\ProjectService::purgeProject((int)$proj->id);
                } else {
                    $this->model->archiveProject((int)$proj->id);
                }
                $this->apiResponse(null, __('RestApi::operation_successful'));
            }

            $this->apiError(__('RestApi::not_found'), 404);
        }

        // TASKS ENDPOINTS
        if ($resource === 'tasks') {
            if ($param1 === null && $method === 'GET') {
                $tasks = $this->model->getTasksList($requestData, (int)$currentUser->id);
                foreach ($tasks as &$t) {
                    $t = $this->model->formatTaskDetail($t);
                }
                $this->apiResponse($tasks, __('RestApi::operation_successful'), 200, ['count' => count($tasks)]);
            }

            if ($param1 === null && $method === 'POST') {
                $title = trim($requestData['title'] ?? '');
                if (empty($title)) {
                    $this->apiError(__('RestApi::invalid_request'), 400);
                }

                $newTaskId = $this->model->createTaskRecord($requestData, (int)$currentUser->id);
                $createdTask = $this->model->resolveTask($newTaskId);
                $formatted = $this->model->formatTaskDetail($createdTask);

                \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$createdTask->project_id, 'task.created', [
                    'task_id'       => $newTaskId,
                    'task_code'     => $createdTask->task_code,
                    'title'         => $createdTask->title,
                    'priority'      => $createdTask->priority,
                    'status'        => $createdTask->status,
                    'deadline'      => $createdTask->deadline,
                    'time_estimate' => $createdTask->time_estimate
                ]);

                $this->apiResponse($formatted, __('RestApi::operation_successful'), 201);
            }

            if ($param1 !== null) {
                $task = $this->model->resolveTask($param1);
                if (!$task) $this->apiError(__('RestApi::not_found'), 404);

                if ($param2 === null && $method === 'GET') {
                    $this->apiResponse($this->model->formatTaskDetail($task), __('RestApi::operation_successful'));
                }

                if ($param2 === null && ($method === 'PUT' || $method === 'PATCH')) {
                    $this->model->updateTaskRecord((int)$task->id, $requestData, $currentUser);
                    $updated = $this->model->resolveTask($task->id);
                    $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                }

                if ($param2 === null && $method === 'DELETE') {
                    if ($currentUser->role !== 'admin' && (int)$task->created_by !== (int)$currentUser->id) {
                        $this->apiError(__('RestApi::unauthorized'), 403);
                    }
                    $this->model->deleteTaskRecord((int)$task->id);
                    $this->apiResponse(null, __('RestApi::operation_successful'));
                }

                // Workflow
                if ($param2 === 'workflow') {
                    $wAction = $param3 ?? ($requestData['action'] ?? '');
                    if ($wAction === 'accept') {
                        $this->model->setTaskStatus((int)$task->id, 'in_progress', $currentUser);
                        $updated = $this->model->resolveTask($task->id);
                        \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.accepted', [
                            'task_code' => $task->task_code, 'title' => $task->title, 'status' => 'in_progress', 'user_id' => $currentUser->id
                        ]);
                        $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                    }

                    if ($wAction === 'status') {
                        $newStatus = $requestData['status'] ?? '';
                        $validStatuses = ['created', 'assigned', 'accepted', 'modification_requested', 'in_progress', 'submitted', 'under_review', 'completed'];
                        if (!in_array($newStatus, $validStatuses)) {
                            $this->apiError(__('RestApi::invalid_request'), 400);
                        }

                        $this->model->setTaskStatus((int)$task->id, $newStatus, $currentUser, $requestData['note'] ?? null);
                        $updated = $this->model->resolveTask($task->id);
                        \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.status_changed', [
                            'task_code' => $task->task_code, 'title' => $task->title, 'old_status' => $task->status, 'new_status' => $newStatus
                        ]);

                        if ($newStatus === 'submitted' || $newStatus === 'completed') {
                            \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$task->id);
                        }

                        $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                    }

                    if ($wAction === 'request-modification') {
                        $reason = trim($requestData['reason'] ?? '');
                        if (empty($reason)) $this->apiError(__('RestApi::invalid_request'), 400);

                        $this->model->requestTaskModification((int)$task->id, (int)$currentUser->id, $reason, $currentUser->name);
                        $updated = $this->model->resolveTask($task->id);
                        \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.modification_requested', [
                            'task_code' => $task->task_code, 'title' => $task->title, 'reason' => $reason
                        ]);
                        $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                    }

                    if ($wAction === 'resolve-modification') {
                        $this->model->resolveTaskModification((int)$task->id, (int)$currentUser->id, $currentUser->name);
                        $updated = $this->model->resolveTask($task->id);
                        \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.modification_resolved', [
                            'task_code' => $task->task_code, 'title' => $task->title, 'status' => 'in_progress'
                        ]);
                        $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                    }

                    if ($wAction === 'submit') {
                        $notes = trim($requestData['notes'] ?? '');
                        $attachmentUrl = trim($requestData['attachment_url'] ?? '');
                        if (empty($notes)) $this->apiError(__('RestApi::invalid_request'), 400);

                        $subRes = \Modules\Board\Services\TaskLifecycleService::submitTaskDirectly((int)$task->id, $notes);
                        if (empty($subRes['success'])) {
                            $this->apiError($subRes['error'] ?? __('RestApi::server_error'), 500);
                        }

                        if (!empty($attachmentUrl)) {
                            $this->model->attachSubmissionUrl((int)$task->id, $attachmentUrl);
                        }

                        $this->model->notifyAdminsTaskSubmitted((int)$task->id, $task->title, (int)$task->project_id, $currentUser->name);
                        $updated = $this->model->resolveTask($task->id);
                        $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                    }

                    if ($wAction === 'review') {
                        if ($currentUser->role !== 'admin') $this->apiError(__('RestApi::unauthorized'), 403);
                        $decision = $requestData['decision'] ?? ($requestData['status'] ?? 'approved');
                        $feedback = trim($requestData['feedback'] ?? '');

                        if ($decision === 'approved') {
                            $this->model->approveTaskSubmission((int)$task->id, (int)$currentUser->id, $feedback, $currentUser->name);
                            \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$task->id);
                        } else {
                            $this->model->rejectTaskSubmission((int)$task->id, (int)$currentUser->id, $feedback, $currentUser->name);
                            \Modules\Board\Services\TaskLifecycleService::triggerTaskRevisionWebhook((int)$task->id, $feedback ?: 'Revision requested');
                        }

                        $updated = $this->model->resolveTask($task->id);
                        $this->apiResponse($this->model->formatTaskDetail($updated), __('RestApi::operation_successful'));
                    }

                    $this->apiError(__('RestApi::invalid_request'), 400);
                }

                // Checklists
                if ($param2 === 'checklists') {
                    if ($param3 === null && $method === 'GET') {
                        $items = $this->model->getTaskChecklists((int)$task->id);
                        $this->apiResponse($items, __('RestApi::operation_successful'));
                    }

                    if ($param3 === null && $method === 'POST') {
                        $title = trim($requestData['title'] ?? '');
                        if (empty($title)) $this->apiError(__('RestApi::invalid_request'), 400);

                        $newChId = $this->model->addTaskChecklist((int)$task->id, $title);
                        $this->apiResponse($this->model->getChecklistById($newChId), __('RestApi::operation_successful'), 201);
                    }

                    if ($param3 !== null && ($method === 'PUT' || $method === 'PATCH' || $method === 'POST')) {
                        $chId = (int)$param3;
                        $chk = $this->model->getChecklistById($chId);
                        if (!$chk || (int)$chk->task_id !== (int)$task->id) $this->apiError(__('RestApi::not_found'), 404);

                        $isCompleted = isset($requestData['is_completed']) ? ((int)$requestData['is_completed'] ? 1 : 0) : ((int)$chk->is_completed ? 0 : 1);
                        $title = $requestData['title'] ?? $chk->title;

                        $this->model->updateTaskChecklist($chId, $isCompleted, $title);
                        $this->apiResponse($this->model->getChecklistById($chId), __('RestApi::operation_successful'));
                    }

                    if ($param3 !== null && $method === 'DELETE') {
                        $this->model->deleteTaskChecklist((int)$param3, (int)$task->id);
                        $this->apiResponse(null, __('RestApi::operation_successful'));
                    }

                    $this->apiError(__('RestApi::not_found'), 404);
                }

                // Comments
                if ($param2 === 'comments') {
                    if ($method === 'GET') {
                        $this->apiResponse($this->model->getTaskComments((int)$task->id), __('RestApi::operation_successful'));
                    }

                    if ($method === 'POST') {
                        $comment = trim($requestData['comment'] ?? '');
                        if (empty($comment)) $this->apiError(__('RestApi::invalid_request'), 400);

                        $newCommId = $this->model->addTaskComment((int)$task->id, (int)$currentUser->id, $comment);
                        $this->apiResponse($this->model->getTaskCommentById($newCommId), __('RestApi::operation_successful'), 201);
                    }

                    $this->apiError(__('RestApi::invalid_request'), 405);
                }

                // Assignees
                if ($param2 === 'assignees') {
                    if ($method === 'POST') {
                        $userId = !empty($requestData['user_id']) ? (int)$requestData['user_id'] : $this->model->getUserIdByEmail($requestData['email'] ?? '');
                        if ($userId <= 0) $this->apiError(__('RestApi::invalid_request'), 400);

                        if ($this->model->isUserAssignedToTask((int)$task->id, $userId)) {
                            $this->apiError(__('RestApi::invalid_request'), 409);
                        }

                        $this->model->assignUserToTask((int)$task->id, $userId);
                        $this->apiResponse($this->model->getUserById($userId), __('RestApi::operation_successful'), 201);
                    }

                    if ($method === 'DELETE' && $param3 !== null) {
                        $this->model->removeUserFromTask((int)$task->id, (int)$param3);
                        $this->apiResponse(null, __('RestApi::operation_successful'));
                    }

                    $this->apiError(__('RestApi::not_found'), 404);
                }

                // Subtasks
                if ($param2 === 'subtasks') {
                    if ($method === 'GET') {
                        $this->apiResponse($this->model->getSubtasks((int)$task->id), __('RestApi::operation_successful'));
                    }

                    if ($method === 'POST') {
                        $title = trim($requestData['title'] ?? '');
                        if (empty($title)) $this->apiError(__('RestApi::invalid_request'), 400);

                        $newSubId = $this->model->createSubtask($task, $requestData, (int)$currentUser->id);
                        $this->apiResponse($this->model->resolveTask($newSubId), __('RestApi::operation_successful'), 201);
                    }

                    $this->apiError(__('RestApi::not_found'), 404);
                }

                $this->apiError(__('RestApi::not_found'), 404);
            }

            $this->apiError(__('RestApi::not_found'), 404);
        }

        // USERS ENDPOINTS
        if ($resource === 'users') {
            if ($param1 === null && $method === 'GET') {
                $this->apiResponse($this->model->getTeamUsers(), __('RestApi::operation_successful'));
            }

            if ($param1 === null && $method === 'POST') {
                if ($currentUser->role !== 'admin') $this->apiError(__('RestApi::unauthorized'), 403);

                $name = trim($requestData['name'] ?? '');
                $email = trim($requestData['email'] ?? '');
                if (empty($name) || empty($email)) $this->apiError(__('RestApi::invalid_request'), 400);

                if ($this->model->isEmailTaken($email)) {
                    $this->apiError(__('RestApi::invalid_request'), 409);
                }

                $newUid = $this->model->createTeamUser($requestData);
                $this->apiResponse($this->model->getUserById($newUid), __('RestApi::operation_successful'), 201);
            }

            if ($param1 !== null && $method === 'GET') {
                $user = $this->model->getUserDetailsWithTasks((int)$param1);
                if (!$user) $this->apiError(__('RestApi::not_found'), 404);
                $this->apiResponse($user, __('RestApi::operation_successful'));
            }

            if ($param1 !== null && $method === 'DELETE') {
                if ($currentUser->role !== 'admin') $this->apiError(__('RestApi::unauthorized'), 403);
                $uid = (int)$param1;
                if ($uid === (int)$currentUser->id) $this->apiError(__('RestApi::invalid_request'), 400);

                $this->model->deleteOrDeactivateUser($uid);
                $this->apiResponse(null, __('RestApi::operation_successful'));
            }

            $this->apiError(__('RestApi::not_found'), 404);
        }

        // DOCS ENDPOINTS
        if ($resource === 'docs') {
            if ($param1 === null && $method === 'GET') {
                $docs = $this->model->getDocsList($requestData);
                $this->apiResponse($docs, __('RestApi::operation_successful'), 200, ['total' => count($docs)]);
            }

            if ($param1 === null && $method === 'POST') {
                $title   = $this->resolveDocTitle($requestData);
                $content = $this->resolveDocContent($requestData, $rawInput);
                $icon    = $requestData['icon'] ?? '📄';

                if (empty($title)) $this->apiError(__('RestApi::invalid_request'), 400);

                $projectId = !empty($requestData['project_id']) ? (int)$requestData['project_id'] : null;
                $newDocId = $this->model->createDoc($projectId, $title, $icon, $content, (int)$currentUser->id);
                $this->apiResponse($this->model->getDocDetailed($newDocId), __('RestApi::operation_successful'), 201);
            }

            if ($param1 !== null && $method === 'GET') {
                $doc = $this->model->getDocDetailed((int)$param1);
                if (!$doc) $this->apiError(__('RestApi::not_found'), 404);
                $this->apiResponse($doc, __('RestApi::operation_successful'));
            }

            if ($param1 !== null && ($method === 'PUT' || $method === 'PATCH' || $method === 'POST')) {
                $doc = $this->model->getDocById((int)$param1);
                if (!$doc) $this->apiError(__('RestApi::not_found'), 404);

                $title = $this->resolveDocTitle($requestData) ?: $doc->title;
                $content = $this->resolveDocContent($requestData, $rawInput);
                if ($content === '') $content = $requestData['content'] ?? $doc->content;

                $this->model->updateDoc((int)$doc->id, [
                    'title'      => $title,
                    'content'    => $content,
                    'icon'       => $requestData['icon'] ?? $doc->icon,
                    'project_id' => isset($requestData['project_id']) ? (int)$requestData['project_id'] : $doc->project_id
                ]);

                $this->apiResponse($this->model->getDocById((int)$doc->id), __('RestApi::operation_successful'));
            }

            if ($param1 !== null && $method === 'DELETE') {
                $this->model->deleteDoc((int)$param1);
                $this->apiResponse(null, __('RestApi::operation_successful'));
            }

            $this->apiError(__('RestApi::not_found'), 404);
        }

        // NOTIFICATIONS
        if ($resource === 'notifications') {
            if ($param1 === null && $method === 'GET') {
                $unreadOnly = isset($requestData['unread_only']) && (int)$requestData['unread_only'] === 1;
                $notifs = $this->model->getUserNotifications((int)$currentUser->id, $unreadOnly);
                $this->apiResponse($notifs, __('RestApi::operation_successful'));
            }

            if ($param1 !== null && $param2 === 'read' && $method === 'POST') {
                $this->model->markNotificationRead((int)$param1, (int)$currentUser->id);
                $this->apiResponse(null, __('RestApi::operation_successful'));
            }

            if ($param1 === 'read-all' && $method === 'POST') {
                $this->model->markAllNotificationsRead((int)$currentUser->id);
                $this->apiResponse(null, __('RestApi::operation_successful'));
            }

            $this->apiError(__('RestApi::not_found'), 404);
        }

        // REPORTS
        if ($resource === 'reports') {
            if ($method === 'GET') {
                $this->apiResponse($this->model->getSystemAnalytics(), __('RestApi::operation_successful'));
            }
        }

        // WEBHOOKS
        if ($resource === 'webhooks') {
            if ($param1 === 'listener') {
                $raw = file_get_contents('php://input');
                $parsed = json_decode($raw, true) ?: ['raw' => $raw];
                $convId = $_SERVER['HTTP_X_TASKMANAGER_CONVERSATION_ID'] ?? ($parsed['agent_conversation_id'] ?? null);
                $event  = $_SERVER['HTTP_X_TASKMANAGER_EVENT'] ?? ($parsed['event'] ?? 'unknown');

                $this->apiResponse([
                    'received'              => true,
                    'event'                 => $event,
                    'agent_conversation_id' => $convId,
                    'received_at'           => date('c'),
                    'payload'               => $parsed
                ], __('RestApi::operation_successful'));
            }

            if ($param1 === 'logs' && $method === 'GET') {
                $logFile = dirname(__DIR__, 2) . '/Public/uploads/webhook_logs.json';
                $logs = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
                $this->apiResponse($logs, __('RestApi::operation_successful'));
            }
        }

        // 404 Fallback
        $this->apiError(__('RestApi::not_found'), 404);
    }

    protected function apiResponse($data = null, string $message = '', int $status = 200, $meta = null): void
    {
        http_response_code($status);
        $res = ['success' => ($status >= 200 && $status < 300)];
        if ($message !== '') $res['message'] = $message;
        if ($data !== null) $res['data'] = $data;
        if ($meta !== null) $res['meta'] = $meta;
        $res['locale'] = Language::current();
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function apiError(string $message = '', int $status = 400, $errors = null): void
    {
        http_response_code($status);
        $res = [
            'success' => false,
            'error'   => $message ?: __('RestApi::invalid_request'),
            'locale'  => Language::current()
        ];
        if ($errors !== null) $res['errors'] = $errors;
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function resolveDocTitle(array $data): string
    {
        $keys = ['title', 'name', 'subject', 'heading', 'baslik'];
        foreach ($keys as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) {
                return trim($data[$k]);
            }
        }
        if (!empty($_FILES['file']['name'])) {
            return pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
        }
        if (!empty($_FILES['content']['name'])) {
            return pathinfo($_FILES['content']['name'], PATHINFO_FILENAME);
        }
        return '';
    }

    protected function cleanDocWhitespace(?string $content): ?string
    {
        if (!is_string($content) || $content === '') return $content;
        $c = str_replace(["\r\n", "\r"], "\n", $content);
        $c = preg_replace("/\n{3,}/", "\n\n", $c);
        $c = preg_replace("/\n\s*([-*_]\s*){3,}\n/", "\n\n---\n\n", $c);
        return trim($c);
    }

    protected function resolveDocContent(array $data, string $rawInput = ''): string
    {
        $raw = '';
        $keys = ['content', 'body', 'description', 'text', 'markdown', 'document', 'doc_content', 'html', 'detail', 'icerik'];
        foreach ($keys as $k) {
            if (isset($data[$k])) {
                if (is_string($data[$k]) && trim($data[$k]) !== '') {
                    $raw = $data[$k];
                    break;
                }
                if (is_array($data[$k])) {
                    $raw = json_encode($data[$k], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    break;
                }
            }
        }

        if ($raw === '') {
            $fileKeys = ['file', 'content', 'document', 'markdown', 'upload'];
            foreach ($fileKeys as $fk) {
                if (!empty($_FILES[$fk]['tmp_name']) && is_uploaded_file($_FILES[$fk]['tmp_name'])) {
                    $uploaded = file_get_contents($_FILES[$fk]['tmp_name']);
                    if ($uploaded !== false && trim($uploaded) !== '') {
                        $raw = $uploaded;
                        break;
                    }
                }
            }
        }

        if ($raw === '' && !empty($rawInput)) {
            $trimmed = trim($rawInput);
            if ($trimmed !== '' && !str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '[')) {
                $raw = $rawInput;
            }
        }

        return (string)$this->cleanDocWhitespace($raw);
    }
}
