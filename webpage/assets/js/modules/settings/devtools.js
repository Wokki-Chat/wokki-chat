// modules/settings/devtools.js
// Module description: This module helps with the devtools panel in the settings page.

export default class DevtoolsPanel {
    constructor({ access_token, socket, worker_name = "Unknown Worker" }) {
        this.socket = socket;
        this.worker_name = worker_name;
    }

    async checkServerAvailability(url, type = 'text') {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000);
            
            const response = await fetch(url, {
                method: 'GET',
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) return false;
            
            if (type === 'json') {
                const data = await response.json();
                return data.status === 'online';
            } else {
                const text = await response.text();
                return text.trim() === 'OK';
            }
        } catch (error) {
            return false;
        }
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
            
            const isAvailable = await this.checkServerAvailability("https://chat.wokki20.nl/app/check_worker?worker=Ignis", 'json');
            if (!isAvailable) {
                switchServerBtn.disabled = true;
                switchServerBtn.innerText = "Production server is not available";
                switchServerBtn.style.opacity = "0.5";
                switchServerBtn.style.cursor = "not-allowed";
                switchServerBtn.style.pointerEvents = "none";
            }
        } else if (location.hostname === "chat.wokki20.nl") {
            connectedServerEl.innerText = "Production server (chat.wokki20.nl)";
            switchServerBtn.href = "https://localhost:8443/settings/devtools";
            switchServerBtn.innerText = "Switch to development server";
            
            const isAvailable = await this.checkServerAvailability("http://localhost:5001/health", 'text');
            if (!isAvailable) {
                switchServerBtn.disabled = true;
                switchServerBtn.innerText = "Development server is not available";
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