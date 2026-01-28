function loadScripts() {
    const allowedScripts = document.querySelector("wchat-allowed-scripts").getAttribute("value").split(";");
    allowedScripts
        .filter(script => script.trim() !== "")
        .forEach(script => {
            const src = `/assets/js/${script}`;
            if (document.querySelector(`script[src="${src}"]`)) return;
            const scriptTag = document.createElement("script");
            scriptTag.src = src;
            document.body.appendChild(scriptTag);
        });
}

loadScripts();