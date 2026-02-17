function initDocs() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('developer/docs.js')) return;
}
if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initDocs();
    });
}
initDocs();