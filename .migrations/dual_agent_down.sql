-- Surgical Rollback for Dual Agent Migration
-- Usage: Run this SQL to restore exact database schema before Dual Agent

ALTER TABLE `projects` 
    DROP COLUMN IF EXISTS `qa_test_instructions`,
    DROP COLUMN IF EXISTS `qa_conversation_id`,
    DROP COLUMN IF EXISTS `dev_conversation_id`;

ALTER TABLE `settings`
    DROP COLUMN IF EXISTS `qa_global_instructions`;

ALTER TABLE `tasks`
    DROP COLUMN IF EXISTS `qa_revision_count`;
