<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/SettingsModel.php';

class SettingsController extends Controller
{
    private SettingsModel $settingsModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->settingsModel = new SettingsModel($this->db);
    }

    /**
     * Ayarlar ekranı verilerini hazırlar
     */
    public function index(?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isAuthorized = ($currentUser->role === 'admin');

        $settings = $isAuthorized ? $this->settingsModel->getSettings() : (object)[];
        $modules  = $isAuthorized ? \Core\ModuleLoader::getAllAvailableModules() : [];

        return [
            'currentUser'      => $currentUser,
            'isAuthorized'     => $isAuthorized,
            's'                => $settings,
            'availableModules' => $modules
        ];
    }
}
