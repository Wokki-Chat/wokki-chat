function init_portal() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('developer/portal.js')) return;

    top_bar_profile = document.querySelector(".top-bar-profile");
    if (top_bar_profile) {
        top_bar_profile.addEventListener("click", () => {
            const dropdown = document.querySelector(".top-bar-profile-dropdown");
            if (dropdown) {
                dropdown.classList.toggle("active");
            }
        });
    }

    const sidebar_items = document.querySelectorAll('.sidebar-item');
    const currentPage = document.getElementById('page')?.getAttribute('id');

	if (currentPage) {
		sidebar_items.forEach(el => el.classList.remove('active'));
		sidebar_items.forEach(el => {
			if (el.getAttribute('href') === `${currentPage}`) {
				el.classList.add('active');
			}
		});
	}
}