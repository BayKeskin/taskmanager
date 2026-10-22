<?php
namespace Modules\Settings\Models;

use Core\BaseModel;
use PDO;

class AiAccountsModel extends BaseModel
{
    protected string $table = 'ai_accounts';

    public function getAllAccounts(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY is_active DESC, gemini_quota_pct DESC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAccountByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertAccount(array $data): bool
    {
        $existing = $this->getAccountByEmail($data['email']);
        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET name = :name,
                    refresh_token = :refresh_token,
                    access_token = :access_token,
                    expiry_timestamp = :expiry_timestamp,
                    gemini_quota_pct = :gemini_quota_pct,
                    claude_quota_pct = :claude_quota_pct,
                    status = :status,
                    reset_time = :reset_time,
                    updated_at = NOW()
                WHERE email = :email
            ");
            return $stmt->execute([
                ':email' => $data['email'],
                ':name' => $data['name'] ?? null,
                ':refresh_token' => $data['refresh_token'],
                ':access_token' => $data['access_token'] ?? null,
                ':expiry_timestamp' => $data['expiry_timestamp'] ?? 0,
                ':gemini_quota_pct' => (int)($data['gemini_quota_pct'] ?? 100),
                ':claude_quota_pct' => (int)($data['claude_quota_pct'] ?? 100),
                ':status' => $data['status'] ?? 'HEALTHY',
                ':reset_time' => $data['reset_time'] ?? null
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} 
                (email, name, refresh_token, access_token, expiry_timestamp, gemini_quota_pct, claude_quota_pct, status, is_active, reset_time)
                VALUES 
                (:email, :name, :refresh_token, :access_token, :expiry_timestamp, :gemini_quota_pct, :claude_quota_pct, :status, :is_active, :reset_time)
            ");
            return $stmt->execute([
                ':email' => $data['email'],
                ':name' => $data['name'] ?? null,
                ':refresh_token' => $data['refresh_token'],
                ':access_token' => $data['access_token'] ?? null,
                ':expiry_timestamp' => $data['expiry_timestamp'] ?? 0,
                ':gemini_quota_pct' => (int)($data['gemini_quota_pct'] ?? 100),
                ':claude_quota_pct' => (int)($data['claude_quota_pct'] ?? 100),
                ':status' => $data['status'] ?? 'HEALTHY',
                ':is_active' => (int)($data['is_active'] ?? 0),
                ':reset_time' => $data['reset_time'] ?? null
            ]);
        }
    }

    public function setActiveAccount(string $email): bool
    {
        $this->db->exec("UPDATE {$this->table} SET is_active = 0");
        $stmt = $this->db->prepare("UPDATE {$this->table} SET is_active = 1 WHERE email = :email");
        return $stmt->execute([':email' => $email]);
    }

    public function deleteAccount(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
