// Modules/Storage/Assets/js/storage.js
window.TaskManagerStorage = {
    validateFile: function(file, maxSizeMb, allowedExtensions) {
        const sizeBytes = file.size;
        const maxBytes = maxSizeMb * 1024 * 1024;
        if (sizeBytes > maxBytes) {
            const msg = (window.STORAGE_I18N && window.STORAGE_I18N.file_too_large) ? window.STORAGE_I18N.file_too_large : 'File size exceeds limit.';
            return { valid: false, error: msg };
        }
        const ext = file.name.split('.').pop().toLowerCase();
        if (allowedExtensions && !allowedExtensions.includes(ext)) {
            const msg = (window.STORAGE_I18N && window.STORAGE_I18N.invalid_extension) ? window.STORAGE_I18N.invalid_extension : 'Invalid file extension.';
            return { valid: false, error: msg };
        }
        return { valid: true };
    }
};
