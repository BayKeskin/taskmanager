<?php
namespace Modules\Mailer\Controllers;

use Core\BaseController;
use Modules\Mailer\Services\MailerService;

class MailerController extends BaseController
{
    public function testConnection(): void
    {
        $this->requireRole('admin');
        $result = MailerService::testConnection();
        $this->json($result, $result['success'] ? 200 : 400);
    }
}
