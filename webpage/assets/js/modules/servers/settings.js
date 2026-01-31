// modules/servers/settings.js
// Module description: This module helps with managing server settings.

export default class SettingsManager {
    constructor() {}

    async open() {
        let settingsContent = await fetch('/assets/html/server/settings/ui.html').then(r => r.text());

        let generalInfoContent = await fetch('/assets/html/server/settings/pages/general_info.html').then(r => r.text());

        const serverNameEl = document.querySelector('[wchat-data][id="server-name"]');
        const serverDescriptionEl = document.querySelector('[wchat-data][id="server-description"]');

        const serverName = serverNameEl ? serverNameEl.textContent.trim() : '';
        const serverDescription = serverDescriptionEl ? serverDescriptionEl.textContent.trim() : '';

        const canManageServer = window.permissions?.manage_server ?? false;
        const allowAttr = canManageServer ? '' : 'disabled';

        generalInfoContent = generalInfoContent
            .replace(/{{server_name}}/g, serverName)
            .replace(/{{description}}/g, serverDescription)
            .replace(/{{allow_server_management}}/g, allowAttr);

        settingsContent = settingsContent.replace('{{settings_content}}', generalInfoContent);

        jspt.makePopup({
            content_type: "html",
            header: "Server Settings",
            style: "server-settings-popup",
            content: settingsContent,
            close_button: false,
            custom_id: "server-settings-popup",
        });

        const popupEl = document.getElementById("server-settings-popup");

        const defaultTab = popupEl.querySelector("#general-info-tab");
        if (defaultTab) defaultTab.classList.add("active");

        popupEl.querySelectorAll(".settings-tab").forEach(tab => {
            tab.addEventListener("click", async () => {
                popupEl.querySelectorAll(".settings-tab").forEach(t => t.classList.remove("active"));
                tab.classList.add("active");

                const pageFile = `/assets/html/server/settings/pages/${tab.dataset.page}.html`;
                let pageContent = await fetch(pageFile).then(r => r.text());

                if (tab.dataset.page === 'general_info') {
                    pageContent = pageContent
                        .replace(/{{server_name}}/g, serverName)
                        .replace(/{{description}}/g, serverDescription)
                        .replace(/{{allow_server_management}}/g, allowAttr);
                }

                const contentContainer = popupEl.querySelector("#settings-page-content");
                if (contentContainer) contentContainer.innerHTML = pageContent;
            });
        });

        const closeBtn = popupEl.querySelector(".close-settings-container");
        closeBtn.addEventListener("click", () => {
            jspt.closePopup("server-settings-popup");
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                const popup = document.getElementById("server-settings-popup");
                if (popup) jspt.closePopup("server-settings-popup");
            }
        });

        popupEl.addEventListener("click", (e) => {
            if (!e.target.closest(".popup")) {
                jspt.closePopup("server-settings-popup");
            }
        });
    }
}