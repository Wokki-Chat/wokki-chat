top_bar_profile = document.querySelector(".top-bar-profile");
if (top_bar_profile) {
    top_bar_profile.addEventListener("click", () => {
        const dropdown = document.querySelector(".top-bar-profile-dropdown");
        if (dropdown) {
            dropdown.classList.toggle("active");
        }
    });
}

let page = "voting";

function change_page(new_page) {
    page = new_page;
    document.getElementById("voting").classList.remove("active");
    document.getElementById("planned").classList.remove("active");
    document.getElementById("implemented").classList.remove("active");
    document.getElementById(new_page).classList.add("active");

    document.getElementById("ideas-voting").style.display = "none";
    document.getElementById("ideas-planned").style.display = "none";
    document.getElementById("ideas-implemented").style.display = "none";
    document.getElementById("ideas-" + new_page).style.display = "block";
}

window.addEventListener("load", () => change_page("voting"));

document.querySelectorAll(".tab").forEach(tab => tab.addEventListener("click", () => change_page(tab.id)));

const addIdeaBtn = document.getElementById("add-idea-btn");
addIdeaBtn.addEventListener("click", newIdeaModal);

function newIdeaModal() {
  let modalHtml = `
    <div class="modal" id="new-idea-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">New Idea</h2>
                <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
            </div>
            <div class="modal-body">
                <form id="new-idea-form" class="new-idea-form">
                    <label for="idea-image">Idea Image:</label>
                    <label for="idea-image" id="icon-preview" class="icon-preview" >
                        <span class="material-symbols-rounded upload-icon">add</span>
                    </label>
                    <input required type="file" id="idea-image" name="idea-image" accept="image/jpeg,image/png,image/gif" class="input-file-dark-bg file-input" style="display: none;">
                    <label for="title" style="margin-top: 15px;">Title:</label>
                    <input type="text" id="title" name="title" class="input-text-dark-bg w270" required maxlength="50" placeholder="Title (up to 50 characters)">
                    <label for="description" style="margin-top: 15px;">Description:</label>
                    <textarea id="description" name="description" class="input-text-dark-bg w270" style="height: 40%;" required placeholder="Description (up to 500 characters, markdown supported)" maxlength="500"></textarea>
                    <button type="submit" class="button-primary-filled">Submit</button>
                </form>
            </div>
        </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  const modal = document.getElementById("new-idea-modal");
  const modalContent = modal.querySelector(".modal-content");

  const modalCloseBtn = document.getElementById("close-modal-btn");
  modalCloseBtn.addEventListener("click", () => {
    modal.remove();
  });

  const ideaImageInput = document.getElementById("idea-image");
  const iconPreview = document.getElementById("icon-preview");

  ideaImageInput.addEventListener("change", () => {
    const file = ideaImageInput.files[0];
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
          ideaImageInput.value = "";
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

  const createChannelForm = document.getElementById("new-idea-form");
  createChannelForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const title = document.getElementById("title").value;
    const ideaImageFile = ideaImageInput.files[0];
    const description = document.getElementById("description").value;

    const formData = new FormData();
    formData.append("title", title);
    formData.append("image", ideaImageFile);
    formData.append("description", description);


    fetch("https://chat.wokki20.nl/app/add_idea", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${access_token}`,
    },
    body: formData,
    })
    .then((response) => response.json())
    .then((data) => {
    console.log("Server created:", data);
        Toastify({
            text: "Idea created!",
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
        window.location.reload();
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



function showIdea(id) {
    const idea = ideas.find(idea => idea.id === id);

    window.history.replaceState({}, '', `?idea=${id}`);

    let modalHtml = `
    <div class="modal" id="new-idea-modal">
        <div class="modal-content" style="overflow-y: scroll; width: auto;">
            <div class="modal-header">
                <h2 class="modal-title">${idea.title}</h2>
                <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
            </div>
            <div class="modal-body">
                <img draggable="false" class="idea-image" src="${idea.image_path}" style="border-radius: 10px; height: 300px;">
                <div class="idea-creator">
                    <img draggable="false" class="idea-creator-img" src="${idea.profile_picture}">
                    <p class="idea-creator-name">${idea.username}</p>
                    <p class="idea-creator-date">${formatDate(idea.created_at)}</p>
                </div>
                <div class="idea-description">${sanitizeMsg(idea.description)}</div>
                <div class="idea-actions">
                    <button class="idea-action" id="upvote-button" onclick="upvoteIdea('${idea.id}')" data-id="${idea.id}"><span class="material-symbols-rounded">arrow_shape_up</span>${idea.votes}</button>
                    <button class="idea-action" onclick="share('${idea.id}')"><span class="material-symbols-rounded">share</span></button>
                </div>
            </div>
        </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  const modal = document.getElementById("new-idea-modal");
  const modalContent = modal.querySelector(".modal-content");
  const ideaActions = modalContent.querySelector(".idea-actions");

//   if (idea.user_id === user_id) {
//     ideaActions.innerHTML += `
//         <div class="idea-actions-owner">
//             <button class="idea-action" onclick="editIdea('${idea.id}')"><span class="material-symbols-rounded">edit</span></button>
//             <button class="idea-action" onclick="deleteIdea('${idea.id}')"><span class="material-symbols-rounded">delete</span></button>
//         </div>
//     `;
//   }

  if (idea.voted === 1) {
    ideaActions.querySelector("#upvote-button").innerHTML = `
        <span class="material-symbols-rounded">arrow_shape_up</span>${idea.votes}
    `;
    ideaActions.querySelector("#upvote-button").classList.add("done");
  }

  const modalCloseBtn = document.getElementById("close-modal-btn");
  modalCloseBtn.addEventListener("click", () => {
    window.history.replaceState({}, '', location.pathname);
    modal.remove();
  });
}

function upvoteIdea(id) {   
    const formData = new FormData();
    formData.append("idea_id", id);

    fetch(`https://chat.wokki20.nl/app/upvote_idea`, {
        method: "POST",
        headers: {
            Authorization: `Bearer ${access_token}`,
        },
        body: formData,
    })
    .then((response) => response.json())
    .then((data) => {
        console.log("Server created:", data);

        const button = document.querySelector(`#upvote-button[data-id="${id}"]`);
        if (!button) {
            console.error("Upvote button not found for id:", id);
            return;
        }

        button.innerHTML = `
            <span class="material-symbols-rounded">arrow_shape_up</span>${data.votes}
        `;
        button.classList.toggle("done", data.voted === 1);

        const ideaVotesIcon = document.querySelector(`.idea-votes-icon[data-id="${id}"]`);
        if (ideaVotesIcon) {
            ideaVotesIcon.classList.toggle("filled", data.voted === 1);
        }

        const ideaVotesCount = document.querySelector(`.idea-votes-count[data-id="${id}"]`);
        if (ideaVotesCount) {
            ideaVotesCount.textContent = data.votes;
        }

        ideas.find(idea => idea.id === id).votes = data.votes;
        ideas.find(idea => idea.id === id).voted = data.voted;
    })
    .catch((error) => {
        console.error("Error creating server:", error);
    });
}

function share(id) {
    navigator.clipboard.writeText(`https://chat.wokki20.nl/ideas?idea=${id}`);
    Toastify({
        text: "Link copied to clipboard",
        duration: 3000,
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
}