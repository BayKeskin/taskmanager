<?php
/**
 * English Language File - Dispatcher Module
 */
return [
    'module_name'          => 'Autonomous AI Bridge',
    'module_desc'          => 'Antigravity Language Server and task dispatching engine.',
    'nav_menu_title'       => 'Dispatcher',
    'dispatcher_running'   => 'Dispatcher Running (Port 4000)',
    'dispatcher_stopped'   => 'Dispatcher Stopped',
    'sync_workspaces'      => 'Sync Workspaces',
    'workspaces_synced'    => 'Workspaces synchronized successfully.',
    'sync_error'           => 'Synchronization error.',
    'queue_dispatched'     => 'Task dispatched to autonomous AI queue.',
    'active_lock_cleared'  => 'Active task lock cleared.',

    // Dashboard UI
    'page_title'           => 'Dispatcher — AI Command Center',
    'badge_live'           => 'Live Monitor',
    'status_bridge_title'  => 'Bridge Port Status',
    'status_bridge_desc'   => '127.0.0.1:4000 socket connection and HTTP bridge',
    'status_queue_title'   => 'Queued AI Tasks',
    'status_queue_desc'    => 'Autonomous tasks in assigned state',
    'status_active_title'  => 'Active Processing Tasks',
    'status_active_desc'   => 'Tasks currently being handled by autonomous agents',
    'status_total_title'   => 'Configured Projects',
    'status_total_desc'    => 'Projects linked to local workspaces',

    'tab_queue'            => 'Queue (Assigned Tasks)',
    'tab_active'           => 'Active Tasks',
    'tab_logs'             => 'Live Dispatcher Logs',

    'th_task_code'         => 'Task Code',
    'th_project'           => 'Project',
    'th_title'             => 'Task Title',
    'th_priority'          => 'Priority',
    'th_status'            => 'Status',
    'th_actions'           => 'Actions',
    'th_started_at'        => 'Started At',

    'btn_dispatch_now'     => 'Dispatch Now',
    'btn_cancel_task'      => 'Remove from Queue',
    'btn_abort_task'       => 'Abort (Revert)',
    'btn_view_task'        => 'View Task',
    'btn_refresh'          => 'Refresh',
    'btn_clear_logs'       => 'Clear',
    'btn_sync_workspaces'  => 'Sync Workspaces',

    'empty_queue'          => 'No autonomous AI tasks waiting in the queue.',
    'empty_active'         => 'No active AI tasks running currently.',
    'empty_logs'           => 'No log entries recorded yet.',

    'confirm_dispatch'     => 'Do you want to dispatch this task to the autonomous agent immediately?',
    'confirm_abort'        => 'Do you want to abort this task and revert it back to the todo list?',

    'msg_dispatch_success' => 'Task successfully dispatched to the AI agent.',
    'msg_dispatch_failed'  => 'Failed to dispatch task or bridge did not respond.',
    'msg_abort_success'    => 'Task successfully aborted and reverted.',
    'msg_abort_failed'     => 'An error occurred while aborting the task.',
    'msg_unauthorized'     => 'You do not have permission for this action.',
    'msg_not_found'        => 'Requested record could not be found.'
];
