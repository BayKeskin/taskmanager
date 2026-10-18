<?php
$notes = "1. Neler Yapıldı (What Was Done):\n"
    . "- View/index/board.php içerisindeki Kanban üst filtre araç çubuğu sadeleştirildi; üst barda sadece 'AI Proje Mimarı' ve '+ Görev Ekle' butonları bırakıldı.\n"
    . "- Kalan tüm aksiyonlar (PDF Hakediş Raporu, AI Standup Özeti, Excel'e Aktar, GitHub Hesap Bağlantısı, GitHub Sync, Canlı Dağıtım Sihirbazı, MCP Araçları) '🛠️ Araçlar' başlıklı şık ve modern bir açılır menü (dropdown) altında toplandı.\n"
    . "- Menü dışına tıklandığında otomatik kapanma ve ikonlu/açıklamalı buton hover efektleri eklendi.\n"
    . "- languages/tr, en, de sözlüklerine gerekli i18n çeviri anahtarları (kanban_tools, kanban_tools_desc, export_excel_desc, tooltip_sync_github) tanımlandı.\n"
    . "- .migrations/TSK-1157_down.sql eklendi, değişiklikler commit edilip GitHub origin/main dalına push edildi.\n\n"
    . "2. Nereden Test Edilir (Where to Test):\n"
    . "- http://localhost/taskmanager/board?project_id=1\n\n"
    . "3. Nasıl Test Edilir (How to Test):\n"
    . "- Kanban panosunda üst barda sadece '🪄 AI Proje Mimarı', '🛠️ Araçlar' ve '➕ Görev Ekle' butonlarının yer aldığı görülür.\n"
    . "- 'Araçlar' butonuna tıklandığında açılan menüden PDF Raporu, AI Standup, Excel İndirme, Canlı Dağıtım, GitHub ve MCP araçlarına kolayca erişilebildiği ve dışarı tıklandığında menünün kapandığı test edilir.\n\n"
    . "4. Otonom Test Kanıtı (Autonomous Test Evidence):\n"
    . "- PHP linter ile tüm dosyalar hatasız (No syntax errors detected) doğrulandı.\n"
    . "- HTML çıktısı ve dropdown menü bileşeni curl ile kontrol edildi.\n"
    . "- Git commit & push eksiksiz tamamlandı.";

$data = json_encode(['notes' => $notes], JSON_UNESCAPED_UNICODE);

$ch = curl_init("http://localhost/taskmanager/api/tasks/TSK-1157/workflow/submit");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer tm_admin_talha_keskin_token_2026",
    "Content-Type: application/json; charset=utf-8"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code: $res\n";
