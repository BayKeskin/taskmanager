<?php
/**
 * Türkçe Dil Dosyası - Dispatcher Modülü
 */
return [
    'module_name'          => 'Otonom Yapay Zeka Köprüsü',
    'module_desc'          => 'Antigravity Language Server ve görev dağıtım motoru.',
    'nav_menu_title'       => 'Dispatcher',
    'dispatcher_running'   => 'Dispatcher Aktif (Port 4000)',
    'dispatcher_stopped'   => 'Dispatcher Durduruldu',
    'sync_workspaces'      => 'Çalışma Alanlarını Senkronize Et',
    'workspaces_synced'    => 'Çalışma alanları başarıyla senkronize edildi.',
    'sync_error'           => 'Senkronizasyon hatası.',
    'queue_dispatched'     => 'Görev otonom yapay zeka kuyruğuna gönderildi.',
    'active_lock_cleared'  => 'Aktif görev kilidi temizlendi.',

    // Dashboard UI
    'page_title'           => 'Dispatcher — AI Komuta Merkezi',
    'badge_live'           => 'Canlı İzleme',
    'status_bridge_title'  => 'Bridge Port Durumu',
    'status_bridge_desc'   => '127.0.0.1:4000 soket bağlantısı ve HTTP köprüsü',
    'status_queue_title'   => 'Kuyruktaki AI Görevleri',
    'status_queue_desc'    => 'Atanmış (assigned) statüsündeki otonom görevler',
    'status_active_title'  => 'Aktif Çalışılan Görevler',
    'status_active_desc'   => 'Şu an otonom ajanların geliştirdiği (in_progress) işler',
    'status_total_title'   => 'Tanımlı Projeler',
    'status_total_desc'    => 'Çalışma alanı (workspace) bağlı proje sayısı',

    'tab_queue'            => 'Kuyruk (Atanmış Görevler)',
    'tab_active'           => 'Aktif Görevler',
    'tab_logs'             => 'Canlı Dispatcher Logları',

    'th_task_code'         => 'Görev Kodu',
    'th_project'           => 'Proje',
    'th_title'             => 'Görev Başlığı',
    'th_priority'          => 'Öncelik',
    'th_status'            => 'Durum',
    'th_actions'           => 'İşlemler',
    'th_started_at'        => 'Başlangıç',

    'btn_dispatch_now'     => 'Şimdi Tetikle',
    'btn_cancel_task'      => 'Kuyruktan Çıkar',
    'btn_abort_task'       => 'Durdur (Geri Al)',
    'btn_view_task'        => 'Görevi Aç',
    'btn_refresh'          => 'Yenile',
    'btn_clear_logs'       => 'Temizle',
    'btn_sync_workspaces'  => 'Çalışma Alanlarını Senkronize Et',

    'empty_queue'          => 'Kuyrukta bekleyen otonom AI görevi bulunmuyor.',
    'empty_active'         => 'Şu anda arka planda çalışan aktif AI görevi yok.',
    'empty_logs'           => 'Henüz kayıtlı log satırı bulunmuyor.',

    'confirm_dispatch'     => 'Bu görevi otonom ajana hemen yönlendirmek istiyor musunuz?',
    'confirm_abort'        => 'Bu görevi durdurup yapılacaklar listesine (todo) geri almak istiyor musunuz?',

    'msg_dispatch_success' => 'Görev başarıyla yapay zeka ajanına yönlendirildi.',
    'msg_dispatch_failed'  => 'Görev yönlendirilemedi veya bridge yanıt vermedi.',
    'msg_abort_success'    => 'Görev başarıyla durduruldu ve geri alındı.',
    'msg_abort_failed'     => 'Görev durdurulurken bir hata oluştu.',
    'msg_unauthorized'     => 'Bu işlem için yetkiniz bulunmuyor.',
    'msg_not_found'        => 'İlgili kayıt bulunamadı.'
];
