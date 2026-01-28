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

const alwaysKeep = [
    "/assets/js/load_scripts.js",
    "/assets/js/socket.js"
];

const activeScripts = {};

function loadScripts() {
    const container = document.querySelector("wchat-allowed-scripts");
    if (!container) return;

    const scripts = container
        .getAttribute("value")
        .split(";")
        .map(s => s.trim())
        .filter(s => s !== "");

    scripts.forEach(src => {
        src = `/assets/js/${src}`;
        if (activeScripts[src]) return;

        const scriptTag = document.createElement("script");
        scriptTag.src = src;
        scriptTag.type = "module";
        document.body.appendChild(scriptTag);
        activeScripts[src] = scriptTag;
    });
}

function unloadScripts() {
    document.querySelectorAll("body script").forEach(s => {
        if (!alwaysKeep.includes(s.src)) {
            s.remove();
            delete activeScripts[s.src];
        }
    });
}

function reloadAllowedScripts() {
    unloadScripts();
    loadScripts();
}

document.addEventListener("DOMContentLoaded", () => {
    loadScripts();
});

if (window.swup) {
    window.swup.hooks.on("page:view", () => {
        reloadAllowedScripts();
    });
}