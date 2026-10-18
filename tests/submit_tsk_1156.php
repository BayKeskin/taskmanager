<?php
$notes = "1. Neler Yapıldı (What Was Done):\n"
    . "- Model/TaskModel.php içerisine getTasksForExport() metodu eklendi; proje filtreleri, durum, öncelik, sorumlu/oluşturan kullanıcı bilgileri ve alt görev ilerleme yüzdeleri hesaplanarak dışa aktarıma hazır hale getirildi.\n"
    . "- Controller/BoardController.php içerisine exportExcel() metodu eklendi; Microsoft Excel uyumluluğu için UTF-8 BOM eklendi, CSV başlıkları ve veri akışı sağlandı.\n"
    . "- Controller/ajax/ajax.php ve .htaccess içine /kanban/export-excel rotası tanımlandı.\n"
    . "- View/index/board.php Kanban araç çubuğuna Excel renginde (#107C41) dışa aktarım butonu eklendi.\n"
    . "- languages/tr, en, de sözlükleri ve ajax çevirileri güncellendi.\n"
    . "- .migrations/TSK-1156_down.sql geri alma betiği hazırlandı.\n\n"
    . "2. Nereden Test Edilir (Where to Test):\n"
    . "- http://localhost/taskmanager/board?project_id=1\n"
    . "- http://localhost/taskmanager/kanban/export-excel?project_id=1\n\n"
    . "3. Nasıl Test Edilir (How to Test):\n"
    . "- Kanban panosundaki yeşil Excel İndir butonuna tıklanarak veya direkt URL çağrılarak filtrelenmiş görevler indirilir. İndirilen CSV Excel ile açıldığında Türkçe ve Almanca karakterlerin bozulmadığı doğrulanır.\n\n"
    . "4. Otonom Test Kanıtı (Autonomous Test Evidence):\n"
    . "- curl ile HTTP 200 yanıtı ve Content-Disposition: attachment; filename=kanban_tasks_... başlığı doğrulandı.\n"
    . "- Farklı dillerde (TR, EN) başlık ve durum etiketlerinin doğru geldiği test edildi.\n"
    . "- Git commit yapıldı ve origin main dalına push edildi.";

$data = json_encode(['notes' => $notes], JSON_UNESCAPED_UNICODE);

$ch = curl_init("http://localhost/taskmanager/api/tasks/TSK-1156/workflow/submit");
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
