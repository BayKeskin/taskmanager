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
            $sql = "UPDATE {$this->table} 
                SET name = ?,
                    refresh_token = ?,
                    access_token = ?,
                    expiry_timestamp = ?,
                    gemini_quota_pct = ?,
                    claude_quota_pct = ?,
                    status = ?,
                    reset_time = ?,
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

    public function deleteAccount(int $id): bool
    {
        $res = $this->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return $res > 0;
    }
}
