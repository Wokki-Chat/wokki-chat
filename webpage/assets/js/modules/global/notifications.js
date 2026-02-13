// modules/global/notifications.js
// Module description: This module helps with showing notifications.

export class NotificationsManager {
    constructor({ user_id, channel_id = null, server_id = null, access_token, socket, contact_id = null }) {
        this.user_id = user_id;
        this.channel_id = channel_id;
        this.server_id = server_id;
        this.access_token = access_token;
        this.socket = socket;
        this.contact_id = contact_id;
        this.permissionGranted = false;
        
        this.requestPermission();
    }

    async requestPermission() {
        if (!("Notification" in window)) {
            return;
        }

        if (Notification.permission === "granted") {
            this.permissionGranted = true;
        } else if (Notification.permission !== "denied") {
            const permission = await Notification.requestPermission();
            this.permissionGranted = permission === "granted";
        }
    }

    listen() {
        this.socket.on("new_message_notification", (data) => this.handleNewMessageNotification(data));
    }

    handleNewMessageNotification(data) {
        if (data.contact_id && data.contact_id == this.contact_id) return;
        if (data.server_id && data.channel_id && data.server_id != this.server_id && data.channel_id != this.channel_id) return;
        
        this.showNotification(data);
    }

    showNotification(data) {
        if (!this.permissionGranted) return;

        const { sender_info, message, server_id, channel_id, contact_id } = data;
        
        const senderName = sender_info.display_name || sender_info.username;
        let title, body, tag;
        
        if (contact_id) {
            title = senderName;
            body = message;
            tag = `contact-${contact_id}`;
        } else {
            title = `${senderName} in channel`;
            body = message;
            tag = `server-${server_id}-channel-${channel_id}`;
        }

        const options = {
            body: body,
            icon: sender_info.profile_picture,
            badge: sender_info.profile_picture,
            tag: tag,
            requireInteraction: false,
            silent: false
        };

        const notification = new Notification(title, options);

        notification.onclick = () => {
            window.focus();
            notification.close();
        };
    }
}