<?php
/**
 * HostingAdapterInterface - Standard Contract for Hosting Panel API Adapters
 */
interface HostingAdapterInterface
{
    /**
     * Test connection/authentication with panel API or remote server
     *
     * @return array ['success' => bool, 'message' => string, 'data' => mixed]
     */
    public function testConnection(): array;

    /**
     * Create database, user and grant privileges in a single automated step
     *
     * @param string $dbName Desired database name
     * @param string $dbUser Desired database username
     * @param string $dbPass Secure database password
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function provisionDatabase(string $dbName, string $dbUser, string $dbPass): array;

    /**
     * List existing databases
     *
     * @return array
     */
    public function listDatabases(): array;

    /**
     * Get adapter identifier
     *
     * @return string
     */
    public function getType(): string;
}
