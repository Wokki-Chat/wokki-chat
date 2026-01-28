document.addEventListener('DOMContentLoaded', () => {
    initScripts();
});

window.swup.hooks.on('page:view', () => {
    resetScripts();
});

function initScripts() {
    loadScripts();
}

function resetScripts() {
    document.querySelectorAll('script:not([ignore-unload])').forEach(s => s.remove());
    loadScripts();
}

function loadScripts() {
    const container = document.querySelector("wchat-allowed-scripts");
    if (!container) return;

    const allowedScripts = container.getAttribute("value").split(";").map(s => s.trim()).filter(s => s !== "");

    allowedScripts.forEach(script => {
        const src = `/assets/js/${script}`;
        if (document.querySelector(`script[src="${src}"]`)) return;

        const scriptTag = document.createElement("script");
        scriptTag.src = src;
        scriptTag.type = "module";
        document.body.appendChild(scriptTag);
    });
}
