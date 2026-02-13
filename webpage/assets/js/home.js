import { Sanitizer } from "./modules/global/sanitization.js";
import { NotificationsManager } from "./modules/global/notifications.js";
function initHome() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('home.js')) return;

    const serverBar = document.querySelector(".server-bar");
    if (serverBar && window.matchMedia("(min-width: 768px)").matches) {
        serverBar.classList.remove("hidden");
    } else {
		serverBar.classList.add("hidden");
	}

    const access_token = document.getElementById("access-token").getAttribute("value");
    const users = JSON.parse(document.getElementById("users-list").getAttribute("value"));
    const usersList = users.map(user => ({ id: user.id, username: user.username, profile_picture: user.profile_picture, status: user.status, premium: user.premium, is_in_server: user.is_in_server }));

    socket.on("user_updated", (user) => {
        if (!usersList.some(u => String(u.id) === user.id)) return;
        renderUser(user);
    });
		
	const notificationsManager = new NotificationsManager({ access_token: access_token, socket: socket });
	notificationsManager.listen();

	const serverbar_servers = document.querySelectorAll('#server-bar-item-server');
	serverbar_servers.forEach(el => el.classList.remove('active'));

    const sanitizer = new Sanitizer();
    
    document.getElementById("server-bar-item-home").classList.add("active");
    function renderUser(user) {
        const userListContainer = document.querySelector(".dm-users");

        if (!usersList.some(u => String(u.id) === user.id)) return;

        userListContainer.querySelectorAll(`[data-user-id="${user.id}"]`).forEach(el => el.remove());

        const userEl = document.createElement("div");
        userEl.classList.add("info-profile");
        userEl.setAttribute("data-user-id", user.id);
        const encodedUsername = encodeURIComponent(user.username);
        userEl.onclick = () => {
            window.location.href = `/dm/@${encodedUsername}`;
        };

        userEl.innerHTML = `
            <div class="self-info-profile-status" data-user-id="${user.id}">
                <img class="self-info-profile-picture" src="${user.profile_picture}" />
                <div class="self-info-status-circle-outer">
                    <div class="self-info-status-circle-inner ${user.status}"></div>
                </div>
            </div>
            <div class="self-info-status-username">
                <div class="self-info-profile-username-container"><p class="self-info-username">${sanitizer.sanitize(user.username)}</p>
                    ${user.premium ? '<div class="premium-tag"><span class="material-symbols-rounded">star</span>PREMIUM</div>' : ''}
                    </div>
                <p class="self-info-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
            </div>
        `;

        userListContainer.appendChild(userEl);
    }

    const daytime = document.getElementById("main-content-daytime");
    if (daytime) {
        const hour = new Date().getHours();
        const greeting =
            hour < 12 ? "Good morning" :
            hour < 17 ? "Good afternoon" :
            "Good evening";
        daytime.textContent = greeting;
    }

    const addFriendBtn = document.getElementById("add-friend-btn");
    addFriendBtn.addEventListener("click", openAddFriendModal);

    socket.emit("pending_friend_requests", {
        access_token
    });
    socket.emit("outgoing_friend_requests", {
        access_token
    });

    socket.on("pending_friend_requests", (requests) => {
        if (!requests) return;
        if (!requests.friend_requests) return;
        if (requests.friend_requests.length === 0) return;

        const pendingFriendRequests = document.getElementById("pending-friend-requests");
        pendingFriendRequests.innerHTML = "";

        requests.friend_requests.forEach(request => {
            const requestEl = `
                <div class="request-item" data-id="${request.user_id}">
                    <img src="${request.profile_picture}" class="request-pfp">
                    <div class="request-item-options-username">
                        <span class="request-username">${sanitizer.sanitize(request.username)}</span>
                        <div class="request-item-options">
                            <span class="request-accept">Accept</span>
                            <span class="request-decline">Decline</span>
                        </div>
                    </div>
                </div>
            `;
            pendingFriendRequests.innerHTML += requestEl;
        });

        const requestAcceptBtns = document.querySelectorAll(".request-accept");
        const requestDeclineBtns = document.querySelectorAll(".request-decline");
        requestAcceptBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                socket.emit("accept_friend_request", {
                    requested_friend_id: btn.parentElement.parentElement.parentElement.dataset.id,
                    access_token
                });
                window.location.reload();
            });
        });
        requestDeclineBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                socket.emit("deny_friend_request", {
                    friend_id: btn.parentElement.parentElement.parentElement.dataset.id,
                    access_token
                });
                window.location.reload();
            });
        });
    });

    socket.on("outgoing_friend_requests", (requests) => {
        if (!requests) return;
        if (!requests.outgoing_friend_requests) return;
        if (requests.outgoing_friend_requests.length === 0) return;

        const outgoingFriendRequests = document.getElementById("outgoing-friend-requests");
        outgoingFriendRequests.innerHTML = "";

        requests.outgoing_friend_requests.forEach(request => {
            const requestEl = `
                <div class="request-item" data-id="${request.user_id}">
                    <img src="${request.profile_picture}" class="request-pfp">
                    <div class="request-item-options-username">
                        <span class="request-username">${sanitizer.sanitize(request.username)}</span>
                        <span class="request-cancel">Cancel</span>
                    </div>
                </div>
            `;
            outgoingFriendRequests.innerHTML += requestEl;
        });

        const requestCancelBtns = document.querySelectorAll(".request-cancel");
        requestCancelBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                socket.emit("cancel_outgoing_friend_request", {
                    friend_id: btn.parentElement.parentElement.dataset.id,
                    access_token
                });
                window.location.reload();
            });
        });
    });

    socket.on("friend_request_received", (request) => {
        const pendingFriendRequests = document.getElementById("pending-friend-requests");

        const emptyMsg = pendingFriendRequests.querySelector("p");
        if (emptyMsg && emptyMsg.textContent.includes("No pending friend requests")) {
            pendingFriendRequests.innerHTML = "";
        }

        const requestEl = `
            <div class="request-item" data-id="${request.user_id}">
                <img src="${request.profile_picture}" class="request-pfp">
                <div class="request-item-options-username">
                    <span class="request-username">${sanitizer.sanitize(request.username)}</span>
                    <div class="request-item-options">
                        <span class="request-accept">Accept</span>
                        <span class="request-decline">Decline</span>
                    </div>
                </div>
            </div>
        `;
        pendingFriendRequests.innerHTML += requestEl;

        const requestAcceptBtns = document.querySelectorAll(".request-accept");
        const requestDeclineBtns = document.querySelectorAll(".request-decline");
        requestAcceptBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                socket.emit("accept_friend_request", {
                    requested_friend_id: request.user_id,
                    access_token
                });
                window.location.reload();
            });
        });
        requestDeclineBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                socket.emit("deny_friend_request", {
                    friend_id: request.user_id,
                    access_token
                });
                window.location.reload();
            });
        });
    });

    function openAddFriendModal() {
        let modalHtml = `
            <div class="modal" id="add-friend-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Add Friend</h2>
                        <span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
                    </div>
                    <div class="modal-body">
                        <form id="add-friend-form" class="create-channel-form">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" class="input-text-dark-bg w270" required>
                            <button type="submit" class="button-primary-filled">Send Friend Request</button>
                        </form>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHtml);

        const modal = document.getElementById("add-friend-modal");
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

        const addFriendForm = document.getElementById("add-friend-form");
        addFriendForm.addEventListener("submit", (event) => {
            event.preventDefault();
            const username = document.getElementById("username").value;
            sendFriendRequest(username, modal);
        });
    }

    function sendFriendRequest(username, modal) {
        socket.emit("send_friend_request", {
            access_token,
            friend_username: username
        });

        socket.once("friend_request_sent", (response) => {
            if (response.success) {
            modal.remove();
            window.location.reload();
            } else {
                if (response.msg == "User not found") {
                    Toastify({
                        text: "User doesn't exist",
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
                } else if (response.msg == "Cannot send friend request to yourself") {
                    Toastify({
                        text: "You can't send a friend request to yourself",
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
                } else if (response.msg == "You have already sent a friend request to this person") {
                    Toastify({
                        text: "Friend request already sent",
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
                } else if (response.msg == "Already friends") {
                    Toastify({
                        text: "You are already friends with this person",
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
                } else {
                    Toastify({
                        text: "Something went wrong",
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
                }
            }
        });
    }
}
if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initHome();
	});
}
initHome();