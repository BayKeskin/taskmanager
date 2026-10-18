/**
 * Settings Module Interactions
 */
window.SettingsModule = {
    async saveGeneralSettings(formElement) {
        const formData = new FormData(formElement);
        formData.append('action', 'save_settings');
        const res = await fetch('/taskmanager/ajax?action=save_settings', {
            method: 'POST',
            body: formData
        });
        return await res.json();
    }
};
