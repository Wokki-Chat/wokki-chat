// modules/servers/settings.js
// Module description: This module helps with managing server settings.
import { Sanitizer } from "../global/sanitization";

export default class SettingsManager {
    constructor() {
        this.sanitizer = new Sanitizer();
    }

    async open() {
        let settingsContent = await fetch('/assets/html/server/settings/ui.html').then(r => r.text());
        let generalInfoContent = await fetch('/assets/html/server/settings/pages/general_info.html').then(r => r.text());

        const serverNameEl = document.getElementById('server-name');
        const serverDescriptionEl = document.getElementById('server-description');

        const serverName = serverNameEl ? serverNameEl.getAttribute('value') : '';
        const serverDescription = serverDescriptionEl ? serverDescriptionEl.getAttribute('value') : '';

        const permissionsEl = document.getElementById('permissions');
        let canManageServer = false;
        if (permissionsEl) {
            try {
                const permissions = JSON.parse(permissionsEl.getAttribute('value'));
                canManageServer = !!permissions.manage_server;
            } catch (e) {
                console.error("Failed to parse permissions JSON", e);
            }
        }
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

        const setupGeneralInfo = () => {
            const serverSettingsTextarea = popupEl.querySelector("#message-input");
            const serverSettingsPreview = popupEl.querySelector("#message-input-bg");
            const serverSettingsMessageInputWrapper = popupEl.querySelector('.message-input-wrapper');
            const serverSettingsInputContainer = popupEl.querySelector(".input-container-2");
            const serverSettingsMaxMessageLengthEl = popupEl.querySelector('.max-message-length');
            const serverSettingsMaxCharactersLeftEl = popupEl.querySelector('.max-characters-left');

            const maxHeight = 250;
            const warningThreshold = 200;
            const maxChars = 200;

            const updateHeight = () => {
                let newHeight = Math.min(serverSettingsTextarea.scrollHeight, maxHeight);
                serverSettingsMessageInputWrapper.style.height = newHeight + 'px';
                serverSettingsInputContainer.style.minHeight = newHeight + 'px';
            };

            const updateCharsLeft = () => {
                const charsLeft = maxChars - serverSettingsTextarea.innerText.length;
                if (charsLeft <= warningThreshold) {
                    serverSettingsMaxMessageLengthEl.style.display = 'flex';
                    serverSettingsMaxCharactersLeftEl.textContent = charsLeft;
                    serverSettingsMaxCharactersLeftEl.classList.toggle('debt', charsLeft < 0);
                } else {
                    serverSettingsMaxMessageLengthEl.style.display = 'none';
                    serverSettingsMaxCharactersLeftEl.classList.remove('debt');
                }
            };

            const inputHandler = (e) => {
                if (e.target !== serverSettingsTextarea) return;

                if (serverSettingsTextarea.textContent.trim() === '' && serverSettingsTextarea.innerHTML !== '') {
                    serverSettingsTextarea.innerHTML = '';
                }

                updateHeight();
                updateCharsLeft();
                serverSettingsPreview.innerHTML = this.sanitizer.sanitize(serverSettingsTextarea.innerText);
            };

            serverSettingsTextarea.removeEventListener('input', inputHandler);
            serverSettingsTextarea.addEventListener('input', inputHandler);

            serverSettingsTextarea.addEventListener('paste', (e) => {
                e.preventDefault();
                const text = e.clipboardData.getData('text/plain');
                const selection = window.getSelection();
                if (!selection.rangeCount) return;
                selection.deleteFromDocument();
                selection.getRangeAt(0).insertNode(document.createTextNode(text));
                selection.collapseToEnd();
                serverSettingsTextarea.dispatchEvent(new Event('input'));
            });

            serverSettingsTextarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const selection = window.getSelection();
                    if (!selection.rangeCount) return;
                    const range = selection.getRangeAt(0);
                    range.deleteContents();
                    const textNode = document.createTextNode('\n\n');
                    range.insertNode(textNode);
                    range.setStartAfter(textNode);
                    range.setEndAfter(textNode);
                    selection.removeAllRanges();
                    selection.addRange(range);
                    serverSettingsTextarea.dispatchEvent(new Event('input'));
                }
            });

            updateHeight();
            updateCharsLeft();
            serverSettingsPreview.innerHTML = this.sanitizer.sanitize(serverSettingsTextarea.innerText);
        };

        setupGeneralInfo();

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

                    const contentContainer = popupEl.querySelector("#settings-page-content");
                    if (contentContainer) contentContainer.innerHTML = pageContent;

                    setupGeneralInfo();
                } else {
                    const contentContainer = popupEl.querySelector("#settings-page-content");
                    if (contentContainer) contentContainer.innerHTML = pageContent;
                }
            });
        });

        const closeBtn = popupEl.querySelector(".close-settings-container");
        closeBtn.addEventListener("click", () => jspt.closePopup("server-settings-popup"));

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