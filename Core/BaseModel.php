<?php
namespace Core;

use Database;

/**
 * BaseModel - Çekirdek Ana Model Sınıfı
 * Tüm modül modelleri bu sınıftan türer. PDO veritabanı erişimi, transaction
 * ve CRUD yardımcılarını standartlaştırır.
 */
abstract class BaseModel
{
    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct(?Database $db = null)
    {
        global $db;
        if ($db instanceof Database) {
            $this->db = $db;
        } else {
            $this->db = new Database();
        }
    }

    /**
     * Database nesnesini döndürür
     */
    public function getDb(): Database
    {
        return $this->db;
    }

    /**
     * Ham PDO bağlantısını döndürür
     */
    public function getPdo(): \PDO
    {
        return $this->db->connect;
    }

    /**
     * ID ile tek kayıt bulur
     */
    public function find(int $id): ?object
    {
        if (empty($this->table)) {
            return null;
        }
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1";
        $rows = $this->db->multi_record_query($sql, [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Tüm kayıtları listeler
     */
    public function all(string $orderBy = 'id', string $direction = 'DESC', int $limit = 100): array
    {
        if (empty($this->table)) {
            return [];
        }
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `{$orderBy}` {$dir} LIMIT {$limit}";
        return $this->db->multi_record_query($sql) ?: [];
    }

    /**
     * SQL sorgusu çalıştırır ve çoklu satır döndürür
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->db->multi_record_query($sql, $params) ?: [];
    }

    /**
     * SQL sorgusu çalıştırır ve tek satır döndürür
     */
    public function queryOne(string $sql, array $params = []): ?object
    {
        $rows = $this->db->multi_record_query($sql, $params);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Transaction Başlatıcı
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Transaction Onaylayıcı
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Transaction Geri Alıcı
     */
    public function rollBack(): bool
    {
        return $this->getPdo()->rollBack();
    }
}
