<?php
namespace Modules\Board\Controllers;

use Core\BaseController;
use Modules\Board\Models\TaskModel;
use Modules\Projects\Models\ProjectModel;
use Modules\Team\Models\UserModel;
use Modules\Settings\Models\SettingsModel;

class BoardController extends BaseController
{
    private TaskModel $taskModel;
    private ProjectModel $projectModel;
    private UserModel $userModel;
    private SettingsModel $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->taskModel = new TaskModel();
        $this->projectModel = new ProjectModel();
        $this->userModel = new UserModel();
        $this->settingsModel = new SettingsModel();
    }

    /**
     * Kanban panosu verilerini hazırlar
     */
    public function index(?int $activeProjectId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $projectInfo = $activeProjectId ? $this->projectModel->getProjectById($activeProjectId) : null;
        $sysSettings = $this->settingsModel->getSettings();

        $employeeCanCreateTasks = (int)($sysSettings->employee_can_create_tasks ?? 1);
        $employeeCanAiTasks = (int)($sysSettings->employee_can_ai_tasks ?? 0);
        $canCreateTask = ($currentUser->role === 'admin') || (!$isClient && $employeeCanCreateTasks);
        $canUseAiTasks = ($currentUser->role === 'admin') || (!$isClient && $employeeCanAiTasks);

        $allUsers = $this->userModel->getUsersForFilter();
        $allTasks = $this->taskModel->getTasksForBoard($activeProjectId);
        $assigneesMap = $this->taskModel->getAssigneesMap();
        $attachmentCounts = $this->taskModel->getAttachmentCounts();

        // JS Client-Side i18n & Context köprüsü (Inline script bloğu yerine AssetManager üzerinden)
        \Core\AssetManager::localizeScript('board-js', 'BOARD_CONTEXT', [
            'projectId' => $activeProjectId ? (string)$activeProjectId : null,
            'userEmail' => $currentUser->email ?? ''
        ]);
        \Core\AssetManager::localizeScript('board-js', 'BOARD_I18N', [
            'approve_confirm' => __('Board::client_approve_confirm'),
            'approve_success' => __('Board::client_approve_success'),
            'request_prompt'  => __('Board::client_request_reason_prompt'),
            'request_success' => __('Board::client_request_success'),
            'analyzing'       => __('Board::ai_standup_analyzing'),
            'period'          => __('Board::ai_standup_period_label'),
            'last_24h'        => __('Board::ai_standup_last_24h'),
            'comp_stat'       => __('Board::ai_standup_completed_stat'),
            'in_prog_stat'    => __('Board::ai_standup_in_progress_stat'),
            'delayed_stat'    => __('Board::ai_standup_delayed_stat'),
            'email_prompt'    => __('Board::ai_standup_email_prompt'),
            'email_success'   => __('Board::ai_standup_email_success'),
            'err_fetch'       => __('Board::ai_standup_err_fetch'),
            'err_conn'        => __('Board::ai_standup_err_conn'),
            'server_error'    => __('Board::server_error'),
            'error_prefix'    => __('Board::error_prefix'),
            'status_accepted' => __('Board::status_accepted'),
            'status_modification_requested' => __('Board::status_modification_requested'),
            'status_in_progress' => __('Board::status_in_progress'),
            'status_submitted' => __('Board::status_submitted'),
            'status_under_review' => __('Board::status_under_review'),
            'priority_high' => __('Board::priority_high'),
            'priority_low' => __('Board::priority_low'),
            'loading' => __('Board::loading'),
            'loading_details' => __('Board::loading_details'),
            'comments_loading' => __('Board::comments_loading'),
            'err_task_details' => __('Board::err_task_details'),
            'back_to_parent' => __('Board::back_to_parent'),
            'desc_empty_hint' => __('Board::desc_empty_hint'),
            'click_to_edit' => __('Board::click_to_edit'),
            'unassigned' => __('Board::unassigned'),
            'not_specified' => __('Board::not_specified'),
            'notes_revision_title' => __('Board::notes_revision_title'),
            'notes_submission_title' => __('Board::notes_submission_title'),
            'link_open_submission' => __('Board::link_open_submission'),
            'no_db_changes' => __('Board::no_db_changes'),
            'reverted' => __('Board::reverted'),
            'db_impact' => __('Board::db_impact'),
            'view_diff' => __('Board::view_diff'),
            'btn_rollback' => __('Board::btn_rollback'),
            'btn_accept_task' => __('Board::btn_accept_task'),
            'btn_request_mod' => __('Board::btn_request_mod'),
            'btn_submit_task' => __('Board::btn_submit_task'),
            'btn_approve_task' => __('Board::btn_approve_task'),
            'btn_request_changes' => __('Board::btn_request_changes'),
            'comment_sent' => __('Board::comment_sent'),
            'task_moved' => __('Board::task_moved'),
            'err_status_update' => __('Board::err_status_update'),
            'action_success' => __('Board::action_success'),
            'action_failed' => __('Board::action_failed'),
            'err_request' => __('Board::err_request'),
            'remove' => __('Board::remove'),
            'ai_prompt_required' => __('Board::ai_prompt_required'),
            'ai_btn_generate' => __('Board::ai_btn_generate'),
            'ai_spec_success' => __('Board::ai_spec_success'),
            'ai_spec_failed' => __('Board::ai_spec_failed'),
            'task_created' => __('Board::task_created'),
            'err_task_create' => __('Board::err_task_create'),
            'no_checklists' => __('Board::no_checklists'),
            'item_completed' => __('Board::item_completed'),
            'item_reverted' => __('Board::item_reverted'),
            'checklist_added' => __('Board::checklist_added'),
            'title_updated' => __('Board::title_updated'),
            'no_subtasks' => __('Board::no_subtasks'),
            'mark_done' => __('Board::mark_done'),
            'delete_subtask' => __('Board::delete_subtask'),
            'subtask_created' => __('Board::subtask_created'),
            'err_subtask_create' => __('Board::err_subtask_create'),
            'btn_start_timer' => __('Board::btn_start_timer'),
            'btn_stop_timer' => __('Board::btn_stop_timer'),
            'timer_started' => __('Board::timer_started'),
            'err_save_time' => __('Board::err_save_time'),
            'no_dependencies' => __('Board::no_dependencies'),
            'select_task' => __('Board::select_task'),
            'select_task_error' => __('Board::select_task_error'),
            'dependency_removed' => __('Board::dependency_removed'),
            'filters_reset' => __('Board::filters_reset'),
            'subtask_title_required' => __('Board::subtask_title_required'),
            'unsaved_changes' => __('Board::unsaved_changes'),
            'desc_updated' => __('Board::desc_updated'),
            'err_desc_save' => __('Board::err_desc_save'),
            'confirm_delete_task_all' => __('Board::confirm_delete_task_all'),
            'confirm_delete_task' => __('Board::confirm_delete_task'),
            'err_task_delete' => __('Board::err_task_delete'),
            'confirm_delete_subtask' => __('Board::confirm_delete_subtask'),
            'subtask_deleted' => __('Board::subtask_deleted'),
            'item_deleted' => __('Board::item_deleted'),
            'no_assignee_selected' => __('Board::no_assignee_selected'),
            'no_more_assignees' => __('Board::no_more_assignees'),
            'no_task_assignees' => __('Board::no_task_assignees'),
            'remove_assignee' => __('Board::remove_assignee'),
            'err_assign' => __('Board::err_assign'),
            'err_unassign' => __('Board::err_unassign'),
            'ai_autonomous_active' => __('Board::ai_autonomous_active'),
            'remove_ai_tag' => __('Board::remove_ai_tag'),
            'ai_title_remove' => __('Board::ai_title_remove'),
            'ai_title_enable' => __('Board::ai_title_enable'),
            'remove_tag' => __('Board::remove_tag'),
            'tags_updated' => __('Board::tags_updated'),
            'ai_queue_hint' => __('Board::ai_queue_hint'),
            'switch_dark' => __('Board::switch_dark'),
            'switch_light' => __('Board::switch_light'),
            'theme_light' => __('Board::theme_light'),
            'theme_dark' => __('Board::theme_dark'),
            'theme_light_active' => __('Board::theme_light_active'),
            'theme_dark_active' => __('Board::theme_dark_active'),
            'delete_file' => __('Board::delete_file'),
            'select_task_first' => __('Board::select_task_first'),
            'file_uploading' => __('Board::file_uploading'),
            'file_uploaded' => __('Board::file_uploaded'),
            'err_upload' => __('Board::err_upload'),
            'confirm_delete_attachment' => __('Board::confirm_delete_attachment'),
            'file_deleted' => __('Board::file_deleted'),
            'screenshot_added_ai' => __('Board::screenshot_added_ai'),
            'screenshot_added_task' => __('Board::screenshot_added_task'),
            'screenshot_added_sub' => __('Board::screenshot_added_sub'),
            'screenshot_added_comment' => __('Board::screenshot_added_comment'),
            'screenshot_uploaded' => __('Board::screenshot_uploaded'),
            'no_comments_yet' => __('Board::no_comments_yet'),
            'internal_team' => __('Board::internal_team'),
            'click_to_zoom' => __('Board::click_to_zoom'),
            'edit_comment' => __('Board::edit_comment'),
            'btn_edit' => __('Board::btn_edit'),
            'edited' => __('Board::edited'),
            'member' => __('Board::member'),
            'err_comment_empty' => __('Board::err_comment_empty'),
            'comment_updated' => __('Board::comment_updated'),
            'err_comment_update' => __('Board::err_comment_update'),
            'no_activity_records' => __('Board::no_activity_records'),
            'user' => __('Board::user'),
        ]);

        $ghCreds = [];
        if (!$isClient && class_exists('\Modules\GithubDevops\Services\GitService')) {
            \Modules\GithubDevops\Services\GitService::ensureGithubAndRollbackSchema();
            $ghCreds = \Modules\GithubDevops\Services\GitService::getGithubCredentials();
        }

        return [
            'currentUser'            => $currentUser,
            'isClient'               => $isClient,
            'activeProjectId'        => $activeProjectId,
            'projectInfo'            => $projectInfo,
            'sysSettings'            => $sysSettings,
            'canCreateTask'          => $canCreateTask,
            'canUseAiTasks'          => $canUseAiTasks,
            'allUsers'               => $allUsers,
            'allTasks'               => $allTasks,
            'assigneesMap'           => $assigneesMap,
            'attachmentCounts'       => $attachmentCounts,
            'ghCreds'                => $ghCreds,
        ];
    }
}
