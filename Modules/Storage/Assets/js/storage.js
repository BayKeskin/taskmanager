// Modules/Storage/Assets/js/storage.js
window.TaskManagerStorage = {
    validateFile: function(file, maxSizeMb, allowedExtensions) {
        const sizeBytes = file.size;
        const maxBytes = maxSizeMb * 1024 * 1024;
        if (sizeBytes > maxBytes) {
            return { valid: false, error: 'Dosya boyutu sınırı aşıyor.' };
        }
        const ext = file.name.split('.').pop().toLowerCase();
        if (allowedExtensions && !allowedExtensions.includes(ext)) {
            return { valid: false, error: 'İzin verilmeyen dosya formatı.' };
        }
        return { valid: true };
    }
};
