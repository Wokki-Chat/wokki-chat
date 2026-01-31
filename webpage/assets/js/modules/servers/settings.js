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
                    const textarea = document.getElementById("message-input");
                    const preview = document.getElementById("message-input-bg");

                    const minHeight = 18;

                    if (textarea) {
                        const maxHeight = 250;
                        const warningThreshold = 200;
                        const maxChars = 200;

                        const messageInputWrapper = document.querySelector('.message-input-wrapper');

                        const maxMessageLengthEl = document.querySelector('.max-message-length');
                        const maxCharactersLeftEl = document.querySelector('.max-characters-left');

                        document.querySelector(".input-container-2").addEventListener("click", () => textarea.focus());
                        
                        const inputContainer = document.querySelector(".input-container-2");

                        const updateHeight = () => {
                            let newHeight = Math.min(textarea.scrollHeight, maxHeight);
                            messageInputWrapper.style.height = newHeight + 'px';
                            inputContainer.style.minHeight = newHeight + 'px';
                        };

                        const updateCharsLeft = () => {
                            const charsLeft = maxChars - textarea.innerText.length;
                            if (charsLeft <= warningThreshold) {
                                maxMessageLengthEl.style.display = 'flex';
                                maxCharactersLeftEl.textContent = charsLeft;
                                maxCharactersLeftEl.classList.toggle('debt', charsLeft < 0);
                            } else {
                                maxMessageLengthEl.style.display = 'none';
                                maxCharactersLeftEl.classList.remove('debt');
                            }
                        };

                        textarea.addEventListener('input', (e) => {
                            if (e.target !== textarea) return;

                            if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
                                textarea.innerHTML = '';
                            }

                            updateHeight();
                            updateCharsLeft();

                            preview.innerHTML = renderMarkdownInTextarea(textarea.innerText);
                        });

                        textarea.addEventListener('input', (e) => {
                            if (e.target !== textarea) return;

                            if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
                                textarea.innerHTML = '';;
                            }

                            updateHeight();
                            updateCharsLeft();

                            preview.innerHTML = renderMarkdownInTextarea(textarea.innerText);
                        });

                        textarea.addEventListener('paste', (e) => {
                            e.preventDefault();

                            const text = e.clipboardData.getData('text/plain');

                            const selection = window.getSelection();
                            if (!selection.rangeCount) return;
                            selection.deleteFromDocument();
                            selection.getRangeAt(0).insertNode(document.createTextNode(text));

                            selection.collapseToEnd();

                            textarea.dispatchEvent(new Event('input'));
                        });

                        textarea.addEventListener('keydown', (e) => {
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

                                textarea.dispatchEvent(new Event('input'));
                            }
                        });

                        updateHeight();
                        updateCharsLeft();
                        preview.innerHTML = renderMarkdownInTextarea(textarea.innerText);
                    }
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