<?php

require_once __DIR__ . '/HostingAdapterInterface.php';
require_once __DIR__ . '/CpanelAdapter.php';
require_once __DIR__ . '/PleskAdapter.php';
require_once __DIR__ . '/CyberPanelAdapter.php';
require_once __DIR__ . '/StandardHostingAdapter.php';

/**
 * HostingManager - Factory and Orchestrator for Hosting Panel API Adapters
 */
class HostingManager
{
    /**
     * Get instance of adapter based on panel type
     *
     * @param string $type cpanel, plesk, cyberpanel, standard
     * @param array $config Panel/DB connection parameters
     * @return HostingAdapterInterface
     */
    public static function createAdapter(string $type, array $config): HostingAdapterInterface
    {
        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'cpanel'     => new CpanelAdapter($config),
            'plesk'      => new PleskAdapter($config),
            'cyberpanel' => new CyberPanelAdapter($config),
            default      => new StandardHostingAdapter($config)
        };
    }

    /**
     * Auto-detect panel type or test credentials
     */
    public static function testCredentials(string $type, array $config): array
    {
        try {
            $adapter = self::createAdapter($type, $config);
            return $adapter->testConnection();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Adapter initialization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Unified one-click database provisioning across any panel
     */
    public static function provision(string $type, array $config, string $dbName, string $dbUser, string $dbPass): array
    {
        try {
            $adapter = self::createAdapter($type, $config);
            return $adapter->provisionDatabase($dbName, $dbUser, $dbPass);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Provisioning exception: ' . $e->getMessage()
            ];
        }
    }
}
