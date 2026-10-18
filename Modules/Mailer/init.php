<?php
// Modules/Mailer/init.php
namespace Modules\Mailer;

use Core\EventManager;
use Core\AssetManager;

// EventManager kancası: Sistem e-posta gönderim isteklerini dinle
EventManager::listen('email.send', function($params) {
    if (!empty($params['to']) && !empty($params['subject']) && !empty($params['body'])) {
        return Services\MailerService::sendHtmlMail(
            $params['to'],
            $params['subject'],
            $params['body'],
            $params['title'] ?? null
        );
    }
    return false;
});
