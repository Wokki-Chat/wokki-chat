window.addEventListener("load", () => {


socket.on("user_updated", (user) => {
    if (!usersList.some(u => String(u.id) === user.id)) return;
    renderUser(user);
});


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
            <div class="self-info-profile-username-container"><p class="self-info-username">${sanitize(user.username)}</p>
                ${user.premium ? '<div class="premium-tag"><span class="material-symbols-rounded">star</span>PREMIUM</div>' : ''}
                </div>
            <p class="self-info-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
        </div>
    `;

    userListContainer.appendChild(userEl);
}

function sanitize(text) {
    const div = document.createElement("div");
    div.innerText = text;
    return div.innerHTML;
}

});