/**
 * Modules/VisualPins/Assets/js/visual-pins.js
 * In-House BugHerd Alternatifi: Görsel Pinleme İstemci Kütüphanesi
 */
(function() {
    window.VisualPinsModule = {
        init: function() {
            console.log("VisualPins Module loaded and active.");
        },
        createPin: function(data) {
            const base = window.BASE_URL || '';
            return fetch(base + '/api/v1/visual-feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(res => res.json());
        }
    };
})();
