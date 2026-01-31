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
            style: "server-settings-popup",
            content: settingsContent,
            close_button: false,
            custom_id: "server-settings-popup",
        });
        const closeBtn = document.getElementById("server-settings-popup").querySelector(".close-settings-container");
        closeBtn.addEventListener("click", () => {
            jspt.closePopup("server-settings-popup");
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                const popup = document.getElementById("server-settings-popup");
                if (popup) jspt.closePopup("server-settings-popup");
            }
        });
        document.getElementById("server-settings-popup").addEventListener("click", (e) => {
            if (!e.target.closest(".popup")) {
                jspt.closePopup("server-settings-popup");
            }
        });
    }
}