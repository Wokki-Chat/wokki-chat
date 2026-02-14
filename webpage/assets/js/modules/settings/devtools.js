// modules/settings/devtools.js
// Module description: This module helps with the devtools panel in the settings page.

export default class DevtoolsPanel {
    constructor({ access_token, socket, worker_name = "Unknown Worker" }) {
        this.socket = socket;
        this.worker_name = worker_name;
    }

    async checkServerAvailability(url) {
        return new Promise((resolve) => {
            const timeout = setTimeout(() => {
                img.src = '';
                resolve(false);
            }, 3000);

            const img = new Image();
            
            img.onload = () => {
                clearTimeout(timeout);
                resolve(true);
            };
            
            img.onerror = () => {
                clearTimeout(timeout);
                resolve(true);
            };
            
            img.src = `${url}/favicon.ico?t=${Date.now()}`;
        });
    }

    async init({ connectedWorkerEl, connectedServerEl, switchServerBtn }) {
        connectedWorkerEl.innerText = this.worker_name;
        this.socket.on("connected to server", (data) => {
            connectedWorkerEl.innerText = data.server_name;
            this.worker_name = data.server_name;
        });

        if (location.hostname === "localhost") {
            connectedServerEl.innerText = "Development server (localhost:5001)";
            switchServerBtn.href = "https://chat.wokki20.nl/settings/devtools";
            switchServerBtn.innerText = "Switch to production server";
            
            const isAvailable = await this.checkServerAvailability("https://chat.wokki20.nl");
            if (!isAvailable) {
                switchServerBtn.disabled = true;
                switchServerBtn.title = "Production server is not available";
                switchServerBtn.style.opacity = "0.5";
                switchServerBtn.style.cursor = "not-allowed";
                switchServerBtn.style.pointerEvents = "none";
            }
        } else if (location.hostname === "chat.wokki20.nl") {
            connectedServerEl.innerText = "Production server (chat.wokki20.nl)";
            switchServerBtn.href = "https://localhost:8443/settings/devtools";
            switchServerBtn.innerText = "Switch to development server";
            
            const isAvailable = await this.checkServerAvailability("https://localhost:8443");
            if (!isAvailable) {
                switchServerBtn.disabled = true;
                switchServerBtn.title = "Development server is not available";
                switchServerBtn.style.opacity = "0.5";
                switchServerBtn.style.cursor = "not-allowed";
                switchServerBtn.style.pointerEvents = "none";
            }
        } else {
            connectedServerEl.innerText = "Unknown server";
            switchServerBtn.href = "https://chat.wokki20.nl/settings/devtools";
        }
    }
}