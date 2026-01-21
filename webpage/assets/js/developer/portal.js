function init_portal() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('portal.js')) return;

    top_bar_profile = document.querySelector(".top-bar-profile");
    if (top_bar_profile) {
        top_bar_profile.addEventListener("click", () => {
            const dropdown = document.querySelector(".top-bar-profile-dropdown");
            if (dropdown) {
                dropdown.classList.toggle("active");
            }
        });
    }
}