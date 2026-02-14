import { Sanitizer } from "./modules/global/sanitization.js";
import { NotificationsManager } from "./modules/global/notifications.js";
import Dev from "./modules/global/dev.js";

function initKudos() {
    const el = document.querySelector('wchat-allowed-scripts');
    const scripts = el.getAttribute('value').split(';');
    if (!scripts.includes('kudos.js')) return;

    const serverBar = document.querySelector(".server-bar");
    if (serverBar && window.matchMedia("(min-width: 768px)").matches) {
        serverBar.classList.remove("hidden");
    } else {
        serverBar.classList.add("hidden");
    }

    const access_token = document.getElementById("access-token").getAttribute("value");
    const users = JSON.parse(document.getElementById("users-list").getAttribute("value"));
    const usersList = users.map(user => ({ id: user.id, username: user.username, profile_picture: user.profile_picture, status: user.status, premium: user.premium, is_in_server: user.is_in_server }));
	const kudo_items = JSON.parse(document.getElementById("kudo-items").getAttribute("value"));
	const kudos = document.getElementById("kudos").getAttribute("value");

	const dev = new Dev();
	dev.init();

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

    document.querySelectorAll(".kudos-item").forEach(item => {
		item.addEventListener("click", () => {
			const kudosItemId = item.getAttribute("data-id");
			const kudoItem = kudo_items.find(item => item["id"].toString() === kudosItemId);
			let modalHtml = `
				<div class="modal" id="kudo-item-modal">
					<div class="modal-content">
						<div class="modal-header">
							<h2 class="modal-title">${kudoItem["name"]}</h2>
							<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
						</div>
						<div class="modal-body kudo-item-modal">
							<div class="kudo-item-modal-image-container"><img draggable="false" src="${kudoItem["image"]}" alt="Kudo Shop Item Image" class="kudo-item-modal-image"></div>
							<p class="kudo-item-modal-name">${kudoItem["name"]}</p>
							<p class="kudo-item-modal-description">${kudoItem["description"]}</p>
							<div class="kudo-item-modal-price-container">
								<span class="material-symbols-rounded">poker_chip</span>
								<p class="kudo-item-modal-price">${kudoItem["price"] > 9999 ? kudoItem["price"].toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") : kudoItem["price"]}</p>
							</div>
							<div class="kudo-item-modal-buttons">
								<button class="kudo-item-modal-button button-primary-filled" id="buy-kudo-item" ${Number(kudos) >= Number(kudoItem["price"]) ? "" : "disabled"} >Buy for <span class="material-symbols-rounded">poker_chip</span>${Number(kudoItem["price"]).toLocaleString(undefined, { minimumFractionDigits: 0 })}</button>
							</div>
						</div>
					</div>
				</div>
			`;

			document.body.insertAdjacentHTML("beforeend", modalHtml);

			const modal = document.getElementById("kudo-item-modal");
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
			const buyKudoBtn = document.getElementById("buy-kudo-item");
			buyKudoBtn.addEventListener("click", () => {
				buyKudo(kudosItemId);
			});
		});
    });

	function buyKudo(kudoId) {
		let formData = new FormData();
		formData.append("kudo_id", kudoId);

		fetch(`/app/buy_item`, {
			method: "POST",
			headers: {
				Authorization: `Bearer ${access_token}`,
			},
			body: formData,
		})
		.then((response) => response.json())
		.then((data) => {
			window.location.reload();
		})
		.catch((error) => {
			console.error(error);
		});
	}
}
if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initKudos();
    });
}
initKudos();