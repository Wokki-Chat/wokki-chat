import { TextareaFormatter } from "./modules/global/sanitization.js";

const TWEMOJI_BASE = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@v14.0.2/assets/';
const EMOJI_DATA_URL = 'https://cdn.jsdelivr.net/npm/@emoji-mart/data';

const PICKER_HEIGHT = 350;
const PICKER_WIDTH = 500;
const ROW_HEIGHT = 40;
const ITEMS_PER_ROW = 11;
const BUFFER_ROWS = 3;

const TWEMOJI_OPTS = {
	folder: 'svg',
	ext: '.svg',
	base: TWEMOJI_BASE,
	className: 'emoji'
};

const GROUP_ICONS = {
	people: 'sentiment_satisfied',
	nature: 'pets',
	foods: 'restaurant',
	activity: 'sports_esports',
	places: 'flight',
	objects: 'emoji_objects',
	symbols: 'emoji_symbols',
	flags: 'flag'
};

const emojis = {
	map: {},
	regex: null,
	all: null,
	_categories: [],

	async _initTwemoji() {
		if (window.twemoji) return;
		await new Promise((resolve, reject) => {
			const s = document.createElement('script');
			s.src = 'https://unpkg.com/twemoji@latest/dist/twemoji.min.js';
			s.crossOrigin = 'anonymous';
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild(s);
		});
	},

	async load() {
		await this._initTwemoji();

		const data = await fetch(EMOJI_DATA_URL).then(r => r.json());

		this._categories = data.categories;
		const emojiEntries = data.emojis;

		const groupById = {};
		for (const cat of this._categories) {
			for (const id of cat.emojis) {
				groupById[id] = cat.id;
			}
		}

		this.all = [];

		for (const [id, entry] of Object.entries(emojiEntries)) {
			const skin = entry.skins?.[0];
			if (!skin?.native) continue;

			this.all.push({
				emoji: skin.native,
				annotation: entry.name,
				group: groupById[id] || null,
				shortcodes: [id, ...(entry.keywords || [])],
				tags: entry.keywords || []
			});

			this.map[`:${id}:`] = skin.native;
		}

		const keys = Object.keys(this.map);
		if (keys.length > 0) {
			this.regex = new RegExp(
				`(?<!\\\\)(${keys
					.map(k => k.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '\\$1'))
					.join('|')})`,
				'g'
			);
		}
	},

	emojiToImg(emoji) {
		if (!window.twemoji) return emoji;
		return twemoji.parse(emoji, TWEMOJI_OPTS);
	},

	replaceText(text) {
		if (!text) return text;
		let result = text;
		if (this.regex) {
			result = result.replace(this.regex, match => this.map[match] || match);
		}
		result = result.replace(/\\:/g, ':');
		if (window.twemoji) {
			result = twemoji.parse(result, TWEMOJI_OPTS);
		}
		return result;
	},

	isInCodeBlock(node) {
		let p = node.parentNode;
		while (p) {
			if (
				p.nodeName === 'CODE' ||
				p.nodeName === 'PRE' ||
				p.nodeName === 'KBD' ||
				p.classList?.contains('code-block')
			) return true;
			p = p.parentNode;
		}
		return false;
	},

	async replaceAllTextNodes(root = document.body) {
		const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
		const nodes = [];
		let node;
		while ((node = walker.nextNode())) {
			if (this.isInCodeBlock(node)) continue;
			nodes.push(node);
		}

		for (const node of nodes) {
			let text = node.textContent;
			if (this.regex) {
				text = text.replace(this.regex, match => this.map[match] || match);
			}
			text = text.replace(/\\:/g, ':');

			const span = document.createElement('span');
			span.textContent = text;
			if (window.twemoji) {
				twemoji.parse(span, TWEMOJI_OPTS);
			}
			node.replaceWith(...span.childNodes);
		}
	},

	async replaceAll() {
		await this.replaceAllTextNodes();
	},

	async replaceEl(element) {
		if (!element) return;
		if (!Array.isArray(this.all) || this.all.length === 0) await this.load();
		await this.replaceAllTextNodes(element);
	},

	_positionDropdown(dropdown, relativeTo) {
		const rect = relativeTo.getBoundingClientRect();
		const spaceAbove = rect.top;
		const spaceBelow = window.innerHeight - rect.bottom;

		dropdown.style.position = 'fixed';
		dropdown.style.transform = 'none';
		dropdown.style.top = 'auto';
		dropdown.style.bottom = 'auto';

		if (spaceBelow >= PICKER_HEIGHT || spaceBelow >= spaceAbove) {
			dropdown.style.top = `${rect.bottom + 4}px`;
		} else {
			dropdown.style.bottom = `${window.innerHeight - rect.top + 4}px`;
		}

		let left = rect.right - PICKER_WIDTH;
		if (left + PICKER_WIDTH > window.innerWidth - 8) left = window.innerWidth - PICKER_WIDTH - 8;
		if (left < 8) left = 8;
		dropdown.style.left = `${left}px`;
	},

	async picker(targetField = null, relativeTo = null, shortcode = false) {
		const textareaFormatter = new TextareaFormatter();
		if (!Array.isArray(this.all) || this.all.length === 0) {
			await this.load();
		}

		return new Promise((resolve) => {
			document.getElementById('emoji-picker-dropdown')?.remove();

			const dropdown = document.createElement('div');
			dropdown.id = 'emoji-picker-dropdown';
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

			const listEl = document.createElement('div');
			listEl.classList.add('emoji-picker-list');
			content.appendChild(listEl);

			const groups = [
				{ key: 'all', icon: 'apps' },
				...this._categories.map(cat => ({
					key: cat.id,
					icon: GROUP_ICONS[cat.id] || 'tag'
				}))
			];

			let activeGroup = 'all';
			let currentEmojis = [];
			let scrollRAF = null;
			let currentFilteredSource = this.all;

			function renderVisible(grid, totalRows) {
				const scrollTop = listEl.scrollTop;
				const viewportHeight = listEl.clientHeight;
				const startRow = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT) - BUFFER_ROWS);
				const endRow = Math.min(totalRows, Math.ceil((scrollTop + viewportHeight) / ROW_HEIGHT) + BUFFER_ROWS);
				const startIndex = startRow * ITEMS_PER_ROW;
				const endIndex = Math.min(currentEmojis.length, endRow * ITEMS_PER_ROW);

				grid.style.transform = `translateY(${startRow * ROW_HEIGHT}px)`;
				grid.innerHTML = '';

				for (let i = startIndex; i < endIndex; i++) {
					const e = currentEmojis[i];
					const btn = document.createElement('button');
					btn.classList.add('emoji-picker-item');
					btn.title = e.annotation || e.shortcodes?.[0] || '';
					btn.innerHTML = emojis.emojiToImg(e.emoji);
					btn.addEventListener('click', () => handleSelect(e));
					grid.appendChild(btn);
				}
			}

			function renderList(source) {
				listEl.innerHTML = '';
				currentEmojis = activeGroup === 'all'
					? source
					: source.filter(e => e.group === activeGroup);

				const totalRows = Math.ceil(currentEmojis.length / ITEMS_PER_ROW);

				const spacer = document.createElement('div');
				spacer.style.height = `${totalRows * ROW_HEIGHT}px`;

				const grid = document.createElement('div');
				grid.classList.add('emoji-picker-grid');
				grid.style.position = 'absolute';
				grid.style.top = '0';
				grid.style.left = '0';
				grid.style.right = '0';

				listEl.style.position = 'relative';
				listEl.appendChild(spacer);
				listEl.appendChild(grid);
				listEl.scrollTop = 0;

				renderVisible(grid, totalRows);

				listEl.onscroll = () => {
					if (scrollRAF) return;
					scrollRAF = requestAnimationFrame(() => {
						renderVisible(grid, totalRows);
						scrollRAF = null;
					});
				};
			}

			function handleSelect(e) {
				const output = shortcode && e.shortcodes?.length ? `:${e.shortcodes[0]}:` : e.emoji;
				if (targetField) {
					if (targetField.isContentEditable) {
						const sel = window.getSelection();
						if (sel && sel.rangeCount) {
							const range = sel.getRangeAt(0);
							range.deleteContents();
							range.insertNode(document.createTextNode(output));
							range.collapse(false);
							sel.removeAllRanges();
							sel.addRange(range);
						}
						textareaFormatter.caret_end(targetField);
					} else if ('selectionStart' in targetField) {
						const start = targetField.selectionStart;
						const end = targetField.selectionEnd;
						const val = targetField.value;
						targetField.value = val.slice(0, start) + output + val.slice(end);
						targetField.selectionStart = targetField.selectionEnd = start + output.length;
						textareaFormatter.caret_end(targetField);
					}
				}
				resolve(output);
				hideDropdown();
			}

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
					renderList(currentFilteredSource);
				});
				sidebar.appendChild(btn);
			}
			sidebar.querySelector('[data-group="all"]').classList.add('active');

			search.addEventListener('input', () => {
				const q = search.value.toLowerCase().trim();
				currentFilteredSource = !q
					? emojis.all
					: emojis.all.filter(e =>
						(e.annotation && e.annotation.toLowerCase().includes(q)) ||
						(e.shortcodes && e.shortcodes.some(s => s.toLowerCase().includes(q))) ||
						(e.tags && e.tags.some(t => t.toLowerCase().includes(q)))
					);
				renderList(currentFilteredSource);
			});

			document.body.appendChild(dropdown);

			let cleanupReposition = null;
			if (relativeTo) {
				emojis._positionDropdown(dropdown, relativeTo);
				const onReposition = () => emojis._positionDropdown(dropdown, relativeTo);
				window.addEventListener('resize', onReposition);
				window.addEventListener('scroll', onReposition, { passive: true });
				cleanupReposition = () => {
					window.removeEventListener('resize', onReposition);
					window.removeEventListener('scroll', onReposition);
				};
			} else {
				dropdown.style.position = 'fixed';
				dropdown.style.top = '50%';
				dropdown.style.left = '50%';
				dropdown.style.transform = 'translate(-50%, -50%)';
			}

			dropdown.style.display = 'flex';
			search.focus();
			renderList(emojis.all);

			function hideDropdown() {
				cleanupReposition?.();
				dropdown.remove();
				document.removeEventListener('click', outsideClick);
			}

			function outsideClick(e) {
				if (!dropdown.contains(e.target) && e.target !== targetField) {
					hideDropdown();
				}
			}

			setTimeout(() => document.addEventListener('click', outsideClick), 0);
		});
	}
};

export default emojis;