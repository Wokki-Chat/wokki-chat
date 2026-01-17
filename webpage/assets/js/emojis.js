window.emojis = {
	map: {},
	regex: null,

	async load(path = '/assets/json/emojis.json') {
		const res = await fetch(path);
		const data = await res.json();

		for (const entry of data) {
			if (!entry.emoji || !Array.isArray(entry.shortcodes)) continue;

			for (const shortcode of entry.shortcodes) {
				const code = shortcode.startsWith(':') && shortcode.endsWith(':')
					? shortcode
					: `:${shortcode.replace(/\s+/g, '_')}:`;
				this.map[code] = entry.emoji;
			}
		}

		this.regex = new RegExp(
			`(?<!\\\\)(${Object.keys(this.map)
				.map(k => k.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '\\$1'))
				.join('|')})`,
			'g'
		);
	},

	async replaceText(text) {
		if (!this.regex) return text;

		text = text.replace(this.regex, match => this.map[match] || match);
		text = text.replace(/\\:/g, ':');

		return text;
	},

	async replaceAllTextNodes(root = document.body) {
		const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
		let node;

		while ((node = walker.nextNode())) {
			if (this.isInCodeBlock(node)) continue;

			node.textContent = await this.replaceText(node.textContent);
		}
	},

	isInCodeBlock(node) {
		let parent = node.parentNode;
		while (parent) {
			if (
				parent.nodeName === 'CODE' ||
				parent.nodeName === 'PRE' ||
				parent.nodeName === 'KBD' ||
				parent.classList?.contains('code-block')
			) {
				return true;
			}
			parent = parent.parentNode;
		}
		return false;
	},

	async replaceAll() {
		await this.replaceAllTextNodes();
	},

	async replaceEl(element) {
		if (!element) return;
		await this.replaceAllTextNodes(element);
	}
};
