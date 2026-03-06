import * as jspt from "https://cdn.wokki20.nl/content/jspt-v2.1.0/jspt.module.js";
import { Sanitizer, TextareaFormatter } from "../modules/global/sanitization.js";
function initBotInfo() {
    const el = document.querySelector('wchat-allowed-scripts');
    const scripts = el.getAttribute('value').split(';');
    if (!scripts.includes('developer/bot_installation.js')) return;

    const profilePicturePreview = document.querySelector(".profile-picture-preview");
    const bot_id = document.getElementById("bot-id").getAttribute("value");
    const access_token = document.getElementById("access-token").getAttribute("value");

    const showTokenBtn = document.getElementById("show-btn");
    const copyTokenBtn = document.getElementById("copy-btn");
    const copyInviteLinkBtn = document.getElementById("copy-invite-btn");

    let tokenShowing = false;
    showTokenBtn.addEventListener("click", () => {
        if (tokenShowing) {
            document.getElementById("bot-token").type = "password";
            showTokenBtn.innerText = "Show Token";
            tokenShowing = false;
        } else {
            document.getElementById("bot-token").type = "text";
            showTokenBtn.innerText = "Hide Token";
            tokenShowing = true;
        }
    });

    copyTokenBtn.addEventListener("click", () => {
        const token = document.getElementById("bot-token").value;
        navigator.clipboard.writeText(token).then(() => {
            jspt.makeToast({
                message: "Token copied to clipboard!",
                style: "default",
                duration: 3000
            });
        });
    });

    copyInviteLinkBtn.addEventListener("click", () => {
        const inviteLink = document.getElementById("bot-invite").value;
        navigator.clipboard.writeText(inviteLink).then(() => {
            jspt.makeToast({
                message: "Invite link copied to clipboard.",
                style: "default",
                duration: 3000
            });
        });
    });
}
if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initBotInfo();
    });
}
initBotInfo();