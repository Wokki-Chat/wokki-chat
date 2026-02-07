function initCreateServer() {
  const access_token = document.getElementById("access-token").getAttribute("value");
  function openCreateServerModal() {
    let modalHtml = `
      <div class="modal" id="create-server-modal">
          <div class="modal-content">
              <div class="modal-header">
                  <h2 class="modal-title">Create Server</h2>
                  <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
              </div>
              <div class="modal-body">
                  <form id="create-server-form" class="create-channel-form">
                      <label for="server-icon">Server Icon (optional):</label>
                      <label for="server-icon" id="icon-preview" class="icon-preview" >
                          <span class="material-symbols-rounded upload-icon">add</span>
                      </label>
                      <input type="file" id="server-icon" name="server-icon" accept="image/jpeg,image/png,image/gif" class="input-file-dark-bg file-input" style="display: none;">
                      <label for="server-name" style="margin-top: 15px;">Server Name:</label>
                      <input type="text" id="server-name" name="server-name" class="input-text-dark-bg w270" required maxlength="50" placeholder="Server name (up to 50 characters)">
                      <button type="submit" class="button-primary-filled">Create Server</button>
                  </form>
              </div>
          </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHtml);

    const modal = document.getElementById("create-server-modal");
    const modalContent = modal.querySelector(".modal-content");

    setTimeout(() => {
      function handleClickOutside(event) {
        if (!modalContent.contains(event.target)) {
          modal.remove();
          document.removeEventListener("click", handleClickOutside);
        }
      }
      document.addEventListener("click", handleClickOutside);
    }, 10);

    const modalCloseBtn = document.getElementById("close-modal-btn");
    modalCloseBtn.addEventListener("click", () => {
      modal.remove();
    });

    const serverIconInput = document.getElementById("server-icon");
    const iconPreview = document.getElementById("icon-preview");

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

    const createChannelForm = document.getElementById("create-server-form");
    createChannelForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const serverName = document.getElementById("server-name").value;
      const serverIconFile = serverIconInput.files[0];

      const formData = new FormData();
      formData.append("server_name", serverName);
      if (serverIconFile) {
      formData.append("image", serverIconFile);
      }

      fetch("https://chat.wokki20.nl/app/create_server.php", {
      method: "POST",
      headers: {
          Authorization: `Bearer ${access_token}`,
      },
      body: formData,
      })
      .then((response) => response.json())
      .then((data) => {
      console.log("Server created:", data);
          const server_id = data.server_id;

          window.location.href = `https://chat.wokki20.nl/server/${server_id}`;
      })
      .catch((error) => {
      console.error("Error creating server:", error);
      Toastify({
          text: "Failed to create server.",
          duration: 5000,
          gravity: "bottom",
          position: "right",
          close: true,
          stopOnFocus: true,
          style: {
          background: "var(--clr-popup-a20)",
          borderRadius: "12px",
          boxShadow: "none",
          },
      }).showToast();
      })
      .finally(() => {
      modal.remove();
      });
      modal.remove();
    });
  }

  const createServerBtn = document.getElementById("open-create-server-modal");
  createServerBtn.addEventListener("click", openCreateServerModal);
}

initCreateServer();

if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initCreateServer();
	});
}