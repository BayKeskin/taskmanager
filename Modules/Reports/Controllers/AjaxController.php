<?php
namespace Modules\Reports\Controllers;

use Core\BaseController;

class AjaxController extends BaseController
{
    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function getDailyStandup(): void
    {
        $pId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $res = \Modules\Reports\Services\StandupService::generateAiStandupSummary($pId);
        $this->respond(true, __('Reports::standup_prepared'), array_merge($res, ['data' => $res]));
    }

    public function sendStandupEmail(): void
    {
        $email = trim($_POST['email'] ?? $this->user->email ?? '');
        $pId = isset($_POST['project_id']) && is_numeric($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        if (empty($email)) {
            $this->respond(false, __('Reports::email_required'));
        }
        $summary = \Modules\Reports\Services\StandupService::generateAiStandupSummary($pId);
        $sent = \Modules\Reports\Services\StandupService::sendDailyStandupEmail($email, $summary);
        $this->respond((bool)$sent, $sent ? __('Reports::standup_email_sent') : __('Reports::email_failed'));
    }
}
