-- .migrations/TSK-1293_down.sql
-- Rollback migration for TSK-1293 (Project Time Machine & Visual Rollback)
-- Since TSK-1293 utilizes existing DB schemas (task_activities, tasks, projects, users)
-- and does not add persistent table columns, this rollback file confirms clean DB state.
SELECT 1;
