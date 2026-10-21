-- Rollback migration for TSK-1263
-- Reverts qa_global_instructions and global_agent_rules in settings

UPDATE settings 
SET qa_global_instructions = NULL 
WHERE id = 1;
