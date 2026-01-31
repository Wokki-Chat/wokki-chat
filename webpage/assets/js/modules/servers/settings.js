// modules/servers/settings.js
// Module description: This module helps with managing server settings.

export default class SettingsManager {
    constructor() {}

    async open() {
        const response = await fetch('/assets/html/server/settings/ui.html');
        const settingsContent = await response.text();
        jspt.makePopup({
            content_type: "html",
            header: "Server Settings",
            content: settingsContent,
            close_button: false,
            custom_id: "server-settings-popup",
        });
    }
}