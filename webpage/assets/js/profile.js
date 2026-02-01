import { UserPopupManager } from "./modules/users/popups.js";
function initProfile() {
    const el = document.querySelector('wchat-allowed-scripts');
    const scripts = el.getAttribute('value').split(';');
    if (!scripts.includes('profile.js')) return;

    const access_token = document.getElementById("access-token").getAttribute("value");
    const user_id = document.getElementById("user-id").getAttribute("value");
    const lastPage = document.getElementById("last-page").getAttribute("value");
    const requested_user_id = document.getElementById("requested-user-id").getAttribute("value");

    const serverBar = document.querySelector(".server-bar");
    if (serverBar) serverBar.classList.remove("hidden");

    const serverbar_servers = document.querySelectorAll('#server-bar-item-server');
    serverbar_servers.forEach(el => el.classList.remove('active'));
    document.getElementById("server-bar-item-home").classList.add("active");

    const userPopupManager = new UserPopupManager({ user_id, access_token });

    userPopupManager.openExtendedPopup(requested_user_id);
    if (typeof window.swup !== "undefined") {
        window.swup.navigate(lastPage);
    }
}
if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initProfile();
    });
}
initProfile();