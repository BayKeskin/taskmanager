<?php

require_once __DIR__ . '/Model.php';

class McpModel extends Model
{
    /**
     * Tüm MCP Kataloğunu listeler
     */
    public function getCatalog(?string $category = null): array
    {
        if (!empty($category) && $category !== 'all') {
            return $this->db->multi_record_query(
                "SELECT * FROM mcp_catalog WHERE is_active = 1 AND category = ? ORDER BY name ASC",
                [$category]
            ) ?: [];
        }
        return $this->db->multi_record_query(
            "SELECT * FROM mcp_catalog WHERE is_active = 1 ORDER BY category ASC, name ASC"
        ) ?: [];
    }

    /**
     * Tekil katalog öğesi
     */
    public function getCatalogItem(int $id): ?object
    {
        $rows = $this->db->multi_record_query("SELECT * FROM mcp_catalog WHERE id = ? LIMIT 1", [$id]);
        return $rows[0] ?? null;
    }

    /**
     * Projeye ait tüm MCP durumlarını (katalog bilgileriyle birleştirerek) çeker
     */
    public function getProjectMcps(int $projectId): array
    {
        $sql = "SELECT c.*, 
                       pm.id AS project_mcp_id, 
                       pm.is_enabled, 
                       pm.env_values, 
                       pm.custom_args,
                       pm.updated_at AS configured_at
                FROM mcp_catalog c
                LEFT JOIN project_mcps pm ON c.id = pm.mcp_catalog_id AND pm.project_id = ?
                WHERE c.is_active = 1
                ORDER BY c.category ASC, c.name ASC";
        
        $rows = $this->db->multi_record_query($sql, [$projectId]) ?: [];
        foreach ($rows as &$r) {
            $r->is_enabled = !empty($r->is_enabled);
            $r->required_env_fields = json_decode($r->required_env_fields ?? '[]', true) ?: [];
            $r->default_args = json_decode($r->default_args ?? '[]', true) ?: [];
            $r->env_values = json_decode($r->env_values ?? '{}', true) ?: [];
            $r->custom_args = json_decode($r->custom_args ?? '[]', true) ?: [];
        }
        return $rows;
    }

    /**
     * Proje için MCP ayarlarını kaydeder ve açar/kapatır
     */
    public function saveProjectMcp(int $projectId, int $mcpCatalogId, bool $isEnabled, array $envValues = [], array $customArgs = []): bool
    {
        $envJson = json_encode($envValues, JSON_UNESCAPED_UNICODE);
        $argsJson = json_encode($customArgs, JSON_UNESCAPED_UNICODE);
        $enabledInt = $isEnabled ? 1 : 0;

        $sql = "INSERT INTO project_mcps (project_id, mcp_catalog_id, is_enabled, env_values, custom_args, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                is_enabled = VALUES(is_enabled),
                env_values = VALUES(env_values),
                custom_args = VALUES(custom_args),
                updated_at = NOW()";

        return (bool)$this->db->exec_query($sql, [$projectId, $mcpCatalogId, $enabledInt, $envJson, $argsJson]);
    }

    /**
     * MCP aç/kapat toggle
     */
    public function toggleProjectMcp(int $projectId, int $mcpCatalogId, bool $isEnabled): bool
    {
        $enabledInt = $isEnabled ? 1 : 0;
        $sql = "INSERT INTO project_mcps (project_id, mcp_catalog_id, is_enabled, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                is_enabled = VALUES(is_enabled),
                updated_at = NOW()";

        return (bool)$this->db->exec_query($sql, [$projectId, $mcpCatalogId, $enabledInt]);
    }

    /**
     * Projenin aktif MCP'lerini okuyup yerel .agents/plugins/ klasörüne
     * ve .agents/mcp_config.json dosyasına otomatik yazar (Sync Engine)
     */
    public function syncProjectMcpFiles(int $projectId): array
    {
        $projectRows = $this->db->multi_record_query("SELECT id, project_code, workspace_path FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        $project = $projectRows[0] ?? null;
        if (!$project || empty($project->workspace_path) || !is_dir($project->workspace_path)) {
            return ['synced' => false, 'error' => 'Geçerli bir yerel proje klasörü (workspace_path) bulunamadı.'];
        }

        $workspacePath = rtrim(str_replace('\\', '/', $project->workspace_path), '/');
        $agentsDir = $workspacePath . '/.agents';
        $pluginsDir = $agentsDir . '/plugins';

        if (!is_dir($agentsDir)) {
            @mkdir($agentsDir, 0777, true);
        }
        if (!is_dir($pluginsDir)) {
            @mkdir($pluginsDir, 0777, true);
        }

        $activeMcps = $this->db->multi_record_query(
            "SELECT c.*, pm.env_values, pm.custom_args 
             FROM project_mcps pm
             JOIN mcp_catalog c ON pm.mcp_catalog_id = c.id
             WHERE pm.project_id = ? AND pm.is_enabled = 1 AND c.is_active = 1",
            [$projectId]
        ) ?: [];

        $allMcpServersMap = [];
        $writtenPlugins = [];

        foreach ($activeMcps as $mcp) {
            $mcpKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $mcp->mcp_key);
            $envData = json_decode($mcp->env_values ?? '{}', true) ?: [];
            $customArgs = json_decode($mcp->custom_args ?? '[]', true) ?: [];
            $defaultArgs = json_decode($mcp->default_args ?? '[]', true) ?: [];

            $finalArgs = !empty($customArgs) ? $customArgs : $defaultArgs;

            // Özel MCP kontrolü
            if ($mcpKey === 'custom_mcp') {
                $customCmd = $envData['CUSTOM_COMMAND'] ?? 'npx';
                $customSse = $envData['CUSTOM_SSE_URL'] ?? null;
                $rawArgs = $envData['CUSTOM_ARGS'] ?? '';
                if (!empty($rawArgs)) {
                    $parsed = json_decode($rawArgs, true);
                    $finalArgs = is_array($parsed) ? $parsed : array_map('trim', explode(',', $rawArgs));
                }
                if (!empty($customSse)) {
                    $serverDef = ["serverUrl" => $customSse];
                } else {
                    $serverDef = [
                        "command" => $customCmd,
                        "args" => $finalArgs,
                        "env" => array_filter($envData, fn($k) => !in_array($k, ['CUSTOM_COMMAND', 'CUSTOM_ARGS', 'CUSTOM_SSE_URL']), ARRAY_FILTER_USE_KEY)
                    ];
                }
            } else {
                $cleanEnv = [];
                foreach ($envData as $k => $v) {
                    if ($v !== null && $v !== '') {
                        $cleanEnv[$k] = (string)$v;
                    }
                }

                $serverDef = [
                    "command" => $mcp->command ?: "npx",
                    "args" => $finalArgs
                ];
                if (!empty($cleanEnv)) {
                    $serverDef["env"] = $cleanEnv;
                }
            }

            $allMcpServersMap[$mcpKey] = $serverDef;

            // Her MCP için izole plugin klasörü oluştur (.agents/plugins/<mcp_key>/)
            $pluginPath = $pluginsDir . '/' . $mcpKey;
            if (!is_dir($pluginPath)) {
                @mkdir($pluginPath, 0777, true);
            }

            // 1. plugin.json
            $pluginManifest = [
                "name" => $mcp->name,
                "version" => "1.0.0",
                "description" => $mcp->description ?: "Antigravity MCP Integration for " . $mcp->name
            ];
            file_put_contents($pluginPath . '/plugin.json', json_encode($pluginManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // 2. mcp_config.json
            $pluginMcpConfig = [
                "mcpServers" => [
                    $mcpKey => $serverDef
                ]
            ];
            file_put_contents($pluginPath . '/mcp_config.json', json_encode($pluginMcpConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $writtenPlugins[] = $mcpKey;
        }

        // 3. Ortak .agents/mcp_config.json dosyasını da güncel tut (çift katmanlı tam uyumluluk)
        $masterConfig = [
            "mcpServers" => $allMcpServersMap
        ];
        file_put_contents($agentsDir . '/mcp_config.json', json_encode($masterConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // 4. Kapatılan MCP'lerin eski plugin klasörlerini temizle
        if (is_dir($pluginsDir)) {
            $existingDirs = glob($pluginsDir . '/*', GLOB_ONLYDIR);
            foreach ($existingDirs as $dir) {
                $dirName = basename($dir);
                if (!in_array($dirName, $writtenPlugins)) {
                    @unlink($dir . '/mcp_config.json');
                    @unlink($dir . '/plugin.json');
                    @rmdir($dir);
                }
            }
        }

        return [
            'synced' => true,
            'active_count' => count($writtenPlugins),
            'active_plugins' => $writtenPlugins,
            'config_path' => $agentsDir . '/mcp_config.json'
        ];
    }
}
