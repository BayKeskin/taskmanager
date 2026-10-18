// Modules/Chat/Assets/js/chat.js
window.TaskManagerChat = {
    fetchMessages: async function(receiverId = 0, lastId = 0) {
        const res = await fetch(`/taskmanager/ajax?action=get_chat_messages&receiver_id=${receiverId}&last_id=${lastId}`);
        return await res.json();
    },
    sendMessage: async function(formData) {
        const res = await fetch('/taskmanager/ajax?action=send_chat_message', {
            method: 'POST',
            body: formData
        });
        return await res.json();
    }
};
