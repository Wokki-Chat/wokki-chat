import * as jspt from "https://cdn.wokki20.nl/content/jspt-v2.1.0/jspt.module.js";
import { Sanitizer } from "./modules/global/sanitization.js";

const user_id = document.getElementById("user_id").getAttribute("value");
const access_token = document.getElementById("access_token").getAttribute("value");
const is_developer = document.getElementById("is_developer").getAttribute("value");
const ideas = JSON.parse(document.getElementById("ideas").getAttribute("value"));
const is_staff = document.getElementById("is_staff").getAttribute("value");

const sanitizer = new Sanitizer(user_id, null, null);

const top_bar_profile = document.querySelector(".top-bar-profile");
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
if (addIdeaBtn) addIdeaBtn.addEventListener("click", newIdeaModal);

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
                    <span class="upload-description">Image is optional but helps the idea stand out. Image dimensions must be at least 50x50px.</span>
                    <label for="idea-image" id="icon-preview" class="icon-preview">
                        <span class="material-symbols-rounded upload-icon">add</span>
                    </label>
                    <input type="file" id="idea-image" name="idea-image" accept="image/jpeg,image/png,image/gif" class="input-file-dark-bg file-input" style="display: none;">
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
    const modalCloseBtn = document.getElementById("close-modal-btn");
    modalCloseBtn.addEventListener("click", () => modal.remove());

    const ideaImageInput = document.getElementById("idea-image");
    const iconPreview = document.getElementById("icon-preview");

    ideaImageInput.addEventListener("change", () => {
        const file = ideaImageInput.files[0];
        if (file && file.type.startsWith("image/")) {
            const img = new Image();
            img.onload = () => {
                if (img.width < 50 || img.height < 50) {
                    jspt.makeToast({ message: "Image dimensions must be at least 50x50px.", type: "default-error", duration: 5000, close_on_click: true });
                    ideaImageInput.value = "";
                    iconPreview.innerHTML = `<span class="material-symbols-rounded upload-icon">add</span>`;
                } else {
                    iconPreview.innerHTML = `<img src="${img.src}" alt="Idea Icon" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
            };
            const reader = new FileReader();
            reader.onload = e => { img.src = e.target.result; };
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
        formData.append("description", description);

        if (ideaImageFile) formData.append("image", ideaImageFile);

        fetch("/app/add_idea", {
            method: "POST",
            headers: { Authorization: `Bearer ${access_token}` },
            body: formData,
        })
        .then((response) => response.json())
        .then(() => {
            jspt.makeToast({ message: "Idea created!", type: "default", duration: 5000, close_on_click: true });
        })
        .catch(() => {
            jspt.makeToast({ message: "Failed to create idea", type: "default-error", duration: 5000, close_on_click: true });
        })
        .finally(() => modal.remove());
    });

}

function formatDate(created_at) {
    const now = new Date();
    const date = new Date(created_at);
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays > 7) {
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    if (diffDays === 1) {
        return `Yesterday at ${date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`;
    }
    if (diffDays >= 2) {
        return `${diffDays} days ago at ${date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`;
    }
    return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function renderAssignees(assignees) {
    if (!assignees || assignees.length === 0) return '';
    const visible = assignees.slice(0, 5);
    const overflow = assignees.length > 5 ? `<span class="idea-assignees-overflow">+${assignees.length - 5}</span>` : '';
    return `
        <div class="idea-assignees-modal">
            <p class="idea-assignees-label">Assigned to</p>
            <div class="idea-assignees-list">
                ${visible.map(a => `
                    <div class="idea-assignee">
                        <img draggable="false" class="idea-assignee-avatar" src="${a.avatar_url}" alt="${a.username}">
                        <span class="idea-assignee-name">${a.username}</span>
                    </div>
                `).join('')}
                ${overflow}
            </div>
        </div>
    `;
}

function renderComment(comment) {
    const isUserResponse = comment.body.startsWith("### User Response");
    const isDeveloperReply = !isUserResponse;

    let displayBody = comment.body;

    if (isDeveloperReply) {
        const userMatch = comment.body.match(/from \*\*(.+?)\*\*/);
        const imgMatch = comment.body.match(/!\[.*?\]\((.*?)\)/);

        const github_username = userMatch ? userMatch[1] : comment.github_username || 'Developer';
        const github_avatar = imgMatch ? imgMatch[1] : comment.github_avatar || 'https://via.placeholder.com/40';

        const bodyLines = comment.body.split('\n');
        displayBody = bodyLines.slice(3).join('\n').replace(/^---\n\n/, '');

        return `
            <div class="idea-comment idea-comment-developer">
                <div class="idea-comment-header">
                    <img draggable="false" class="idea-comment-avatar" src="${github_avatar}" alt="${github_username}">
                    <div class="idea-comment-meta">
                        <span class="idea-comment-username">${github_username}</span>
                        <span class="idea-comment-dev-badge">Developer</span>
                        <span class="idea-comment-date">${formatDate(comment.created_at)}</span>
                    </div>
                </div>
                <p class="idea-comment-body">${escapeHtml(displayBody)}</p>
            </div>
        `;
    } else {
        return `
            <div class="idea-comment">
                <div class="idea-comment-header">
                    <img draggable="false" class="idea-comment-avatar" src="${comment.github_avatar}" alt="${comment.github_username}">
                    <div class="idea-comment-meta">
                        <span class="idea-comment-username">${comment.github_username}</span>
                        <span class="idea-comment-date">${formatDate(comment.created_at)}</span>
                    </div>
                </div>
                <p class="idea-comment-body">${escapeHtml(displayBody)}</p>
            </div>
        `;
    }
}

function escapeHtml(str) {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

const ideaItems = document.querySelectorAll(".idea");
ideaItems.forEach(item => item.addEventListener("click", async () => await showIdea(item.dataset.id)));

const urlParams = new URLSearchParams(window.location.search);
const ideaId = urlParams.get("idea");
if (ideaId) showIdea(ideaId);

async function showIdea(id) {
    const idea = ideas.find(idea => idea.id === id);
    if (!idea) return;

    window.history.replaceState({}, '', `?idea=${id}`);

    const canReply = is_developer === 'true' || user_id === idea.user_id;

    const replyFormHtml = canReply ? `
    <div class="idea-reply-form">
        <textarea id="reply-text" class="input-text-dark-bg" placeholder="${user_id === idea.user_id && is_developer !== 'true' ? 'Write a response...' : 'Write a developer response...'}" maxlength="2000"></textarea>
        <button class="button-primary-filled" id="reply-btn">
            <span class="material-symbols-rounded">send</span>Reply
        </button>
    </div>` : '';

    const initialAssignees = idea.github_assignees || [];
    const assigneesHtml = initialAssignees.length > 0 ? renderAssignees(initialAssignees) : '<div id="idea-assignees-section"></div>';

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
                <div class="idea-description">${await sanitizer.sanitizeMsg(idea.description)}</div>
                ${is_developer === 'true' ? `
                <div class="developer-info" style="display: flex; flex-direction: column; align-items: flex-start; margin-top: 10px;">
                    <p class="idea-id" style="margin: 0;">ID: ${idea.id}</p>
                    <p class="idea-channel-id" style="margin: 0;">Status: ${idea.status}</p>
                </div>` : ''}
                <div id="idea-assignees-section">${assigneesHtml}</div>
                <div class="idea-actions">
                    <button class="idea-action" id="upvote-button" data-id="${idea.id}"><span class="material-symbols-rounded">arrow_shape_up</span>${idea.votes}</button>
                    <div class="idea-actions-owner">
                        <button class="idea-action" onclick="share('${idea.id}')"><span class="material-symbols-rounded">share</span></button>
                        ${is_developer === 'true' ? `<button class="idea-action" data-id="${idea.id}" id="move-idea-back-button"><span class="material-symbols-rounded">move_down</span></button>` : ''}
                        ${is_developer === 'true' ? `<button class="idea-action" data-id="${idea.id}" id="move-idea-button"><span class="material-symbols-rounded">move_up</span></button>` : ''}
                    </div>
                </div>
                <div class="idea-comments-section">
                    <div class="idea-comments-header">
                        <span class="material-symbols-rounded">forum</span>
                        <h3 class="idea-comments-title">Responses</h3>
                    </div>
                    <div id="idea-comments-list">
                        <div class="idea-comments-loading">
                            <span class="material-symbols-rounded spinning">progress_activity</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHtml);

    const modal = document.getElementById("new-idea-modal");
    const modalContent = modal.querySelector(".modal-content");
    const ideaActions = modalContent.querySelector(".idea-actions");

    const upvoteButton = ideaActions.querySelector("#upvote-button");
    upvoteButton.addEventListener("click", () => upvoteIdea(upvoteButton.dataset.id));

    const moveIdeaButton = ideaActions.querySelector("#move-idea-button");
    if (moveIdeaButton) moveIdeaButton.addEventListener("click", () => moveIdea(moveIdeaButton.dataset.id));

    const moveIdeaBackButton = ideaActions.querySelector("#move-idea-back-button");
    if (moveIdeaBackButton) moveIdeaBackButton.addEventListener("click", () => moveIdeaBack(moveIdeaBackButton.dataset.id));

    if (idea.voted === 1) {
        upvoteButton.innerHTML = `<span class="material-symbols-rounded">arrow_shape_up</span>${idea.votes}`;
        upvoteButton.classList.add("done");
    }

    document.querySelector(".idea-comments-section").insertAdjacentHTML("beforeend", replyFormHtml);

    const replyBtn = modal.querySelector("#reply-btn");
    if (replyBtn) {
        replyBtn.addEventListener("click", () => {
            const replyText = modal.querySelector("#reply-text").value.trim();
            if (!replyText) return;
            replyIdea(id, replyText, modal);
        });
    }

    const modalCloseBtn = document.getElementById("close-modal-btn");
    modalCloseBtn.addEventListener("click", () => {
        window.history.replaceState({}, '', location.pathname);
        modal.remove();
    });

    fetchIdeaGitHubData(id, modal);
}

async function fetchIdeaGitHubData(id, modal) {
    const commentsList = modal.querySelector("#idea-comments-list");
    const assigneesSection = modal.querySelector("#idea-assignees-section");

    try {
        const response = await fetch(`/app/get_idea?idea_id=${id}`);
        const data = await response.json();

        if (data.assignees && data.assignees.length > 0) {
            assigneesSection.innerHTML = renderAssignees(data.assignees);
            updateCardAssignees(id, data.assignees);
        }

        if (!data.comments || data.comments.length === 0) {
            commentsList.innerHTML = `<p class="idea-no-comments">No responses yet.</p>`;
            return;
        }

        commentsList.innerHTML = data.comments.map(renderComment).join('');
    } catch {
        commentsList.innerHTML = `<p class="idea-no-comments">Could not load responses.</p>`;
    }
}

function updateCardAssignees(id, assignees) {
    const card = document.querySelector(`.idea[data-id="${id}"]`);
    if (!card) return;

    let assigneesEl = card.querySelector(".idea-assignees");
    if (!assigneesEl) {
        assigneesEl = document.createElement("div");
        assigneesEl.className = "idea-assignees";
        card.appendChild(assigneesEl);
    }

    const visible = assignees.slice(0, 5);
    const overflow = assignees.length > 5 ? `<span class="idea-assignees-overflow">+${assignees.length - 5}</span>` : '';
    assigneesEl.innerHTML = visible.map(a => `
        <img draggable="false" class="idea-assignee-avatar" src="${a.avatar_url}" title="${a.username}" alt="${a.username}">
    `).join('') + overflow;
}

function replyIdea(id, replyText, modal) {
    const replyBtn = modal.querySelector("#reply-btn");
    const replyTextArea = modal.querySelector("#reply-text");
    replyBtn.disabled = true;

    const formData = new FormData();
    formData.append("idea_id", id);
    formData.append("reply", replyText);

    fetch("/app/reply_idea", {
        method: "POST",
        headers: { Authorization: `Bearer ${access_token}` },
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            jspt.makeToast({ message: "Reply posted!", type: "default", duration: 3000, close_on_click: true });
            replyTextArea.value = "";
            fetchIdeaGitHubData(id, modal);
        } else {
            jspt.makeToast({ message: "Failed to post reply", type: "default-error", duration: 5000, close_on_click: true });
        }
    })
    .catch(() => {
        jspt.makeToast({ message: "Failed to post reply", type: "default-error", duration: 5000, close_on_click: true });
    })
    .finally(() => {
        replyBtn.disabled = false;
    });
}

function upvoteIdea(id) {
    const formData = new FormData();
    formData.append("idea_id", id);

    fetch(`/app/upvote_idea`, {
        method: "POST",
        headers: { Authorization: `Bearer ${access_token}` },
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        const button = document.querySelector(`#upvote-button[data-id="${id}"]`);
        if (!button) return;

        button.innerHTML = `<span class="material-symbols-rounded">arrow_shape_up</span>${data.votes}`;
        button.classList.toggle("done", data.voted === 1);

        const ideaVotesIcon = document.querySelector(`.idea-votes-icon[data-id="${id}"]`);
        if (ideaVotesIcon) ideaVotesIcon.classList.toggle("filled", data.voted === 1);

        const ideaVotesCount = document.querySelector(`.idea-votes-count[data-id="${id}"]`);
        if (ideaVotesCount) ideaVotesCount.textContent = data.votes;

        ideas.find(idea => idea.id === id).votes = data.votes;
        ideas.find(idea => idea.id === id).voted = data.voted;
    })
    .catch(error => console.error("Error upvoting idea:", error));
}

function moveIdea(id) {
    const formData = new FormData();
    formData.append("idea_id", id);

    fetch(`/app/move_idea`, {
        method: "POST",
        headers: { Authorization: `Bearer ${access_token}` },
        body: formData,
    })
    .then(r => r.json())
    .then(() => window.location.reload())
    .catch(() => {});
}

function moveIdeaBack(id) {
    const formData = new FormData();
    formData.append("idea_id", id);

    fetch(`/app/move_idea_back`, {
        method: "POST",
        headers: { Authorization: `Bearer ${access_token}` },
        body: formData,
    })
    .then(r => r.json())
    .then(() => window.location.reload())
    .catch(() => {});
}

function share(id) {
    navigator.clipboard.writeText(`/ideas?idea=${id}`);
    jspt.makeToast({ message: "Link copied to clipboard!", style: "default", duration: 3000, close_on_click: true });
}