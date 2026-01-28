import Swup from "https://unpkg.com/swup@4?module";
import SwupPreloadPlugin from "https://unpkg.com/@swup/preload-plugin@3?module";
import SwupScriptsPlugin from "https://unpkg.com/@swup/scripts-plugin@2?module";

window.swup = new Swup({
    containers: ["#app"],
    cache: true,
    plugins: [
        new SwupPreloadPlugin(),
        new SwupScriptsPlugin({
            body: true,
            head: false,
        })
    ]
});

document.addEventListener('DOMContentLoaded', () => {
    loadScripts();
});

window.swup.hooks.on('page:view', () => {
    resetScripts();
});

function resetScripts() {
    document.querySelectorAll('script:not([ignore-unload])').forEach(s => s.remove());
    loadScripts();
}

function loadScripts() {
    const container = document.querySelector("wchat-allowed-scripts");
    if (!container) return;

    const allowedScripts = container.getAttribute("value")
        .split(";")
        .map(s => s.trim())
        .filter(s => s !== "");

    allowedScripts.forEach(script => {
        const src = `/assets/js/${script}`;
        if (document.querySelector(`script[src="${src}"]`)) return;

        const scriptTag = document.createElement("script");
        scriptTag.src = src;
        scriptTag.type = "module";
        document.body.appendChild(scriptTag);
    });
}
