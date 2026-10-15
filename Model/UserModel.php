<?php

require_once __DIR__ . '/Model.php';

class UserModel extends Model
{
    /**
     * Tüm kullanıcıları çeker
     */
    public function getAllUsers(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        return $this->db->multi_record("users", [], [], "", $orderBy, $direction) ?: [];
    }

    /**
     * Filtre dropdownları için kullanıcıları çeker (id, name, role, avatar_color)
     */
    public function getUsersForFilter(): array
    {
        return $this->db->multi_record("users", [], [], "", "name", "ASC", "", "id, name, role, avatar_color") ?: [];
    }

    /**
     * ID'ye göre kullanıcı çeker
     */
    public function getUserById(int $id): ?object
    {
        return $this->db->row_query("SELECT * FROM users WHERE id = ? LIMIT 1", [$id]);
    }

    /**
     * E-posta adresine göre kullanıcı arar (normal veya normalize)
     */
    public function getUserByEmail(string $email): ?object
    {
        $normalizedEmail = (str_ends_with($email, '.om')) ? substr($email, 0, -3) . '.com' : $email;
        return $this->db->row_query("SELECT * FROM users WHERE email = ? OR email = ? LIMIT 1", [$email, $normalizedEmail]);
    }

    /**
     * Raporlar ekranı için ekip üyeleri performans istatistiklerini hesaplar
     */
    public function getTeamPerformanceStats(?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        return $this->db->multi_record_query("
            SELECT u.id, u.name, u.title, u.avatar_color,
                   COUNT(t.id) as total_assigned,
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as total_completed,
                   SUM(CASE WHEN t.status IN ('in_progress', 'accepted') THEN 1 ELSE 0 END) as total_active,
                   SUM(CASE WHEN t.deadline < ? AND t.status != 'completed' THEN 1 ELSE 0 END) as total_overdue
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            GROUP BY u.id
        ", [$today]) ?: [];
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

        return $this->db->multi_record_query("
            SELECT id, name, title, avatar_color, role, status 
            FROM users 
            WHERE {$userFilterSql}
            ORDER BY name ASC
        ", [$currentUserId]) ?: [];
    }
}
