<?php
namespace Modules\Team\Models;

use Core\BaseModel;

class UserModel extends BaseModel
{
    protected string $table = 'users';

    /**
     * Tüm kullanıcıları çeker
     */
    public function getAllUsers(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        return $this->query("SELECT * FROM users ORDER BY {$orderBy} {$dir}");
    }

    /**
     * Filtre dropdownları için kullanıcıları çeker
     */
    public function getUsersForFilter(): array
    {
        return $this->query("SELECT id, name, role, avatar_color FROM users ORDER BY name ASC");
    }

    /**
     * ID'ye göre kullanıcı çeker
     */
    public function getUserById(int $id): ?object
    {
        $rows = $this->query("SELECT * FROM users WHERE id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * E-posta adresine göre kullanıcı arar
     */
    public function getUserByEmail(string $email): ?object
    {
        $normalizedEmail = (str_ends_with($email, '.om')) ? substr($email, 0, -3) . '.com' : $email;
        $rows = $this->query("SELECT * FROM users WHERE email = ? OR email = ? LIMIT 1", [$email, $normalizedEmail]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Ekip performansı istatistikleri
     */
    public function getTeamPerformanceStats(?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        return $this->query("
            SELECT u.id, u.name, u.title, u.avatar_color,
                   COUNT(t.id) as total_assigned,
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as total_completed,
                   SUM(CASE WHEN t.status IN ('in_progress', 'accepted') THEN 1 ELSE 0 END) as total_active,
                   SUM(CASE WHEN t.deadline < ? AND t.status != 'completed' THEN 1 ELSE 0 END) as total_overdue
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            GROUP BY u.id
        ", [$today]);
    }

    /**
     * Sohbet ekranı için aktif ekip üyelerini listeler
     */
    public function getChatTeamUsers(int $currentUserId, bool $isClient = false): array
    {
        $userFilterSql = "status = 'active' AND id != ?";
        if ($isClient) {
            $userFilterSql .= " AND role != 'client'";
        }

        return $this->query("
            SELECT id, name, title, avatar_color, role, status 
            FROM users 
            WHERE {$userFilterSql}
            ORDER BY name ASC
        ", [$currentUserId]);
    }

    /**
     * E-posta adresinin başka kullanıcı tarafından kullanılıp kullanılmadığını kontrol eder
     */
    public function isEmailTaken(string $email, ?int $excludeUserId = null): bool
    {
        if ($excludeUserId) {
            $rows = $this->query("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $excludeUserId]);
        } else {
            $rows = $this->query("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        }
        return !empty($rows);
    }

    /**
     * Yeni kullanıcı ekler
     */
    public function createUser(array $data): int|false
    {
        $fields = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fieldNames = implode(', ', array_map(fn($f) => "`{$f}`", $fields));
        $sql = "INSERT INTO users ({$fieldNames}) VALUES ({$placeholders})";
        
        $stmt = $this->getPdo()->prepare($sql);
        $res = $stmt->execute(array_values($data));
        return $res ? (int)$this->getPdo()->lastInsertId() : false;
    }

    /**
     * Kullanıcıya atanmış görev sayısını kontrol eder
     */
    public function getAssignedTaskCount(int $userId): int
    {
        $rows = $this->query("SELECT COUNT(*) as cnt FROM tasks WHERE assigned_to = ?", [$userId]);
        return !empty($rows) ? (int)$rows[0]->cnt : 0;
    }

    /**
     * Kullanıcı durumunu günceller (active/inactive)
     */
    public function updateStatus(int $userId, string $status): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $userId]);
    }

    /**
     * Kullanıcıyı siler
     */
    public function deleteUser(int $userId): bool
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    /**
     * Kullanıcı profilini günceller
     */
    public function updateProfile(int $userId, array $fields): bool
    {
        $setClauses = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $setClauses[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $this->getPdo()->prepare($sql);
        return $stmt->execute($params);
    }
}
