// modules/servers/settings.js
// Module description: This module helps with managing server settings.

export default class SettingsManager {
    constructor() {}

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

                    const textarea = popupEl.querySelector("#message-input");
                    const preview = popupEl.querySelector("#message-input-bg");
                    const messageInputWrapper = popupEl.querySelector('.message-input-wrapper');
                    const inputContainer = popupEl.querySelector(".input-container-2");
                    const maxMessageLengthEl = popupEl.querySelector('.max-message-length');
                    const maxCharactersLeftEl = popupEl.querySelector('.max-characters-left');

                    const maxHeight = 250;
                    const warningThreshold = 200;
                    const maxChars = 200;

                    const updateHeight = () => {
                        const scrollHeight = Math.max(textarea.scrollHeight, 18);
                        const newHeight = Math.min(scrollHeight, maxHeight);
                        messageInputWrapper.style.height = newHeight + 'px';
                        inputContainer.style.minHeight = newHeight + 'px';
                    };

                    const updateCharsLeft = () => {
                        const charsLeft = maxChars - textarea.textContent.length;
                        if (charsLeft <= warningThreshold) {
                            maxMessageLengthEl.style.display = 'flex';
                            maxCharactersLeftEl.textContent = charsLeft;
                            maxCharactersLeftEl.classList.toggle('debt', charsLeft < 0);
                        } else {
                            maxMessageLengthEl.style.display = 'none';
                            maxCharactersLeftEl.classList.remove('debt');
                        }
                    };

                    const updatePreview = () => {
                        preview.innerHTML = sanitize(textarea.textContent);
                    };

                    const onInput = () => {
                        if (textarea.textContent.trim() === '') textarea.textContent = '';
                        console.log(textarea.textContent);
                        updateHeight();
                        updateCharsLeft();
                        updatePreview();
                    };

                    textarea.addEventListener('input', onInput);

                    textarea.addEventListener('paste', (e) => {
                        e.preventDefault();
                        const text = e.clipboardData.getData('text/plain');
                        document.execCommand('insertText', false, text);
                        textarea.dispatchEvent(new Event('input'));
                    });

                    textarea.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            document.execCommand('insertHTML', false, '\n\n');
                            textarea.dispatchEvent(new Event('input'));
                        }
                    });

                    inputContainer.addEventListener("click", () => textarea.focus());

                    onInput();
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