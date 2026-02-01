function initProfile() {
    const el = document.querySelector('wchat-allowed-scripts');
    const scripts = el.getAttribute('value').split(';');
    if (!scripts.includes('profile.js')) return;

    const lastUrl = document.getElementById("last-url").getAttribute("value");

    const serverBar = document.querySelector(".server-bar");
    if (serverBar) serverBar.classList.remove("hidden");

    const serverbar_servers = document.querySelectorAll('#server-bar-item-server');
    serverbar_servers.forEach(el => el.classList.remove('active'));
    document.getElementById("server-bar-item-home").classList.add("active");

    if (typeof window.swup !== "undefined") {
        window.swup.navigate(lastUrl);
    }
}
if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initProfile();
    });
}
initProfile();