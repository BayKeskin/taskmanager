<?php
namespace Modules\Settings\Models;

use Core\BaseModel;

class AiAccountsModel extends BaseModel
{
    protected string $table = 'ai_accounts';

    public function getAllAccounts(): array
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY is_active DESC, gemini_quota_pct DESC, id ASC");
    }

    public function getAccountByEmail(string $email): ?array
    {
        return $this->row("SELECT * FROM {$this->table} WHERE email = ? LIMIT 1", [$email]);
    }

    public function upsertAccount(array $data): bool
    {
        $existing = $this->getAccountByEmail($data['email']);
        if ($existing) {
            $isActiveSql = isset($data['is_active']) ? ", is_active = " . (int)$data['is_active'] : "";
            $sql = "UPDATE {$this->table} 
                SET name = ?,
                    refresh_token = ?,
                    access_token = ?,
                    expiry_timestamp = ?,
                    gemini_quota_pct = ?,
                    claude_quota_pct = ?,
                    status = ?,
                    reset_time = ?
                    {$isActiveSql},
                    updated_at = NOW()
                WHERE email = ?";
            
            $res = $this->execute($sql, [
                $data['name'] ?? null,
                $data['refresh_token'],
                $data['access_token'] ?? null,
                $data['expiry_timestamp'] ?? 0,
                (int)($data['gemini_quota_pct'] ?? 100),
                (int)($data['claude_quota_pct'] ?? 100),
                $data['status'] ?? 'HEALTHY',
                $data['reset_time'] ?? null,
                $data['email']
            ]);
            return $res >= 0;
        } else {
            $sql = "INSERT INTO {$this->table} 
                (email, name, refresh_token, access_token, expiry_timestamp, gemini_quota_pct, claude_quota_pct, status, is_active, reset_time)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $res = $this->execute($sql, [
                $data['email'],
                $data['name'] ?? null,
                $data['refresh_token'],
                $data['access_token'] ?? null,
                $data['expiry_timestamp'] ?? 0,
                (int)($data['gemini_quota_pct'] ?? 100),
                (int)($data['claude_quota_pct'] ?? 100),
                $data['status'] ?? 'HEALTHY',
                (int)($data['is_active'] ?? 0),
                $data['reset_time'] ?? null
            ]);
            return $res > 0;
        }
    }

    public function setActiveAccount(string $email): bool
    {
        $this->execute("UPDATE {$this->table} SET is_active = 0");
        $res = $this->execute("UPDATE {$this->table} SET is_active = 1 WHERE email = ?", [$email]);
        return $res > 0;
    }

    public function syncActiveAccountFromIde(): string
    {
        $appData = getenv('APPDATA') ?: (getenv('USERPROFILE') . '/AppData/Roaming');
        $vscdb = $appData . '/Antigravity IDE/User/globalStorage/state.vscdb';
        if (!file_exists($vscdb)) {
            return '';
        }

        try {
            $sdb = new \SQLite3($vscdb, SQLITE3_OPEN_READONLY);
            $stmt = $sdb->prepare("SELECT value FROM ItemTable WHERE key = 'antigravityUnifiedStateSync.userStatus' LIMIT 1");
            $res = $stmt ? $stmt->execute() : false;
            $row = $res ? $res->fetchArray(SQLITE3_NUM) : null;
            $sdb->close();

            if (!empty($row[0])) {
                $raw = base64_decode((string)$row[0]);
                if ($raw) {
                    // 1. Direct regex match
                    if (preg_match('/[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+/', $raw, $m)) {
                        $activeEmail = $m[0];
                    } else {
                        // 2. Decode inner base64 chunks with 4-byte alignment
                        $activeEmail = '';
                        if (preg_match_all('/[A-Za-z0-9+\/=]{16,}/', $raw, $chunks)) {
                            foreach ($chunks[0] as $chunk) {
                                for ($trim = 0; $trim < 4; $trim++) {
                                    $cand = substr($chunk, $trim);
                                    $rem = strlen($cand) % 4;
                                    if ($rem > 0) {
                                        $cand = substr($cand, 0, -$rem);
                                    }
                                    $dec = base64_decode($cand, true);
                                    if ($dec && preg_match('/[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.(?:com|net|org|io|co|tr)/i', $dec, $em)) {
                                        $activeEmail = $em[0];
                                        break 2;
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($activeEmail)) {
                        $this->execute("UPDATE {$this->table} SET is_active = 0");
                        $this->execute("UPDATE {$this->table} SET is_active = 1 WHERE email = ?", [$activeEmail]);
                        return $activeEmail;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently failover
        }

        return '';
    }

    public function deleteAccount(int $id): bool
    {
        $res = $this->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return $res > 0;
    }
}
