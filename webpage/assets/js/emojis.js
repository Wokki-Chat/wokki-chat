import { TextareaFormatter } from "./modules/global/sanitization.js";

const emojis = {
	map: {},
	regex: null,
	missingFound: false,
	iconCache: {},
	all: null,

	async load(path = '/assets/json/emojis.json') {
		const res = await fetch(path);
		const data = await res.json();

		this.all = data;

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

		await this.preloadIcons();
	},

	async preloadIcons() {
		if (!this.all) return;
		const promises = this.all.map(async e => {
			if (!e.emoji) return;

			if (e.emojiSvg !== true) {
				this.iconCache[e.emoji] = e.emoji;
				return;
			}

			const hex = Array.from(e.emoji).map(c => c.codePointAt(0).toString(16)).join('-');
			const url = `/assets/icons/emojis/${hex}.svg`;

			try {
				const res = await fetch(url, { method: 'HEAD' });
				if (!res.ok) throw new Error('SVG not found');
				const img = `<img src="${url}" class="emoji">`;
				this.iconCache[e.emoji] = img;
			} catch {
				this.iconCache[e.emoji] = e.emoji;
			}
		});
		await Promise.all(promises);
	},

	async emojiToImg(emoji) {
		if (this.iconCache[emoji]) return this.iconCache[emoji];
		return emoji;
	},

	async replaceText(text) {
		if (!this.regex) return text;

		const parts = [];
		let lastIndex = 0;
		text.replace(this.regex, (match, ...args) => {
			const offset = args[args.length - 2];
			parts.push(text.slice(lastIndex, offset));
			lastIndex = offset + match.length;
			parts.push(match);
		});
		parts.push(text.slice(lastIndex));
		for (let i = 0; i < parts.length; i++) {
			const part = parts[i];
			if (this.map[part]) {
				parts[i] = await this.emojiToImg(this.map[part]);
				continue;
			}
			let newPart = '';
			for (const char of part) {
				newPart += await this.emojiToImg(char);
			}
			parts[i] = newPart;
		}

		return parts.join('').replace(/\\:/g, ':');
	},

	isInCodeBlock(node) {
		let parent = node.parentNode;
		while (parent) {
			if (
				parent.nodeName === 'CODE' ||
				parent.nodeName === 'PRE' ||
				parent.nodeName === 'KBD' ||
				parent.classList?.contains('code-block')
			) return true;
			parent = parent.parentNode;
		}
		return false;
	},

	async replaceAllTextNodes(root = document.body) {
		this.missingFound = false;
		const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
		let node;
		const nodes = [];

		while ((node = walker.nextNode())) {
			if (this.isInCodeBlock(node)) continue;
			nodes.push(node);
		}

		for (const node of nodes) {
			const html = await this.replaceText(node.textContent);
			const span = document.createElement('span');
			span.innerHTML = html;
			node.replaceWith(...span.childNodes);
		}
	},

	async replaceAll() {
		await this.replaceAllTextNodes();
	},

	async replaceEl(element) {
		if (!element) return;
		await this.replaceAllTextNodes(element);
	},

	async picker(targetField = null, relativeTo = null, shortcode = false) {
		const textareaFormatter = new TextareaFormatter();
		if (!Array.isArray(this.all) || this.all.length === 0) {
			await this.load();
		}

		return new Promise((resolve) => {
			const dropdownId = 'emoji-picker-dropdown';
			let dropdown = document.getElementById(dropdownId);

			if (!dropdown) {
				dropdown = document.createElement('div');
				dropdown.id = dropdownId;
				dropdown.classList.add('emoji-picker-dropdown');

				const sidebar = document.createElement('div');
				sidebar.classList.add('emoji-picker-sidebar');
				dropdown.appendChild(sidebar);

				const content = document.createElement('div');
				content.classList.add('emoji-picker-content');
				dropdown.appendChild(content);

				const search = document.createElement('input');
				search.type = 'text';
				search.placeholder = 'Search...';
				search.classList.add('emoji-picker-search');
				content.appendChild(search);

				const list = document.createElement('div');
				list.classList.add('emoji-picker-list');
				content.appendChild(list);

				document.body.appendChild(dropdown);

				const groups = [
					{ key: 'all', icon: 'apps' },
					{ key: 'smileys_emotion', icon: 'sentiment_satisfied' },
					{ key: 'people_body', icon: 'person' },
					{ key: 'component', icon: 'extension' },
					{ key: 'animals_nature', icon: 'pets' },
					{ key: 'food_drink', icon: 'restaurant' },
					{ key: 'travel_places', icon: 'flight' },
					{ key: 'activities', icon: 'sports_esports' },
					{ key: 'objects', icon: 'emoji_objects' },
					{ key: 'symbols', icon: 'emoji_symbols' }
				];

				let activeGroup = 'all';

				for (const g of groups) {
					const btn = document.createElement('button');
					btn.classList.add('emoji-picker-group-btn');
					btn.dataset.group = g.key;

					const icon = document.createElement('span');
					icon.classList.add('material-symbols-rounded');
					icon.textContent = g.icon;

					btn.appendChild(icon);

					btn.addEventListener('click', () => {
						activeGroup = g.key;
						sidebar.querySelectorAll('button').forEach(b => b.classList.remove('active'));
						btn.classList.add('active');
						renderList(emojis.all);
					});

					sidebar.appendChild(btn);
				}

				sidebar.querySelector('[data-group="all"]').classList.add('active');

				let currentEmojis = [];
				let rowHeight = 40;
				let itemsPerRow = 8;
				let bufferRows = 3;

				async function renderList(allEmojis) {
					list.innerHTML = '';
					currentEmojis = activeGroup === 'all'
						? allEmojis
						: allEmojis.filter(e => e.group === activeGroup);

					const totalRows = Math.ceil(currentEmojis.length / itemsPerRow);

					const spacer = document.createElement('div');
					spacer.style.height = `${totalRows * rowHeight}px`;
					list.appendChild(spacer);

					const grid = document.createElement('div');
					grid.classList.add('emoji-picker-grid');
					grid.style.position = 'absolute';
					grid.style.top = '0';
					grid.style.left = '0';
					grid.style.right = '0';
					list.appendChild(grid);

					list.style.position = 'relative';
					list.scrollTop = 0;

					async function renderVisible() {
						const scrollTop = list.scrollTop;
						const viewportHeight = list.clientHeight;

						const startRow = Math.max(0, Math.floor(scrollTop / rowHeight) - bufferRows);
						const endRow = Math.min(
							totalRows,
							Math.ceil((scrollTop + viewportHeight) / rowHeight) + bufferRows
						);

						const startIndex = startRow * itemsPerRow;
						const endIndex = Math.min(currentEmojis.length, endRow * itemsPerRow);

						grid.style.transform = `translateY(${startRow * rowHeight}px)`;
						grid.innerHTML = '';

						for (let i = startIndex; i < endIndex; i++) {
							const e = currentEmojis[i];
							const btn = document.createElement('button');
							btn.classList.add('emoji-picker-item');
							btn.title = e.annotation || e.shortcodes?.[0] || '';
							btn.innerHTML = await emojis.emojiToImg(e.emoji);

							btn.addEventListener('click', () => {
								let output = shortcode && e.shortcodes?.length ? e.shortcodes[0] : e.emoji;

								if (targetField) {
									const active = targetField;

									if (active.isContentEditable) {
										const sel = window.getSelection();
										if (sel && sel.rangeCount) {
											const range = sel.getRangeAt(0);
											range.deleteContents();
											range.insertNode(document.createTextNode(output));
											range.collapse(false);
											sel.removeAllRanges();
											sel.addRange(range);
											textareaFormatter.caret_end(textarea);
										}
									} else if ('selectionStart' in active) {
										const start = active.selectionStart;
										const end = active.selectionEnd;
										const value = active.value;
										active.value = value.slice(0, start) + output + value.slice(end);
										active.selectionStart = active.selectionEnd = start + output.length;
										textareaFormatter.caret_end(textarea);
									}
								}

								resolve(output);
								dropdown.remove();
							});

							grid.appendChild(btn);
						}
					}

					await renderVisible();

					let ticking = false;
					list.onscroll = () => {
						if (!ticking) {
							requestAnimationFrame(async () => {
								await renderVisible();
								ticking = false;
							});
							ticking = true;
						}
					};
				}

				renderList(emojis.all);

				search.addEventListener('input', async () => {
					const q = search.value.toLowerCase();
					const filtered = emojis.all.filter(e =>
						(e.annotation && e.annotation.toLowerCase().includes(q)) ||
						(e.shortcodes && e.shortcodes.some(s => s.toLowerCase().includes(q))) ||
						(e.tags && e.tags.some(t => t.toLowerCase().includes(q)))
					);
					await renderList(filtered);
				});
			}

			if (relativeTo) {
				relativeTo.appendChild(dropdown);
				dropdown.style.position = 'absolute';
				dropdown.style.bottom = '100%';
				dropdown.style.left = '100%';
				dropdown.style.transform = 'translateX(-100%)';
			} else {
				dropdown.style.position = 'fixed';
				dropdown.style.top = '50%';
				dropdown.style.left = '50%';
				dropdown.style.transform = 'translate(-50%, -50%)';
			}

			dropdown.style.display = 'flex';
			const searchInput = dropdown.querySelector('input');
			searchInput.value = '';
			searchInput.focus();

			function hideDropdown() {
				dropdown.remove();
				document.removeEventListener('click', outsideClick);
			}

			function outsideClick(e) {
				if (!dropdown.contains(e.target) && e.target !== targetField) hideDropdown();
			}

			setTimeout(() => document.addEventListener('click', outsideClick), 0);
		});
	}
};

export default emojis;