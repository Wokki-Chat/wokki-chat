import { Sanitizer } from "./modules/global/sanitization.js";
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
}
if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initHome();
	});
}
initHome();