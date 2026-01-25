window.emojis = {
	map: {},
	regex: null,

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
	},

	async picker(targetField = null) {
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

				const search = document.createElement('input');
				search.type = 'text';
				search.placeholder = 'Search...';
				search.classList.add('emoji-picker-search');
				dropdown.appendChild(search);

				const list = document.createElement('div');
				list.classList.add('emoji-picker-list');
				dropdown.appendChild(list);

				document.body.appendChild(dropdown);

				function renderList(emojis) {
					list.innerHTML = '';
					const grouped = {};
					for (const e of emojis) {
						if (!grouped[e.group]) grouped[e.group] = {};
						if (!grouped[e.group][e.subgroup]) grouped[e.group][e.subgroup] = [];
						grouped[e.group][e.subgroup].push(e);
					}
					for (const groupName in grouped) {
						const groupDiv = document.createElement('div');
						groupDiv.classList.add('emoji-picker-group');

						const groupTitle = document.createElement('div');
						groupTitle.textContent = groupName.replaceAll('_', ' ');
						groupTitle.classList.add('emoji-picker-group-title');
						groupDiv.appendChild(groupTitle);

						const subgroups = grouped[groupName];
						for (const subgroupName in subgroups) {
							const subgroupDiv = document.createElement('div');
							subgroupDiv.classList.add('emoji-picker-subgroup');

							const subgroupTitle = document.createElement('div');
							subgroupTitle.textContent = subgroupName.replaceAll('_', ' ');
							subgroupTitle.classList.add('emoji-picker-subgroup-title');
							subgroupDiv.appendChild(subgroupTitle);

							for (const e of subgroups[subgroupName]) {
								const btn = document.createElement('button');
								btn.textContent = e.emoji;
								btn.title = e.annotation || e.shortcodes?.[0] || '';
								btn.classList.add('emoji-picker-item');

								btn.addEventListener('click', () => {
									if (targetField) {
										const active = targetField;
										const emoji = e.emoji;

										if (active.isContentEditable) {
											const sel = window.getSelection();
											if (sel && sel.rangeCount) {
												const range = sel.getRangeAt(0);
												range.deleteContents();
												range.insertNode(document.createTextNode(emoji));
												range.collapse(false);
												sel.removeAllRanges();
												sel.addRange(range);
											}
										} else if ('selectionStart' in active) {
											const start = active.selectionStart;
											const end = active.selectionEnd;
											const value = active.value;
											active.value = value.slice(0, start) + emoji + value.slice(end);
											active.selectionStart = active.selectionEnd = start + emoji.length;
										}
									}
									resolve(e.emoji);
								});

								subgroupDiv.appendChild(btn);
							}

							groupDiv.appendChild(subgroupDiv);
						}

						list.appendChild(groupDiv);
					}
				}

				renderList(window.emojis.all);

				search.addEventListener('input', () => {
					const q = search.value.toLowerCase();
					const filtered = window.emojis.all.filter(e =>
						(e.annotation && e.annotation.toLowerCase().includes(q)) ||
						(e.shortcodes && e.shortcodes.some(s => s.toLowerCase().includes(q))) ||
						(e.tags && e.tags.some(t => t.toLowerCase().includes(q))) ||
						(e.group && e.group.toLowerCase().includes(q)) ||
						(e.subgroup && e.subgroup.toLowerCase().includes(q))
					);
					renderList(filtered);
				});
			}

			dropdown.style.display = 'block';
			const searchInput = dropdown.querySelector('input');
			searchInput.value = '';
			searchInput.focus();

			document.addEventListener('click', function hide(e) {
				if (!dropdown.contains(e.target) && e.target !== targetField) {
					dropdown.style.display = 'none';
					document.removeEventListener('click', hide);
				}
			});
		});
	}
};
