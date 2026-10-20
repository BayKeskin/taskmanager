-- TSK-1213: Rollback migration for settings.global_agent_rules
ALTER TABLE settings DROP COLUMN IF EXISTS global_agent_rules;
