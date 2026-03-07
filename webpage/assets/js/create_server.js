import * as jspt from "https://cdn.wokki20.nl/content/jspt-v2.1.0/jspt.module.js";

function openCreateServerModal() {
    const access_token = document.getElementById("access-token")?.getAttribute("value");
    
    if (!access_token) {
        return;
    }

    const modalContent = `
        <form id="create-server-form" class="create-channel-form">
            <label for="server-icon">Server Icon (optional):</label>
            <label for="server-icon" id="icon-preview" class="icon-preview">
                <span class="material-symbols-rounded upload-icon">add</span>
            </label>
            <input type="file" id="server-icon" name="server-icon" accept="image/jpeg,image/png,image/gif" class="input-file-dark-bg file-input" style="display: none;">
            <label for="server-name" style="margin-top: 15px;">Server Name:</label>
            <input type="text" id="server-name" name="server-name" class="input-text-dark-bg w270" required maxlength="50" placeholder="Server name (up to 50 characters)">
            <button type="submit" class="button-primary-filled">Create Server</button>
        </form>
    `;

    const popupId = `create-server-popup-${Date.now()}`;

    jspt.makePopup({
        content_type: 'html',
        header: 'Create Server',
        content: modalContent,
        close_on_blur: true,
        custom_id: popupId
    });

    setTimeout(() => {
        const serverIconInput = document.getElementById("server-icon");
        const iconPreview = document.getElementById("icon-preview");
        const createServerForm = document.getElementById("create-server-form");

        if (!serverIconInput || !iconPreview || !createServerForm) {
            return;
        }

        serverIconInput.addEventListener("change", () => {
            const file = serverIconInput.files[0];
            if (file && file.type.startsWith("image/")) {
                const img = new Image();
                img.onload = () => {
                    if (img.width < 50 || img.height < 50) {
                        Toastify({
                            text: "Image dimensions must be at least 50x50px.",
                            duration: 5000,
                            gravity: "bottom",
                            position: "right",
                            close: true,
                            stopOnFocus: true,
                            style: {
                                background: "var(--clr-popup-a20)",
                                borderRadius: "12px",
                                boxShadow: "none"
                            }
                        }).showToast();
                        serverIconInput.value = "";
                        iconPreview.innerHTML = `<span class="material-symbols-rounded upload-icon">add</span>`;
                    } else {
                        iconPreview.innerHTML = `<img src="${img.src}" alt="Server Icon" style="width: 100%; height: 100%; object-fit: cover;">`;
                    }
                };
                const reader = new FileReader();
                reader.onload = e => {
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                iconPreview.innerHTML = `<span class="material-symbols-rounded upload-icon">add</span>`;
            }
        });

        createServerForm.addEventListener("submit", (event) => {
            event.preventDefault();

            const formData = new FormData(createServerForm);
            const serverName = formData.get("server-name");
            const serverIconFile = formData.get("server-icon");

            const submitData = new FormData();
            submitData.append("server_name", serverName);
            if (serverIconFile && serverIconFile.size > 0) {
                submitData.append("image", serverIconFile);
            }

            fetch("/app/create_server", {
                method: "POST",
                headers: {
                    Authorization: `Bearer ${access_token}`,
                },
                body: submitData,
            })
            .then((response) => response.json())
            .then((data) => {
                const server_id = data.server_id;
                window.location.href = `/server/${server_id}`;
            })
            .catch((error) => {
				jspt.makeToast({
					message: "Failed to create server",
					style: "default-error",
					duration: 3000,
					close_on_click: true
				})
            })
            .finally(() => {
                jspt.closePopup({ custom_id: popupId });
            });
        });
    }, 0);
}

function initCreateServer() {
    const createServerBtn = document.getElementById("open-create-server-modal");
    if (createServerBtn) {
        createServerBtn.removeEventListener("click", openCreateServerModal);
        createServerBtn.addEventListener("click", openCreateServerModal);
    }
}

initCreateServer();

if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', () => {
        initCreateServer();
    });
}