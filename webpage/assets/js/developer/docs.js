function initDocs() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('developer/docs.js')) return;

    const sidebar_items = document.querySelectorAll('.sidebar-item');
    const currentPage = document.getElementById('page').getAttribute("value");

	if (currentPage) {
		sidebar_items.forEach(el => el.classList.remove('active'));
		sidebar_items.forEach(el => {
			if (el.getAttribute('href') === `${currentPage}`) {
				el.classList.add('active');
			}
		});
	}
}

initDocs();