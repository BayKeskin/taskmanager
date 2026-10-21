-- Rollback migration for TSK-1262
-- Reverts qa_test_instructions for project ASKERLIK

UPDATE projects 
SET qa_test_instructions = NULL 
WHERE project_code = 'ASKERLIK';
