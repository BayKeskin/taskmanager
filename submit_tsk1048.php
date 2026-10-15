<?php
require_once __DIR__ . '/System/System.php';

$notes = "1. Neler Yapildi (What Was Done):
- Controller/ajax/ajax.php icine save_github_settings, sync_project_github ve rollback_task uc noktalari eklendi.
- get_task_detail yanitina commit_hash, schema_diff_summary, attachment_url ve is_reverted alanlarinin sorunsuz donmesi dogrulandi.
- View/index/board.php ust filtre barina GitHub Ayarlari ve Proje Sync butonlari ile #modalGithubSettings modali entegre edildi.
- Components/task_drawer.php ve Public/js/app.js icine GitHub DevOps karti, Diff baglantisi, Veritabani etki ozeti, Cerrahi Rollback butonu ve AI Cerrah (AI Surgeon) onay popup akisi entegre edildi.

2. Nereden Test Edilir (Where to Test):
- Kanban Panosu: http://localhost/taskmanager/board?project_id=1
- Gorev Cekmecesi: Herhangi bir teslim edilmis goreve (Orn: TSK-1047) tiklanarak acilan cekmece.

3. Nasil Test Edilir (How to Test):
- Panoda ust bardaki 'GitHub Bagla' butonuna tiklayin, kullanici adi ve PAT token girip kaydedin.
- 'GitHub Sync' butonuna tiklayarak projenin reposunu esitleyin.
- Teslim edilmis gorevin cekmecesini acin: DevOps Durumu rozeti, commit hash, GitHub Diff linki ve Veritabani Etkisi kartini inceleyin.
- 'Degisiklikleri Geri Al (Rollback)' butonuna tiklayarak cerrahi geri alma veya cakisma durumunda AI Cerrah onay akisini test edin.

4. Otonom Test Kaniti (Autonomous Test Evidence):
- CLI ve PHP birim testleri ile veritabani alanlari, app.js fonksiyonlari ve View modal elementleri test edilip dogrulandi.
- Git commit 66376b9 basariyla origin main branchine push edildi.";

$res = Helper::submitTaskDirectly('TSK-1048', $notes);
echo "Submit Result: " . json_encode($res) . PHP_EOL;
