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
    document.getElementById("bugs").classList.remove("active");
    if (is_developer === 'true') {
        document.getElementById("api-requests").classList.remove("active");
    }
    document.getElementById(new_page).classList.add("active");

    document.getElementById("ideas-voting").style.display = "none";
    document.getElementById("ideas-planned").style.display = "none";
    document.getElementById("ideas-implemented").style.display = "none";
    document.getElementById("ideas-bugs").style.display = "none";
    if (is_developer === 'true') {
        document.getElementById("ideas-api-requests").style.display = "none";
    }
    document.getElementById("ideas-" + new_page).style.display = "block";
}

window.addEventListener("load", () => change_page("voting"));

document.querySelectorAll(".tab").forEach(tab => tab.addEventListener("click", () => change_page(tab.id)));

const addIdeaBtn = document.getElementById("add-idea-btn");
if (addIdeaBtn) addIdeaBtn.addEventListener("click", showTypeSelectionModal);

function showTypeSelectionModal() {
    let modalHtml = `
    <div class="modal" id="type-selection-modal">
        <div class="modal-content type-selection">
            <div class="modal-header">
                <h2 class="modal-title">What would you like to submit?</h2>
                <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
            </div>
            <div class="modal-body">
                <div class="type-selection-options">
                    <div class="type-option" data-type="idea">
                        <span class="material-symbols-rounded type-option-icon">lightbulb</span>
                        <h3>Feature Idea</h3>
                        <p>Suggest a new feature or improvement</p>
                    </div>
                    <div class="type-option" data-type="bug">
                        <span class="material-symbols-rounded type-option-icon">bug_report</span>
                        <h3>Bug Report</h3>
                        <p>Report an issue or problem</p>
                    </div>
                    <div class="type-option" data-type="api_request">
                        <span class="material-symbols-rounded type-option-icon">api</span>
                        <h3>API Request</h3>
                        <p>Request a new API endpoint (private)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHtml);

    const modal = document.getElementById("type-selection-modal");
    const modalCloseBtn = document.getElementById("close-modal-btn");
    modalCloseBtn.addEventListener("click", () => modal.remove());

    modal.querySelectorAll(".type-option").forEach(option => {
        option.addEventListener("click", () => {
            const type = option.dataset.type;
            modal.remove();
            showSubmissionForm(type);
        });
    });
}

function showSubmissionForm(type) {
    let formContent = '';
    let title = '';

    if (type === 'idea') {
        title = 'New Feature Idea';
        formContent = `
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
        `;
    } else if (type === 'bug') {
        title = 'Report a Bug';
        formContent = `
            <label for="title">Bug Title:</label>
            <input type="text" id="title" name="title" class="input-text-dark-bg w270" required maxlength="50" placeholder="Brief summary of the bug">
            
            <label for="bug-issue" style="margin-top: 15px;">What is the issue?</label>
            <textarea id="bug-issue" name="bug-issue" class="input-text-dark-bg w270" required placeholder="Describe the problem you're experiencing" maxlength="500"></textarea>
            
            <label for="bug-expected" style="margin-top: 15px;">What did you expect would happen?</label>
            <textarea id="bug-expected" name="bug-expected" class="input-text-dark-bg w270" required placeholder="Describe the expected behavior" maxlength="500"></textarea>
            
            <label for="bug-actual" style="margin-top: 15px;">What actually happened?</label>
            <textarea id="bug-actual" name="bug-actual" class="input-text-dark-bg w270" required placeholder="Describe what actually happened" maxlength="500"></textarea>
            
            <label for="bug-extra" style="margin-top: 15px;">Any extra information (optional):</label>
            <textarea id="bug-extra" name="bug-extra" class="input-text-dark-bg w270" placeholder="Browser, OS, steps to reproduce, etc." maxlength="500"></textarea>
            
            <label for="idea-image" style="margin-top: 15px;">Screenshot (optional):</label>
            <span class="upload-description">Upload a screenshot to help illustrate the issue.</span>
            <label for="idea-image" id="icon-preview" class="icon-preview">
                <span class="material-symbols-rounded upload-icon">add</span>
            </label>
            <input type="file" id="idea-image" name="idea-image" accept="image/jpeg,image/png,image/gif" class="input-file-dark-bg file-input" style="display: none;">
        `;
    } else if (type === 'api_request') {
        title = 'Request API Endpoint';
        formContent = `
            <div class="private-notice">
                <span class="material-symbols-rounded">lock</span>
                <p>This request will be private - only you and developers can see it.</p>
            </div>
            
            <label for="title">Request Title:</label>
            <input type="text" id="title" name="title" class="input-text-dark-bg w270" required maxlength="50" placeholder="What API endpoint do you need?">
            
            <label for="api-endpoint" style="margin-top: 15px;">Proposed Endpoint Path:</label>
            <input type="text" id="api-endpoint" name="api-endpoint" class="input-text-dark-bg w270" required placeholder="e.g., /api/v1/users/profile" maxlength="200">
            
            <label for="api-purpose" style="margin-top: 15px;">What is the purpose?</label>
            <textarea id="api-purpose" name="api-purpose" class="input-text-dark-bg w270" required placeholder="Explain why you need this endpoint" maxlength="500"></textarea>
            
            <label for="description" style="margin-top: 15px;">Expected Behavior:</label>
            <textarea id="description" name="description" class="input-text-dark-bg w270" required placeholder="Describe what the endpoint should do, what data it should return, etc." maxlength="1000"></textarea>
        `;
    }

    let modalHtml = `
    <div class="modal" id="new-idea-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">${title}</h2>
                <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
            </div>
            <div class="modal-body">
                <form id="new-idea-form" class="new-idea-form" data-type="${type}">
                    ${formContent}
                    <button type="submit" class="button-primary-filled" style="margin-top: 15px;">Submit</button>
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

    if (ideaImageInput && iconPreview) {
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
                        iconPreview.innerHTML = `<img src="${img.src}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    }
                };
                const reader = new FileReader();
                reader.onload = e => { img.src = e.target.result; };
                reader.readAsDataURL(file);
            } else {
                iconPreview.innerHTML = `<span class="material-symbols-rounded upload-icon">add</span>`;
            }
        });
    }

    const form = document.getElementById("new-idea-form");
    form.addEventListener("submit", (event) => {
        event.preventDefault();
        
        const formData = new FormData();
        formData.append("type", type);
        formData.append("title", document.getElementById("title").value);

        if (type === 'idea') {
            formData.append("description", document.getElementById("description").value);
            if (ideaImageInput.files[0]) formData.append("image", ideaImageInput.files[0]);
        } else if (type === 'bug') {
            const bugIssue = document.getElementById("bug-issue").value;
            const bugExpected = document.getElementById("bug-expected").value;
            const bugActual = document.getElementById("bug-actual").value;
            const bugExtra = document.getElementById("bug-extra").value;
            
            formData.append("bug_issue", bugIssue);
            formData.append("bug_expected", bugExpected);
            formData.append("bug_actual", bugActual);
            formData.append("bug_extra", bugExtra);
            
            formData.append("description", `Issue: ${bugIssue}\nExpected: ${bugExpected}\nActual: ${bugActual}\nExtra: ${bugExtra}`);
            
            if (ideaImageInput.files[0]) formData.append("image", ideaImageInput.files[0]);
        } else if (type === 'api_request') {
            formData.append("api_endpoint", document.getElementById("api-endpoint").value);
            formData.append("api_purpose", document.getElementById("api-purpose").value);
            formData.append("description", document.getElementById("description").value);
        }

        fetch("/app/add_idea", {
            method: "POST",
            headers: { Authorization: `Bearer ${access_token}` },
            body: formData,
        })
        .then((response) => response.json())
        .then(() => {
            jspt.makeToast({ message: "Submitted successfully!", type: "default", duration: 5000, close_on_click: true });
            setTimeout(() => window.location.reload(), 1000);
        })
        .catch(() => {
            jspt.makeToast({ message: "Failed to submit", type: "default-error", duration: 5000, close_on_click: true });
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
    const lines = comment.body.split('\n');

    let github_username = 'Unknown';
    let github_avatar = 'https://via.placeholder.com/40';

    if (lines.length > 1) {
        const secondLine = lines[1].trim();
        const usernameMatch = secondLine.match(/!\[(.*?)\]\(/);
        const avatarMatch = secondLine.match(/\((.*?)\)/);
        if (usernameMatch) github_username = usernameMatch[1];
        if (avatarMatch) github_avatar = avatarMatch[1];
    }

    let displayBody = lines
        .slice(2)
        .filter(line => line.trim() !== '---')
        .join('\n')
        .trim();

    if (!displayBody) displayBody = '(No comment text)';

    if (!isUserResponse) {
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
                    <img draggable="false" class="idea-comment-avatar" src="${github_avatar}" alt="${github_username}">
                    <div class="idea-comment-meta">
                        <span class="idea-comment-username">${github_username}</span>
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

    const typeLabel = idea.type === 'bug' ? '🐛 Bug Report' : (idea.type === 'api_request' ? '🔌 API Request' : '💡 Feature Idea');
    const privateLabel = idea.is_private ? '<span class="private-badge"><span class="material-symbols-rounded">lock</span>Private</span>' : '';

    let modalHtml = `
    <div class="modal" id="new-idea-modal">
        <div class="modal-content" style="overflow-y: scroll; width: auto;">
            <div class="modal-header">
                <div class="modal-title-container">
                    <h2 class="modal-title">${idea.title}</h2>
                    <div class="modal-badges">
                        <span class="type-badge">${typeLabel}</span>
                        ${privateLabel}
                    </div>
                </div>
                <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
            </div>
            <div class="modal-body">
                ${ idea.image_path ? `<img draggable="false" class="idea-image" src="${idea.image_path}" style="border-radius: 10px; height: 300px;">` : '' }
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
                    <p class="idea-type" style="margin: 0;">Type: ${idea.type}</p>
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