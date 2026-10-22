<?php
use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('settings-styles', '/Modules/Settings/Assets/css/settings.css');
AssetManager::enqueueJs('settings-js', '/Modules/Settings/Assets/js/settings.js');

AssetManager::localizeScript('settings-js', 'SettingsI18n', [
    'saved_success'       => __('Settings::settings_saved_success'),
    'saved_fail'          => __('Settings::settings_saved_fail'),
    'test_testing'        => __('Settings::js_test_testing'),
    'test_sending'        => __('Settings::js_test_sending'),
    'test_telegram_btn'   => __('Settings::js_test_telegram_btn'),
    'test_telegram_ok'    => __('Settings::js_test_telegram_ok'),
    'test_telegram_fail'  => __('Settings::js_test_telegram_fail'),
    'test_telegram_err'   => __('Settings::js_test_telegram_err'),
    'test_smtp_btn'       => __('Settings::js_test_smtp_btn'),
    'test_smtp_ok'        => __('Settings::js_test_smtp_ok'),
    'test_smtp_fail'      => __('Settings::js_test_smtp_fail'),
    'test_smtp_err'       => __('Settings::js_test_smtp_err'),
    'test_slack_btn'      => __('Settings::js_test_slack_btn'),
    'test_discord_btn'    => __('Settings::js_test_discord_btn'),
    'test_webhook_ok'     => __('Settings::js_test_webhook_ok'),
    'test_webhook_fail'   => __('Settings::js_test_webhook_fail'),
    'test_webhook_err'    => __('Settings::js_test_webhook_err'),
    'server_error'        => __('Settings::js_server_error'),
    'module_toggle_err'   => __('Settings::js_module_toggle_err'),
    'active_badge'        => __('Settings::settings_modules_active_badge'),
    'passive_badge'       => __('Settings::settings_modules_passive_badge'),
    'toggle_success'      => __('Settings::settings_modules_toggle_success'),
    'toggle_fail'         => __('Settings::settings_modules_toggle_fail'),
    'ai_active_tag'       => __('Settings::settings_ai_vault_active'),
    'ai_activate_btn'     => __('Settings::settings_ai_vault_btn_activate'),
    'ai_delete_confirm'   => __('Settings::js_ai_delete_confirm'),
    'ai_generic_error'    => __('Settings::js_ai_generic_error'),
    'ai_empty_vault'      => __('Settings::settings_ai_vault_empty'),
    'ai_oauth_init'       => __('Settings::ai_account_oauth_init_success')
]);
