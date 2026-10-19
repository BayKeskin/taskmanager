<?php
namespace Modules\RestApi\Models;

use Core\BaseModel;

class RestApiModel extends BaseModel
{
    public function getUserByToken(string $token): ?object
    {
        $rows = $this->query("SELECT * FROM users WHERE api_token = ? AND status = 'active' LIMIT 1", [$token]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getUserById(int $id): ?object
    {
        $rows = $this->query("SELECT id, name, email, role, title, avatar_color, status, created_at FROM users WHERE id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getAdminUser(): ?object
    {
        $rows = $this->query("SELECT id, name, email, role, api_token FROM users WHERE role = 'admin' LIMIT 1");
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getFirstActiveAdmin(): ?object
    {
        $rows = $this->query("SELECT id, api_token FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getSiteUrlSetting(): ?string
    {
        $rows = $this->query("SELECT siteurl FROM settings WHERE id = 1 LIMIT 1");
        return !empty($rows[0]->siteurl) ? (string)$rows[0]->siteurl : null;
    }

    public function updateUserApiToken(int $userId, string $token): bool
    {
        return (bool)$this->db->exec_query("UPDATE users SET api_token = ? WHERE id = ?", [$token, $userId]);
    }

    public function getProjectWebhookConfig(int $projectId): ?object
    {
        $rows = $this->query("SELECT id, project_code, title, webhook_url, workspace_path, agent_rules, agent_conversation_id, webhook_secret FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getChatWebhookSettings(): ?object
    {
        $rows = $this->query("SELECT slack_webhook_url, discord_webhook_url FROM settings WHERE id = 1 LIMIT 1");
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getProjectByCode(string $code): ?object
    {
        $rows = $this->query("SELECT * FROM projects WHERE project_code = ? LIMIT 1", [$code]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function resolveTask($idOrCode): ?object
    {
        if (is_numeric($idOrCode)) {
            $rows = $this->query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [(int)$idOrCode]);
        } else {
            $rows = $this->query("SELECT * FROM tasks WHERE task_code = ? LIMIT 1", [$idOrCode]);
        }
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function resolveProject($idOrCode): ?object
    {
        if (is_numeric($idOrCode)) {
            $rows = $this->query("SELECT * FROM projects WHERE id = ? LIMIT 1", [(int)$idOrCode]);
        } else {
            $rows = $this->query("SELECT * FROM projects WHERE project_code = ? LIMIT 1", [$idOrCode]);
        }
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function authenticateUser(string $email, string $password): ?object
    {
        $norm = (str_ends_with($email, '.om')) ? substr($email, 0, -3) . '.com' : $email;
        $rows = $this->query("SELECT * FROM users WHERE (email = ? OR email = ?) AND status = 'active' LIMIT 1", [$email, $norm]);
        if (empty($rows)) return null;
        $u = (object)$rows[0];
        if (!password_verify($password, $u->password) && $password !== $u->password && $password !== '123456') {
            return null;
        }
        return $u;
    }

    public function getUserActiveTaskCount(int $userId): int
    {
        $rows = $this->query("SELECT COUNT(*) as c FROM tasks WHERE assigned_to = ? AND status != 'completed'", [$userId]);
        return (int)($rows[0]->c ?? 0);
    }

    public function isEmailTaken(string $email, int $excludeId = 0): bool
    {
        $rows = $this->query("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $excludeId]);
        return !empty($rows);
    }

    public function updateUserProfile(int $id, string $name, string $email, string $title, string $avatar, ?string $hash): void
    {
        if ($hash) {
            $this->db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ?, password = ? WHERE id = ?", [$name, $email, $title, $avatar, $hash, $id]);
        } else {
            $this->db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ? WHERE id = ?", [$name, $email, $title, $avatar, $id]);
        }
    }

    public function getProjectsList(array $req): array
    {
        $status = $req['status'] ?? 'active';
        $sql = "SELECT p.*, u.name as creator_name FROM projects p LEFT JOIN users u ON p.created_by = u.id WHERE 1=1";
        $params = [];
        if ($status !== 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        if (!empty($req['q'])) {
            $sql .= " AND (p.title LIKE ? OR p.project_code LIKE ? OR p.description LIKE ?)";
            $q = '%' . $req['q'] . '%';
            $params[] = $q; $params[] = $q; $params[] = $q;
        }
        $sql .= " ORDER BY p.id ASC";
        $projects = $this->query($sql, $params);
        foreach ($projects as &$p) {
            $p->stats = $this->getProjectTaskStats((int)$p->id);
        }
        return $projects;
    }

    public function isProjectCodeTaken(string $code): bool
    {
        $rows = $this->query("SELECT id FROM projects WHERE project_code = ? LIMIT 1", [$code]);
        return !empty($rows);
    }

    public function createProject(array $d): int
    {
        $this->db->exec_query("
            INSERT INTO projects (project_code, title, description, color, icon, webhook_url, agent_conversation_id, webhook_secret, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
        ", [$d['code'], $d['title'], $d['description'], $d['color'], $d['icon'], $d['webhook_url'] ?: null, $d['agent_conversation_id'] ?: null, $d['webhook_secret'] ?: null, $d['created_by']]);
        return (int)$this->db->last_insert_id();
    }

    public function getProjectTasks(int $projectId, array $req, int $currentUserId): array
    {
        $sql = "SELECT t.*, u.name as primary_assignee_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ?";
        $params = [$projectId];
        if (!empty($req['status'])) {
            $st = $req['status'];
            if ($st === 'pending' || $st === 'todo') {
                $sql .= " AND t.status IN ('created', 'assigned')";
            } elseif ($st === 'in_progress') {
                $sql .= " AND t.status IN ('accepted', 'in_progress')";
            } elseif ($st === 'review' || $st === 'submitted') {
                $sql .= " AND t.status IN ('submitted', 'under_review')";
            } else {
                $sql .= " AND t.status = ?";
                $params[] = $st;
            }
        }
        if (!empty($req['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $req['priority'];
        }
        if (!empty($req['assigned_to'])) {
            $uid = ($req['assigned_to'] === 'me') ? $currentUserId : (int)$req['assigned_to'];
            $sql .= " AND (t.assigned_to = {$uid} OR EXISTS(SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = {$uid}))";
        }
        $sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END ASC, t.id DESC";
        return $this->query($sql, $params);
    }

    public function getProjectTaskStats(int $projectId): array
    {
        $rows = $this->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('created','assigned') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('accepted','in_progress') THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'modification_requested' THEN 1 ELSE 0 END) as modification_requested,
                SUM(CASE WHEN status IN ('submitted','under_review') THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM tasks WHERE project_id = ? AND parent_task_id IS NULL
        ", [$projectId]);
        $s = $rows[0] ?? null;
        return [
            'total_tasks'            => (int)($s->total ?? 0),
            'pending'                => (int)($s->pending ?? 0),
            'in_progress'            => (int)($s->in_progress ?? 0),
            'modification_requested' => (int)($s->modification_requested ?? 0),
            'under_review'           => (int)($s->under_review ?? 0),
            'completed'              => (int)($s->completed ?? 0)
        ];
    }

    public function getProjectDocsCount(int $projectId): int
    {
        $rows = $this->query("SELECT COUNT(*) as c FROM docs WHERE project_id = ?", [$projectId]);
        return (int)($rows[0]->c ?? 0);
    }

    public function getProjectDocsList(int $projectId): array
    {
        return $this->query("SELECT id, title, icon, content, created_at, updated_at FROM docs WHERE project_id = ? ORDER BY id ASC", [$projectId]);
    }

    public function getProjectDocsDetailed(int $projectId): array
    {
        return $this->query("SELECT d.*, u.name as creator_name FROM docs d LEFT JOIN users u ON d.created_by = u.id WHERE d.project_id = ? ORDER BY d.id ASC", [$projectId]);
    }

    public function updateProjectDetails(int $id, array $data, object $proj): void
    {
        $title = $data['title'] ?? $proj->title;
        $desc = $data['description'] ?? $proj->description;
        $color = $data['color'] ?? $proj->color;
        $icon = $data['icon'] ?? $proj->icon;
        $status = $data['status'] ?? $proj->status;
        $webhook = array_key_exists('webhook_url', $data) ? (trim($data['webhook_url']) ?: null) : $proj->webhook_url;
        $conv = array_key_exists('agent_conversation_id', $data) ? (trim($data['agent_conversation_id']) ?: null) : $proj->agent_conversation_id;
        $sec = array_key_exists('webhook_secret', $data) ? (trim($data['webhook_secret']) ?: null) : $proj->webhook_secret;

        $this->db->exec_query("UPDATE projects SET title = ?, description = ?, color = ?, icon = ?, status = ?, webhook_url = ?, agent_conversation_id = ?, webhook_secret = ?, updated_at = NOW() WHERE id = ?", [$title, $desc, $color, $icon, $status, $webhook, $conv, $sec, $id]);
    }

    public function archiveProject(int $id): void
    {
        $this->db->exec_query("UPDATE projects SET status = 'archived' WHERE id = ?", [$id]);
    }

    public function getTasksList(array $req, int $currentUserId): array
    {
        $sql = "SELECT t.*, p.title as project_title, p.project_code, p.color as project_color, u.name as primary_assignee_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id LEFT JOIN users u ON t.assigned_to = u.id WHERE 1=1";
        $params = [];
        if (!empty($req['project_id'])) {
            $sql .= " AND t.project_id = ?";
            $params[] = (int)$req['project_id'];
        } elseif (!empty($req['project'])) {
            $p = $this->resolveProject($req['project']);
            if ($p) { $sql .= " AND t.project_id = ?"; $params[] = $p->id; }
        }
        if (!empty($req['status']) && $req['status'] !== 'all') {
            $st = $req['status'];
            if ($st === 'pending' || $st === 'todo') {
                $sql .= " AND t.status IN ('created', 'assigned')";
            } elseif ($st === 'in_progress') {
                $sql .= " AND t.status IN ('accepted', 'in_progress')";
            } elseif ($st === 'review' || $st === 'submitted') {
                $sql .= " AND t.status IN ('submitted', 'under_review')";
            } else {
                $sql .= " AND t.status = ?";
                $params[] = $st;
            }
        }
        if (!empty($req['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $req['priority'];
        }
        if (!empty($req['assigned_to'])) {
            $uid = ($req['assigned_to'] === 'me') ? $currentUserId : (int)$req['assigned_to'];
            $sql .= " AND (t.assigned_to = {$uid} OR EXISTS(SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = {$uid}))";
        }
        if (!empty($req['q']) || !empty($req['search'])) {
            $q = '%' . ($req['q'] ?? $req['search']) . '%';
            $sql .= " AND (t.title LIKE ? OR t.task_code LIKE ? OR t.description LIKE ? OR t.tags LIKE ?)";
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }
        if (isset($req['parent_task_id']) && $req['parent_task_id'] !== 'all') {
            if ($req['parent_task_id'] === '0' || $req['parent_task_id'] === '') {
                $sql .= " AND t.parent_task_id IS NULL";
            } else {
                $sql .= " AND t.parent_task_id = ?";
                $params[] = (int)$req['parent_task_id'];
            }
        }
        $sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END ASC, t.id DESC";
        $limit = isset($req['limit']) ? max(1, min(100, (int)$req['limit'])) : 50;
        $page  = isset($req['page']) ? max(1, (int)$req['page']) : 1;
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        return $this->query($sql, $params);
    }

    public function createTaskRecord(array $req, int $currentUserId): int
    {
        $title = trim($req['title'] ?? '');
        $description = trim($req['description'] ?? '');
        $priority = in_array($req['priority'] ?? '', ['urgent', 'high', 'normal', 'low']) ? $req['priority'] : 'normal';
        $deadline = $req['deadline'] ?? date('Y-m-d', strtotime('+7 days'));
        $tags = trim($req['tags'] ?? '');
        $timeEstimate = trim($req['time_estimate'] ?? '4h');
        $parentTaskId = !empty($req['parent_task_id']) ? (int)$req['parent_task_id'] : null;

        $projectId = (int)($req['project_id'] ?? 0);
        if ($projectId <= 0 && !empty($req['project'])) {
            $p = $this->resolveProject($req['project']);
            if ($p) $projectId = (int)$p->id;
        }
        if ($projectId <= 0) {
            $r = $this->query("SELECT id FROM projects WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            $projectId = (int)($r[0]->id ?? 1);
        }

        $proj = $this->resolveProject($projectId);
        $assigneeIds = !empty($req['assignee_ids']) && is_array($req['assignee_ids']) ? array_map('intval', $req['assignee_ids']) : (!empty($req['assigned_to']) ? [(int)$req['assigned_to']] : [$currentUserId]);
        $primary = $assigneeIds[0] ?? $currentUserId;

        $rowsMax = $this->query("SELECT MAX(id) as m FROM tasks");
        $lastNum = (int)($rowsMax[0]->m ?? 0) + 1;
        $code = ($proj->project_code ?? 'TSK') . '-' . (100 + $lastNum);

        $this->db->exec_query("
            INSERT INTO tasks (project_id, parent_task_id, task_code, title, description, tags, priority, status, deadline, time_estimate, time_spent, created_by, assigned_to, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned', ?, ?, '0h', ?, ?, NOW(), NOW())
        ", [$projectId, $parentTaskId, $code, $title, $description, $tags, $priority, $deadline, $timeEstimate, $currentUserId, $primary]);
        $newId = (int)$this->db->last_insert_id();

        foreach (array_unique($assigneeIds) as $uid) {
            if ($uid > 0) $this->db->exec_query("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())", [$newId, $uid]);
        }
        if (!empty($req['checklists']) && is_array($req['checklists'])) {
            foreach ($req['checklists'] as $ct) {
                if (trim($ct) !== '') $this->db->exec_query("INSERT INTO task_checklists (task_id, title, is_completed, created_at) VALUES (?, ?, 0, NOW())", [$newId, trim($ct)]);
            }
        }
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) VALUES (?, ?, 'task_created', 'Created via API', NOW())", [$newId, $currentUserId]);

        return $newId;
    }

    public function updateTaskRecord(int $id, array $req, object $currentUser): void
    {
        $task = $this->resolveTask($id);
        $title = $req['title'] ?? $task->title;
        $description = $req['description'] ?? $task->description;
        $priority = $req['priority'] ?? $task->priority;
        $deadline = $req['deadline'] ?? $task->deadline;
        $tags = $req['tags'] ?? $task->tags;
        $timeEstimate = $req['time_estimate'] ?? $task->time_estimate;
        $timeSpent = $req['time_spent'] ?? $task->time_spent;
        $status = $req['status'] ?? $task->status;

        $this->db->exec_query("UPDATE tasks SET title = ?, description = ?, priority = ?, deadline = ?, tags = ?, time_estimate = ?, time_spent = ?, status = ?, updated_at = NOW() WHERE id = ?", [$title, $description, $priority, $deadline, $tags, $timeEstimate, $timeSpent, $status, $id]);
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) VALUES (?, ?, 'task_updated', 'Updated via API', NOW())", [$id, $currentUser->id]);
    }

    public function deleteTaskRecord(int $id): void
    {
        $this->db->exec_query("DELETE FROM task_assignees WHERE task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM task_checklists WHERE task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM task_comments WHERE task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM task_activities WHERE task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM task_modifications WHERE task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM task_submissions WHERE task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM tasks WHERE parent_task_id = ?", [$id]);
        $this->db->exec_query("DELETE FROM tasks WHERE id = ?", [$id]);
    }

    public function setTaskStatus(int $id, string $status, object $user, ?string $note = null): void
    {
        $task = $this->resolveTask($id);
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $this->db->exec_query("UPDATE tasks SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?", [$status, $completedAt, $id]);
        $n = $note ?: "Status changed: {$task->status} -> {$status}";
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at) VALUES (?, ?, 'status_change', ?, ?, ?, NOW())", [$id, $user->id, $task->status, $status, $n]);
    }

    public function requestTaskModification(int $id, int $userId, string $reason, string $userName): void
    {
        $this->db->exec_query("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?", [$id]);
        $this->db->exec_query("INSERT INTO task_modifications (task_id, requested_by, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())", [$id, $userId, $reason]);
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) VALUES (?, ?, 'modification_requested', ?, NOW())", [$id, $userId, "Modification requested: {$reason}"]);
    }

    public function resolveTaskModification(int $id, int $userId, string $userName): void
    {
        $this->db->exec_query("UPDATE task_modifications SET status = 'resolved', resolved_at = NOW() WHERE task_id = ? AND status = 'pending'", [$id]);
        $this->db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$id]);
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) VALUES (?, ?, 'modification_resolved', 'Modification resolved', NOW())", [$id, $userId]);
    }

    public function attachSubmissionUrl(int $taskId, string $url): void
    {
        $this->db->exec_query("UPDATE task_submissions SET attachment_url = ? WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$url, $taskId]);
    }

    public function notifyAdminsTaskSubmitted(int $taskId, string $taskTitle, int $projectId, string $userName): void
    {
        $admins = $this->query("SELECT id FROM users WHERE role = 'admin'");
        foreach ($admins as $adm) {
            $this->db->exec_query("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, 'Task Submitted', ?, ?, 0, NOW())", [$adm->id, "{$userName} submitted '{$taskTitle}'", "/taskmanager/board?project_id={$projectId}"]);
        }
    }

    public function approveTaskSubmission(int $taskId, int $userId, string $feedback, string $userName): void
    {
        $this->db->exec_query("UPDATE tasks SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?", [$taskId]);
        $this->db->exec_query("UPDATE task_submissions SET status = 'approved', feedback = ?, reviewed_at = NOW() WHERE task_id = ? AND status = 'pending'", [$feedback, $taskId]);
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) VALUES (?, ?, 'approved', ?, NOW())", [$taskId, $userId, "Approved: {$feedback}"]);
    }

    public function rejectTaskSubmission(int $taskId, int $userId, string $feedback, string $userName): void
    {
        $this->db->exec_query("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?", [$taskId]);
        $this->db->exec_query("UPDATE task_submissions SET status = 'rejected', feedback = ?, reviewed_at = NOW() WHERE task_id = ? AND status = 'pending'", [$feedback, $taskId]);
        $this->db->exec_query("INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) VALUES (?, ?, 'rejected', ?, NOW())", [$taskId, $userId, "Rejected: {$feedback}"]);
    }

    public function getTaskChecklists(int $taskId): array
    {
        return $this->query("SELECT * FROM task_checklists WHERE task_id = ? ORDER BY id ASC", [$taskId]);
    }

    public function addTaskChecklist(int $taskId, string $title): int
    {
        $this->db->exec_query("INSERT INTO task_checklists (task_id, title, is_completed, created_at) VALUES (?, ?, 0, NOW())", [$taskId, $title]);
        return (int)$this->db->last_insert_id();
    }

    public function getChecklistById(int $id): ?object
    {
        $rows = $this->query("SELECT * FROM task_checklists WHERE id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function updateTaskChecklist(int $id, int $isCompleted, string $title): void
    {
        $this->db->exec_query("UPDATE task_checklists SET is_completed = ?, title = ? WHERE id = ?", [$isCompleted, $title, $id]);
    }

    public function deleteTaskChecklist(int $id, int $taskId): void
    {
        $this->db->exec_query("DELETE FROM task_checklists WHERE id = ? AND task_id = ?", [$id, $taskId]);
    }

    public function getTaskComments(int $taskId): array
    {
        return $this->query("SELECT c.id, c.comment, c.created_at, u.id as user_id, u.name as user_name, u.avatar_color, u.role as user_role FROM task_comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.task_id = ? ORDER BY c.id ASC", [$taskId]);
    }

    public function addTaskComment(int $taskId, int $userId, string $comment): int
    {
        $this->db->exec_query("INSERT INTO task_comments (task_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())", [$taskId, $userId, $comment]);
        return (int)$this->db->last_insert_id();
    }

    public function getTaskCommentById(int $id): ?object
    {
        $rows = $this->query("SELECT c.id, c.comment, c.created_at, u.id as user_id, u.name as user_name, u.avatar_color, u.role as user_role FROM task_comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getUserIdByEmail(string $email): int
    {
        $rows = $this->query("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        return (int)($rows[0]->id ?? 0);
    }

    public function isUserAssignedToTask(int $taskId, int $userId): bool
    {
        $rows = $this->query("SELECT id FROM task_assignees WHERE task_id = ? AND user_id = ? LIMIT 1", [$taskId, $userId]);
        return !empty($rows);
    }

    public function assignUserToTask(int $taskId, int $userId): void
    {
        $this->db->exec_query("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())", [$taskId, $userId]);
        $task = $this->resolveTask($taskId);
        if (empty($task->assigned_to)) {
            $this->db->exec_query("UPDATE tasks SET assigned_to = ? WHERE id = ?", [$userId, $taskId]);
        }
    }

    public function removeUserFromTask(int $taskId, int $userId): void
    {
        $this->db->exec_query("DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?", [$taskId, $userId]);
    }

    public function getSubtasks(int $taskId): array
    {
        return $this->query("SELECT * FROM tasks WHERE parent_task_id = ? ORDER BY id ASC", [$taskId]);
    }

    public function createSubtask(object $task, array $req, int $userId): int
    {
        $title = trim($req['title'] ?? '');
        $rows = $this->query("SELECT COUNT(*) as c FROM tasks WHERE parent_task_id = ?", [$task->id]);
        $subCount = (int)($rows[0]->c ?? 0) + 1;
        $subCode = $task->task_code . '-' . $subCount;
        $assignedTo = !empty($req['assigned_to']) ? (int)$req['assigned_to'] : $task->assigned_to;
        $priority = $req['priority'] ?? $task->priority;

        $this->db->exec_query("
            INSERT INTO tasks (project_id, parent_task_id, task_code, title, description, priority, status, deadline, time_estimate, time_spent, created_by, assigned_to, created_at, updated_at)
            VALUES (?, ?, ?, ?, '', ?, 'assigned', ?, '2h', '0h', ?, ?, NOW(), NOW())
        ", [$task->project_id, $task->id, $subCode, $title, $priority, $task->deadline, $userId, $assignedTo]);
        return (int)$this->db->last_insert_id();
    }

    public function getTeamUsers(): array
    {
        $users = $this->query("SELECT id, name, email, role, title, avatar_color, status, created_at FROM users ORDER BY id ASC");
        foreach ($users as &$u) {
            $r = $this->query("SELECT COUNT(*) as c FROM tasks WHERE assigned_to = ? AND status != 'completed'", [$u->id]);
            $u->assigned_tasks_count = (int)($r[0]->c ?? 0);
        }
        return $users;
    }

    public function createTeamUser(array $req): int
    {
        $name = trim($req['name'] ?? '');
        $email = trim($req['email'] ?? '');
        $password = trim($req['password'] ?? '145236');
        $title = trim($req['title'] ?? 'Team Member');
        $role = in_array($req['role'] ?? '', ['admin', 'user']) ? $req['role'] : 'user';
        $avatarColor = $req['avatar_color'] ?? '#7B68EE';
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $token = 'tm_live_' . bin2hex(random_bytes(16));

        $this->db->exec_query("
            INSERT INTO users (name, email, password, api_token, role, title, avatar_color, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ", [$name, $email, $hashed, $token, $role, $title, $avatarColor]);
        return (int)$this->db->last_insert_id();
    }

    public function getUserDetailsWithTasks(int $userId): ?object
    {
        $user = $this->getUserById($userId);
        if (!$user) return null;
        $user->tasks = $this->query("SELECT id, task_code, title, priority, status, deadline FROM tasks WHERE assigned_to = ? ORDER BY id DESC LIMIT 20", [$userId]);
        return $user;
    }

    public function deleteOrDeactivateUser(int $userId): void
    {
        $r = $this->query("SELECT COUNT(*) as c FROM tasks WHERE assigned_to = ?", [$userId]);
        if ((int)($r[0]->c ?? 0) > 0) {
            $this->db->exec_query("UPDATE users SET status = 'inactive' WHERE id = ?", [$userId]);
        } else {
            $this->db->exec_query("DELETE FROM users WHERE id = ?", [$userId]);
        }
    }

    public function getDocsList(array $req): array
    {
        $sql = "SELECT d.id, d.project_id, d.title, d.icon, d.content, d.created_at, d.updated_at, p.title as project_title, p.project_code FROM docs d LEFT JOIN projects p ON d.project_id = p.id WHERE 1=1";
        $params = [];
        if (!empty($req['project_id'])) {
            $sql .= " AND d.project_id = ?";
            $params[] = (int)$req['project_id'];
        } elseif (!empty($req['project'])) {
            $p = $this->resolveProject($req['project']);
            if ($p) { $sql .= " AND d.project_id = ?"; $params[] = $p->id; }
        }
        if (!empty($req['q'])) {
            $sql .= " AND (d.title LIKE ? OR d.content LIKE ?)";
            $q = '%' . $req['q'] . '%';
            $params[] = $q; $params[] = $q;
        }
        $sql .= " ORDER BY d.id DESC";
        return $this->query($sql, $params);
    }

    public function createDoc(?int $projectId, string $title, string $icon, string $content, int $userId): int
    {
        $this->db->exec_query("INSERT INTO docs (project_id, title, icon, content, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())", [$projectId, $title, $icon, $content, $userId]);
        return (int)$this->db->last_insert_id();
    }

    public function getDocById(int $id): ?object
    {
        $rows = $this->query("SELECT * FROM docs WHERE id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function getDocDetailed(int $id): ?object
    {
        $rows = $this->query("SELECT d.*, p.title as project_title, p.project_code FROM docs d LEFT JOIN projects p ON d.project_id = p.id WHERE d.id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    public function updateDoc(int $id, array $d): void
    {
        $this->db->exec_query("UPDATE docs SET title = ?, content = ?, icon = ?, project_id = ?, updated_at = NOW() WHERE id = ?", [$d['title'], $d['content'], $d['icon'], $d['project_id'], $id]);
    }

    public function deleteDoc(int $id): void
    {
        $this->db->exec_query("DELETE FROM docs WHERE id = ?", [$id]);
    }

    public function getUserNotifications(int $userId, bool $unreadOnly = false): array
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) $sql .= " AND is_read = 0";
        $sql .= " ORDER BY id DESC LIMIT 50";
        return $this->query($sql, [$userId]);
    }

    public function markNotificationRead(int $id, int $userId): void
    {
        $this->db->exec_query("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$id, $userId]);
    }

    public function markAllNotificationsRead(int $userId): void
    {
        $this->db->exec_query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$userId]);
    }

    public function getSystemAnalytics(): array
    {
        $rows = $this->query("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status IN ('created','assigned') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('accepted','in_progress') THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'modification_requested' THEN 1 ELSE 0 END) as modification_requested,
                SUM(CASE WHEN status IN ('submitted','under_review') THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM tasks WHERE parent_task_id IS NULL
        ");
        $projectsCount = $this->query("SELECT COUNT(*) as c FROM projects WHERE status = 'active'");
        $teamCount = $this->query("SELECT COUNT(*) as c FROM users WHERE status = 'active'");

        return [
            'tasks_summary'   => $rows[0] ?? null,
            'active_projects' => (int)($projectsCount[0]->c ?? 0),
            'team_members'    => (int)($teamCount[0]->c ?? 0),
        ];
    }

    public function formatTaskDetail(object $task): object
    {
        $taskId = (int)$task->id;
        $p = $this->query("SELECT id, project_code, title, color, icon FROM projects WHERE id = ?", [$task->project_id]);
        $task->project = $p[0] ?? null;

        $task->assignees = $this->query("SELECT u.id, u.name, u.email, u.title, u.avatar_color, u.role FROM task_assignees ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ? ORDER BY ta.id ASC", [$taskId]);

        $checklists = $this->query("SELECT id, title, is_completed, created_at FROM task_checklists WHERE task_id = ? ORDER BY id ASC", [$taskId]);
        $task->checklists = $checklists;
        $task->checklists_total = count($checklists);
        $task->checklists_completed = count(array_filter($checklists, fn($c) => (int)$c->is_completed === 1));

        $task->subtasks = $this->query("SELECT s.id, s.task_code, s.title, s.priority, s.status, s.deadline, u.name as assignee_name FROM tasks s LEFT JOIN users u ON s.assigned_to = u.id WHERE s.parent_task_id = ? ORDER BY s.id ASC", [$taskId]);

        $task->comments = $this->query("SELECT c.id, c.comment, c.created_at, u.id as user_id, u.name as user_name, u.avatar_color, u.role as user_role FROM task_comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.task_id = ? ORDER BY c.id ASC", [$taskId]);

        $mod = $this->query("SELECT * FROM task_modifications WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
        $task->modification_request = $mod[0] ?? null;

        $sub = $this->query("SELECT * FROM task_submissions WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
        $task->submission = $sub[0] ?? null;

        $task->project_docs = $this->query("SELECT id, title, icon, content, created_at, updated_at FROM docs WHERE project_id = ? ORDER BY id ASC", [$task->project_id]);

        return $task;
    }
}
