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
        
        this.notificationSound = new Audio('/assets/sounds/notification.mp3');
        this.notificationSound.volume = 0.5;
        
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
            title = `${senderName}`;
            body = message;
            tag = `server-${server_id}-channel-${channel_id}`;
        }

        const options = {
            body: body,
            icon: sender_info.profile_picture || '/uploads/profile-pictures/default-profile.png',
            badge: '/assets/images/branding/logo-purple-hires.png',
            tag: tag,
            requireInteraction: false,
            silent: true
        };

        const notification = new Notification(title, options);

        this.playNotificationSound();

        notification.onclick = () => {
            window.focus();
            notification.close();
        };
    }

    playNotificationSound() {
        const sound = this.notificationSound.cloneNode();
        sound.play()
    }
}