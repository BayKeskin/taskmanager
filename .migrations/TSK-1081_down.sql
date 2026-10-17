-- Rollback Migration for TSK-1081
-- Task: [Deploy-1] Akıllı Proje, Ortam Değişkeni ve Canlı Site URL Analizcisi
-- Note: TSK-1081 introduced code-level engine System/ProjectAnalyzer.php and API/AJAX routing endpoints.
-- No structural database table modifications (DDL) were made.
-- If any testing records or mock entries were inserted:
DELETE FROM live_events WHERE event_type LIKE '%project.analyzed%' AND created_at >= NOW() - INTERVAL 1 HOUR;
