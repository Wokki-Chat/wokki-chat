import * as jspt from "https://cdn.wokki20.nl/content/jspt-v2.1.0/jspt.module.js";
import { Sanitizer, TextareaFormatter } from "../modules/global/sanitization.js";
function initBotInfo() {
    const el = document.querySelector('wchat-allowed-scripts');
    const scripts = el.getAttribute('value').split(';');
    if (!scripts.includes('developer/bot_info.js')) return;

    const profilePicturePreview = document.querySelector(".profile-picture-preview");
    const bot_id = document.getElementById("bot-id").getAttribute("value");
    const access_token = document.getElementById("access-token").getAttribute("value");

    const textareaFormatter = new TextareaFormatter();

    const textarea = document.getElementById("message-input");
    const preview = document.getElementById("message-input-bg");

    let newProfilePictureFile = null;
    let originalBotName = document.getElementById("name").value;
    let originalBotBio = textarea ? textarea.innerText : document.getElementById("bio").value;
    let originalProfilePicture = profilePicturePreview.src;

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

            preview.innerHTML = textareaFormatter.format(textarea.innerText);
            
            if (botChanged()) showSaveResetButtons();
            else document.querySelector(".unsaved-changes-container")?.remove();
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

                const br1 = document.createElement('br');
                const br2 = document.createElement('br');
                
                range.insertNode(br2);
                range.insertNode(br1);

                range.setStartAfter(br1);
                range.setEndAfter(br1);
                selection.removeAllRanges();
                selection.addRange(range);

                textarea.dispatchEvent(new Event('input'));
            }
        });

        textarea.addEventListener("input", () => {
            if (botChanged()) showSaveResetButtons();
            else document.querySelector(".unsaved-changes-container")?.remove();
        });

        updateHeight();
        updateCharsLeft();
        preview.innerHTML = textareaFormatter.format(textarea.innerText);
    }

    const fileInput = document.createElement("input");
    fileInput.type = "file";
    fileInput.accept = "image/png,image/jpeg,image/jpg,image/webp,image/gif";
    fileInput.style.display = "none";
    document.body.appendChild(fileInput);

    profilePicturePreview.addEventListener("click", () => {
        fileInput.click();
    });

    fileInput.addEventListener("change", () => {
        const file = fileInput.files[0];
        if (!file) {
            profilePicturePreview.src = originalProfilePicture;
            return;
        }

        const allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp", "image/gif"];
        if (!allowedTypes.includes(file.type)) {
            jspt.makeToast({
                message: "File type not allowed. Please upload a valid image file.",
                style: "default-error",
                duration: 3000
            });
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            profilePicturePreview.src = e.target.result;
            profilePicturePreview.dataset.tempSrc = e.target.result;
        };
        reader.readAsDataURL(file);

        newProfilePictureFile = file;
        showSaveResetButtons();
    });

    const botChanged = () => {
        const currentBio = textarea ? textarea.innerText.replace(/\n+/g, '\n').trim() : '';
        const originalBioNormalized = originalBotBio.replace(/\n+/g, '\n').trim();
        
        return document.getElementById("name").value !== originalBotName ||
            currentBio !== originalBioNormalized ||
            !!profilePicturePreview.dataset.tempSrc;
    };
    const showSaveResetButtons = () => {
        let container = document.querySelector(".unsaved-changes-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "unsaved-changes-container";

            const message = document.createElement("span");
            message.className = "unsaved-changes-message";
            message.textContent = "You have unsaved changes. Please save or reset before leaving.";

            const buttonsDiv = document.createElement("div");
            buttonsDiv.className = "unsaved-changes-buttons";

            const resetBtn = document.createElement("button");
            resetBtn.textContent = "Reset";
            resetBtn.className = "link reset-account-changes-button";

            const saveBtn = document.createElement("button");
            saveBtn.textContent = "Save";
            saveBtn.className = "button-primary-filled";

            buttonsDiv.appendChild(resetBtn);
            buttonsDiv.appendChild(saveBtn);
            container.appendChild(message);
            container.appendChild(buttonsDiv);
            document.querySelector(".content").appendChild(container);

            resetBtn.addEventListener("click", () => {
                document.getElementById("name").value = originalBotName;
                if (textarea) textarea.innerText = originalBotBio;
                if (preview) preview.innerHTML = textareaFormatter.format(originalBotBio);
                if (profilePicturePreview.dataset.tempSrc) {
                    profilePicturePreview.src = originalProfilePicture;
                    delete profilePicturePreview.dataset.tempSrc;
                }
                newProfilePictureFile = null;
                container.remove();
            });

            saveBtn.addEventListener("click", () => {
                const botName = document.getElementById("name").value;
                const botBio = textarea ? textarea.innerText : document.getElementById("bio").value;

                if (botName === "") {
                    jspt.makeToast({
                        message: "Please enter a bot name.",
                        style: "default-error",
                        duration: 3000
                    });
                    return;
                }

                const formData = new FormData();
                if (profilePicturePreview.dataset.tempSrc) {
                    formData.append("profile_picture", newProfilePictureFile);
                }
                if (botName !== originalBotName) {
                    formData.append("bot_name", botName);
                }
                if (botBio !== originalBotBio) {
                    formData.append("bot_bio", botBio);
                }
                formData.append("bot_id", bot_id);

                fetch("/app/update_bot", {
                    method: "POST",
                    headers: {
                        "Authorization": `Bearer ${access_token}`
                    },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === "success") {
                        originalBotName = botName;
                        originalBotBio = botBio;
                        if (profilePicturePreview.dataset.tempSrc) {
                            originalProfilePicture = profilePicturePreview.src;
                            delete profilePicturePreview.dataset.tempSrc;
                        }
                        newProfilePictureFile = null;
                        container.remove();
                        jspt.makeToast({
                            message: "Bot info updated successfully.",
                            style: "default",
                            duration: 3000
                        });
                    } else {
                        throw new Error(data.message || "Update failed");
                    }
                })
                .catch(() => {
                    jspt.makeToast({
                        message: "Failed to update bot info.",
                        style: "default-error",
                        duration: 3000
                    });
                });
            });
        }
    };

    document.getElementById("name").addEventListener("input", () => {
        if (botChanged()) showSaveResetButtons();
        else document.querySelector(".unsaved-changes-container")?.remove();
    });


    const copyBotIdBtn = document.getElementById("copy-id-btn");
    copyBotIdBtn.addEventListener("click", () => {
        navigator.clipboard.writeText(bot_id);
        jspt.makeToast({
            message: "Bot ID copied to clipboard.",
            style: "default",
            duration: 3000
        });
    });
}
if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initBotInfo();
    });
}
initBotInfo();