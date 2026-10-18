-- Rollback script for project hourly rate feature
ALTER TABLE `projects` DROP COLUMN IF EXISTS `hourly_rate`;
