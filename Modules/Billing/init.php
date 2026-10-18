<?php
/**
 * Billing Modülü Başlatıcı (init.php)
 * Kancaları ve Varlıkları Kaydeder.
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\Billing\Controllers\BillingController;

// 1. Varlıklar: billing.js'i genel kuyruğa al (Rapor PDF CSS'i genel sayfalara sızmamalıdır)
AssetManager::enqueueJs('billing-js', '/taskmanager/Modules/Billing/Assets/js/billing.js');

// 2. Hakediş Özeti Çekme Kancası
EventManager::listen('billing.get_summary', function(array $params) {
    $controller = new BillingController();
    return $controller->getBillingData($params['project_id'] ?? null, $params['rate'] ?? null);
});
