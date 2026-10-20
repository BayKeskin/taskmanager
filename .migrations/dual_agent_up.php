<?php
require_once dirname(__DIR__) . '/Core/System.php';
global $db;
$pdo = $db ? $db->connect : (new Database())->connect;

echo "=== MIGRATION: DUAL AGENT COLUMNS ===\n";

// 1. projects: dev_conversation_id, qa_conversation_id, qa_test_instructions
$cols = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('dev_conversation_id', $cols)) {
    $pdo->exec("ALTER TABLE projects ADD COLUMN dev_conversation_id VARCHAR(100) NULL AFTER agent_conversation_id");
    echo "Added projects.dev_conversation_id\n";
    // Varolan agent_conversation_id'leri dev_conversation_id'ye kopyala
    $pdo->exec("UPDATE projects SET dev_conversation_id = agent_conversation_id WHERE agent_conversation_id IS NOT NULL AND agent_conversation_id != ''");
    echo "Copied existing agent_conversation_id to dev_conversation_id\n";
} else {
    echo "projects.dev_conversation_id already exists\n";
}

if (!in_array('qa_conversation_id', $cols)) {
    $pdo->exec("ALTER TABLE projects ADD COLUMN qa_conversation_id VARCHAR(100) NULL AFTER dev_conversation_id");
    echo "Added projects.qa_conversation_id\n";
} else {
    echo "projects.qa_conversation_id already exists\n";
}

if (!in_array('qa_test_instructions', $cols)) {
    $pdo->exec("ALTER TABLE projects ADD COLUMN qa_test_instructions TEXT NULL AFTER qa_conversation_id");
    echo "Added projects.qa_test_instructions\n";
} else {
    echo "projects.qa_test_instructions already exists\n";
}

// 2. settings: qa_global_instructions
$settingsCols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('qa_global_instructions', $settingsCols)) {
    $pdo->exec("ALTER TABLE settings ADD COLUMN qa_global_instructions TEXT NULL AFTER ai_system_prompt_prefix");
    echo "Added settings.qa_global_instructions\n";
} else {
    echo "settings.qa_global_instructions already exists\n";
}

// 3. tasks: qa_revision_count
$tasksCols = $pdo->query("SHOW COLUMNS FROM tasks")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('qa_revision_count', $tasksCols)) {
    $pdo->exec("ALTER TABLE tasks ADD COLUMN qa_revision_count INT NOT NULL DEFAULT 0 AFTER priority");
    echo "Added tasks.qa_revision_count\n";
} else {
    echo "tasks.qa_revision_count already exists\n";
}

echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
