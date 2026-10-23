<?php
namespace Modules\Settings\Controllers;

use Core\BaseController;
use Modules\Settings\Models\SettingsModel;

class SettingsController extends BaseController
{
    private SettingsModel $settingsModel;

    private \Modules\Settings\Models\AiAccountsModel $aiAccountsModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingsModel = new SettingsModel();
        $this->aiAccountsModel = new \Modules\Settings\Models\AiAccountsModel();
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
        if ($isAuthorized) {
            $this->aiAccountsModel->syncActiveAccountFromIde();
        }
        $aiAccounts = $isAuthorized ? $this->aiAccountsModel->getAllAccounts() : [];

        return [
            'currentUser'      => $currentUser,
            'isAuthorized'     => $isAuthorized,
            's'                => $settings,
            'availableModules' => $modules,
            'aiAccounts'       => $aiAccounts
        ];
    }
}
