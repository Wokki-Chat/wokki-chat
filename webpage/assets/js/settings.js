window.addEventListener("load", () => {



  if (active_tab === "account") {
    const editProfileButton = document.getElementById("edit-profile-button");
    const profilePicturePreview = document.querySelector(".profile-picture-preview");

    let profilePictureChanged = false;
    let newProfilePictureFile = null;
    let isEditing = false;

    editProfileButton.addEventListener("click", () => {
      if (!isEditing) {
        isEditing = true;
        editProfileButton.textContent = "Save";

        if (profilePicturePreview) {
          let wrapper = profilePicturePreview.parentElement;
          wrapper.classList.add("editing");

          const fileInput = document.createElement("input");
          fileInput.type = "file";
          fileInput.accept = premium ? "image/png,image/jpeg,image/jpg,image/webp,image/gif" : "image/png,image/jpeg,image/jpg,image/webp";
          fileInput.style.display = "none";
          document.body.appendChild(fileInput);

          profilePicturePreview.addEventListener("click", () => {
            fileInput.click();
          });

          fileInput.addEventListener("change", () => {
            const file = fileInput.files[0];
            if (!file) {
              profilePicturePreview.src = profile_picture;
              return;
            }

            const allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp"];
            if (premium) allowedTypes.push("image/gif");

            if (!allowedTypes.includes(file.type)) {
              Toastify({
                text: "File type not allowed. Please upload a valid image file.",
                className: "copy-code-failed",
                duration: 3000,
                close: true,
                gravity: "bottom",
                position: "right",
                style: {
                  background: "var(--clr-error-a0)",
                  boxShadow: "none",
                  borderRadius: "12px"
                }
              }).showToast();
              return;
            }

            const reader = new FileReader();
            reader.onload = e => {
              profilePicturePreview.src = e.target.result;
            };
            reader.readAsDataURL(file);

            newProfilePictureFile = file;
            profilePictureChanged = true;
          });
        }

      } else {
        if (profilePictureChanged && newProfilePictureFile) {
          const formData = new FormData();
          formData.append("profile_picture", newProfilePictureFile);
          fetch("/app/update_profile", {
            method: "POST",
            headers: {
              "Authorization": `Bearer ${access_token}`
            },
            body: formData
          })
          .then(res => res.json())
          .then(data => {
            if (data.status === "success") {
              Toastify({
                text: "Profile updated successfully!",
                className: "copy-code-success",
                duration: 3000,
                close: true,
                gravity: "bottom",
                position: "right",
                style: {
                  background: "var(--clr-popup-a10)",
                  boxShadow: "none",
                  borderRadius: "12px"
                }
              }).showToast();
              profilePictureChanged = false;
              newProfilePictureData = null;
            } else {
              throw new Error(data.message || "Update failed");
            }
          })
          .catch(err => {
            Toastify({
              text: "Something went wrong!",
              className: "copy-code-failed",
              duration: 3000,
              close: true,
              gravity: "bottom",
              position: "right",
              style: {
                background: "var(--clr-error-a0)",
                boxShadow: "none",
                borderRadius: "12px"
              }
            }).showToast();
          });
        }

        isEditing = false;
        editProfileButton.textContent = "Edit Profile";
        profilePicturePreview.parentElement.classList.remove("editing");
      }
    });
  }
    
  if (active_tab === "logs") {
    function loadLogs(firstload = false) {
        if (active_tab !== "logs") return;

        const params = new URLSearchParams();

        if (document.getElementById("worker-checkbox").checked) params.append("hide_worker_pid", "true");
        if (document.getElementById("timestamp-checkbox").checked) params.append("hide_timestamp", "true");
        if (document.getElementById("file-checkbox").checked) params.append("hide_file_name", "true");
        if (document.getElementById("type-checkbox").checked) params.append("hide_type", "true");
        if (document.getElementById("message-checkbox").checked) params.append("hide_message", "true");
        

        const url = `https://chat.wokki20.nl/developer/official/logs?${params.toString()}`;

        fetch(url, {
            method: "GET",
            headers: {
                "Authorization": `Bearer ${access_token}`
            }
        })
        .then(response => {
            if (!response.ok) throw new Error("Failed to fetch logs");
            return response.text();
        })
        .then(text => { 
            const logs = document.getElementById("logs");
            const lines = text.split("\n");

            const escapeHTML = str =>
                str.replace(/&/g, "&amp;")
                  .replace(/</g, "&lt;")
                  .replace(/>/g, "&gt;");

            const enableColor = document.getElementById("color-checkbox").checked;

            const coloredLines = lines.map(line => {
                if (!enableColor) return escapeHTML(line);

                const parts = line.match(/\[.*?\]/g) || [];
                let restOfLine = line;

                const coloredParts = parts.map(part => {
                    let color = 'inherit';

                    if (part.startsWith('[Worker PID:')) color = 'var(--clr-logs-green)';
                    else if (/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]$/.test(part)) color = 'var(--clr-logs-blue)';
                    else if (/^\[.*\.\w+(:\d+)?\]$/.test(part)) color = 'var(--clr-logs-purple)';
                    else if (/^\[.*\]$/.test(part)) color = 'var(--clr-logs-orange)';

                    restOfLine = restOfLine.replace(part, '');
                    return `<span style="color: ${color};">${part}</span> `;
                });

                return coloredParts.join('') + escapeHTML(restOfLine);
            });




            logs.innerHTML = coloredLines.join("<br>");
            const offset = 10;
            if (logs.scrollTop + logs.clientHeight >= logs.scrollHeight - offset && !firstload) logs.scrollTop = logs.scrollHeight;
            if (firstload) logs.scrollTop = logs.scrollHeight;
        });
    }

    loadLogs(true);

    const checkboxes = document.querySelectorAll(".log-option-checkbox");
    checkboxes.forEach(cb => cb.addEventListener("change", loadLogs));
    document.getElementById("color-checkbox").addEventListener("change", loadLogs);

    setInterval(loadLogs, 10000);

  }

  
});

function setTheme(theme) {
  document.cookie = `theme=${theme}; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/; SameSite=None; Secure;`;
  window.location.reload();
}

function openConnectionModal(connection) {
    connections.forEach(conn => {
        if (conn["connection_name"] === connection) {
            connection = conn;
        }
    })
    let modalHtml = `
    <div class="modal" id="connection-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">${connection["connection_name"]}</h2>
                <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
            </div>
            <div class="modal-body connection-modal">
                <img src="${connection["connection_user_image"]}" alt="Connection Image" class="connection-modal-userimage">
                <p class="connection-modal-username">${connection["connection_user_name"]}</p>
                <div class="connection-modal-connected-since"><p class="connection-modal-connected-since-title">Connected since:</p><p class="connection-modal-connected-since-date">${formatFullDate(connection["connected_at"])}</p></div>
                <div class="connection-modal-buttons">
                    <button class="connection-modal-button button-primary-filled" onclick="window.open('${connection["connection_user_url"]}', '_blank')">Open Profile Page</button>
                    <button class="connection-modal-button button-primary-outline" onclick="unlinkConnection('${connection["connection_name"]}')">Unlink</button>
                </div>
            </div>
        </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  const modal = document.getElementById("connection-modal");
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
}

function unlinkConnection(connection) {
    const connectionNameLower = connection.toLowerCase();
    window.location.href = `/connections/${connectionNameLower}_unlink`;
}