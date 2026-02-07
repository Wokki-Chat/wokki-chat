// modules/servers/messages.js
// Module description: This module helps with managing messages in a server.
import ReactionsRender from "./reactions.js";
import { Sanitizer } from "../global/sanitization.js";

export class MessageRenderer {
	constructor({ user_id, channels, server_id }) {
		this.user_id = user_id;
		this.channels = channels;
		this.server_id = server_id;
		this.sanitizer = new Sanitizer(this.user_id, this.channels, this.server_id);
	}

	addAssets(asset, msgId, index) {
		const type = getAssetType(asset.savedName);
		const url = type === 'profile_picture'
			? `https://chat.wokki20.nl/uploads/profile-pictures/${encodeURIComponent(asset.savedName.slice(0, -4))}`
			: `https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}`;

		if (type === 'image' || type === 'profile_picture') {
			return `<img data-src="${url}" alt="${asset.originalName}" class="message-asset-image${type === 'profile_picture' ? ' message-asset-profile-picture' : ''} lazyload" data-prefetch-size="true" />`;
		} else if (type === 'video') {
			return `<video data-src="${url}" controls class="message-asset-video lazyload" data-prefetch-size="true"></video>`;
		} else if (type === 'audio') {
			return `
				<div class="custom-player" data-originalName="${asset.originalName}" data-audio-src="${url}">
					<span class="material-symbols-rounded play-pause" style="cursor:pointer;">play_arrow</span>
					<div class="time-left-current">
						<span class="current-time">0:00</span>
						<span class="duration">/ 0:00</span>
					</div>
					<input type="range" class="seek-bar" value="0" step="1" min="0">
					<div class="player-options">
						<div class="player-option" id="download">
							<span class="material-symbols-rounded">download</span>
						</div>
					</div>
				</div>
			`;
		} else if (type === 'pdf') {
			return `<a href="${url}" target="_blank" class="message-asset-pdf link">${this.sanitizer.sanitize(asset.originalName)}</a>`;
		} else if (type === 'txt') {
			return `<pre class="message-asset-text" id="txt-asset-${msgId}-${index}"><div class="lang-bar"><p>Plaintext</p><span class="material-symbols-rounded">content_copy</span></div><code class="lang-plaintext">Loading...</code></pre>`;
		} else {
			return `<a href="${url}" download class="message-asset-file link">${this.sanitizer.sanitize(asset.savedName)}</a>`;
		}
	}

	async create({ message, created_at, id: message_id, bot_message, sender_info, embed, parent_message_info, sent_by, command_info, sent_by_bot, assets }, usersList) {
		if (!message && !embed) return null;

		this.sanitizer.init(usersList);

		const sanitizedUsername = sender_info.display_name ? this.sanitizer.sanitize(sender_info.display_name) : this.sanitizer.sanitize(sender_info.username);
		const sanitizedMessage = await this.sanitizer.sanitizeMsg(message);
		const createdAtDate = new Date(created_at);

		let embedsRaw = null;
		try {
			embedsRaw = typeof embed === 'string' ? JSON.parse(embed) : embed;
		} catch {
			embedsRaw = null;
		}

		const embeds = Array.isArray(embedsRaw)
			? embedsRaw
			: embedsRaw
				? [embedsRaw]
				: null;

		const msgEl = document.createElement("div");
		msgEl.classList.add("message");
		msgEl.dataset.timestamp = created_at;
		msgEl.dataset.messageId = message_id;
		msgEl.dataset.sentBy = sent_by !== null ? sent_by : sent_by_bot;

		msgEl.innerHTML = `
			${
				parent_message_info !== null
				? `<div class="message-reply" data-message-id="${parent_message_info.message_id}">
					<img class="identification" src="/assets/images/identifier.svg">
					<p class="username-reply">@${this.sanitizer.sanitize(parent_message_info.username)}</p>
					<p class="message-text-reply">${this.sanitizer.sanitize(parent_message_info.message_preview)}</p>
					</div>`
				: ''
			}
			${
				command_info && command_info !== null
				? `<div class="message-command">
					<img class="identification" src="/assets/images/identifier.svg">
					<p class="username-command">@${this.sanitizer.sanitize(command_info?.username) ?? ''}</p>
					<p>used</p>
					<p class="used-command">${this.sanitizer.sanitize(command_info?.command) ?? ''}</p>
					</div>`
				: ''
			}
			<div class="message-content">
				<img class="profile-picture" src="${sender_info.profile_picture}" />
				<div class="message-info">
					<div class="username-date">
						<p class="username">${sanitizedUsername}</p>
						${sender_info.premium == true ? '<div class="premium-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_premium.svg"><p class="premium-tag-tooltip">Premium</p></div>' : ''}
						${sender_info.staff == 1 ? '<div class="staff-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_staff.svg"><p class="staff-tag-tooltip">Staff</p></div>' : ''}
						${bot_message == 1 ? '<div class="bot-tag"><span class="material-symbols-rounded">check</span>BOT</div>' : ''}
						<p class="date">${createdAtDate.toLocaleString()}</p>
					</div>
					<p class="message-text">${sanitizedMessage}</p>
					${embeds ? `<div class="message-embed">${await this.Embeds({ embeds }, usersList)}</div>` : ''}
					<div class="message-reactions"></div>
				</div>
			</div>
			<div class="message-options">
				<div class="message-option" id="reply-btn">
					<span class="material-symbols-rounded">reply</span>
				</div>
				<div class="message-option" id="reaction-btn">
					<span class="material-symbols-rounded">add_reaction</span>
				</div>
				${
					String(sent_by) === this.user_id
					? `
					<div class="message-option danger" id="delete-btn">
						<span class="material-symbols-rounded">delete</span>
					</div>
					`
					: ''
				}
			</div>
		`;

		if (assets && assets.length > 0) {
			const assetsContainer = document.createElement("div");
			assetsContainer.classList.add("message-assets");

			assetsContainer.innerHTML = assets.map((asset, index) => {
				return this.addAssets(asset, message_id, index);
			}).join('');

			const messageInfo = msgEl.querySelector(".message-info");
			const reactionsDiv = messageInfo.querySelector(".message-reactions");
			messageInfo.insertBefore(assetsContainer, reactionsDiv);
		}

		return msgEl;
	}

	async Embed({ embed }, usersList) {
		if (!embed) return '';

		const bot_id = embed.bot_id || '';

		return `
			<div class="embed" data-bot-id="${bot_id}" style="border-left: 4px solid ${embed.color || 'var(--clr-primary-a0)'};">
			${embed.title ? `<h3 class="embed-title">${this.sanitizer.sanitize(embed.title)}</h3>` : ''}
			${embed.description ? `<p class="embed-description">${await this.sanitizer.sanitizeMsg(embed.description)}</p>` : ''}
			
			${embed.fields && embed.fields.length > 0 ? `
				<div class="embed-fields">
				${embed.fields.map(field => `
					<div class="embed-field"><strong>${this.sanitizer.sanitize(field.name)}</strong>${this.sanitizer.sanitize(field.value)}</div>
				`).join('')}
				</div>
			` : ''}
			
			${embed.components && embed.components.length > 0 ? `
				<div class="embed-components">
				${embed.components.map(row => `
					<div class="embed-button-row">
					${row.map(btn => {
						const customStyle = `background-color: ${btn.color || 'var(--clr-primary-a0)'}; color: ${btn.text_color || 'var(--clr-text-a0)'}; user-select: none;`;
						return btn.type === 'link'
						? `<a href="${btn.url}" target="_blank" rel="noopener noreferrer" class="button-primary-filled ${btn.disabled ? 'disabled' : ''}"  style="${customStyle}">${this.sanitizer.sanitize(btn.label)}</a>`
						: `<button class="button-primary-filled ${btn.disabled ? 'disabled' : ''}" style="${customStyle}" ${btn.disabled ? 'disabled="true"' : ''} data-btn-id="${btn.id}">${this.sanitizer.sanitize(btn.label)}</button>`;
					}).join('')}
					</div>
				`).join('')}
				</div>
			` : ''}
			
			${embed.footer ? `<h5 class="embed-footer">${this.sanitizer.sanitize(embed.footer)}</h5>` : ''}
			</div>
		`;
	}

	async Embeds({ embeds }, usersList) {
		if (!embeds?.length) return '';
		const embedHtml = await Promise.all(embeds.map(e => this.Embed({ embed: e }, usersList)));
		return `<div class="embeds-container">${embedHtml.join('')}</div>`;
	}
}

export class MessageHydrator {
	constructor() {}

	async hydrate(msgEl, assets, msgId) {
		const invites = msgEl.querySelectorAll('.invite-item-container.loading');
		invites.forEach(async el => {
			const url = el.dataset.inviteUrl;
			const inviteId = el.dataset.inviteId;

			try {
				const res = await fetch(url);
				if (!res.ok) throw 0;
				const html = await res.text();

				const serverName = html.match(/server_name["'] content=["']([^"']+)/i)?.[1] ?? "Unknown Server";
				const serverImage = html.match(/server_image["'] content=["']([^"']+)/i)?.[1] ?? "";
				const serverCreatedAt = html.match(/server_created_at["'] content=["']([^"']+)/i)?.[1];
				const serverId = html.match(/server_id["'] content=["']([^"']+)/i)?.[1];
				const expired = html.match(/invite_expired["'] content=["']([^"']+)/i)?.[1];

				const date = serverCreatedAt
					? new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
						.format(new Date(serverCreatedAt))
					: "Unknown Date";

				el.innerHTML = `
					<div class="invite-item-name-icon-container">
						<img src="${serverImage}" class="invite-item-icon">
						<div>
							<p>${serverName}</p>
							<p>${date}</p>
						</div>
					</div>
					<button class="button-primary-filled ${expired ? "disabled" : ""} invite-join-button"
							onclick="${expired ? "" : `window.location.href='https://chat.wokki20.nl/server/${serverId}?invite=${inviteId}'`}">
						${expired ? "Invite Expired" : "Join Server"}
					</button>
				`;
				el.classList.remove('loading');
			} catch {
				el.innerHTML = `<p>This invite is invalid</p>`;
			}
		});
		const tracks = msgEl.querySelectorAll('.spotify-track-container.loading');
		tracks.forEach(el => {
			const trackId = el.dataset.spotifyTrackId;
			if (!trackId) return;

			const iframe = document.createElement('iframe');
			iframe.src = `https://open.spotify.com/embed/track/${trackId}`;
			iframe.width = '100%';
			iframe.height = '152';
			iframe.frameBorder = '0';
			iframe.allow = 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture';
			iframe.allowFullscreen = true;
			iframe.style.borderRadius = '12px';
			iframe.loading = 'lazy';

			el.innerHTML = '';
			el.appendChild(iframe);
			el.classList.remove('loading');
		});

		if (assets && assets.length > 0) {
			const lazyEls = msgEl.querySelectorAll('[data-src]');
			lazyEls.forEach(el => {
				if (el.tagName === 'IMG' || el.tagName === 'VIDEO') {
					el.src = el.dataset.src;
				}
				el.removeAttribute('data-src');
				el.classList.remove('lazyload');
			});

			const txtPromises = assets.map(async (asset, index) => {
				if (getAssetType(asset.savedName) !== 'txt') return;

				const preEl = msgEl.querySelector(`#txt-asset-${msgId}-${index} code`);
				if (!preEl) return;

				try {
					const contents = await this.getAssetFileInsides(asset.savedName);
					preEl.textContent = this.sanitizer.sanitize(contents);

					const pre = preEl.parentElement;
					if (!pre.querySelector(".lang-bar")) {
						let lang = "bash";
						const langClass = [...preEl.classList].find(c => c.startsWith("lang-"));
						if (langClass) lang = langClass.slice(5);

						pre.insertAdjacentHTML("afterbegin", `
							<div class="lang-bar">
								<p>${lang}</p>
								<span class="material-symbols-rounded copy-icon" style="cursor:pointer;">
									content_copy
								</span>
							</div>
						`);

						const copyIcon = pre.querySelector(".copy-icon");
						copyIcon.addEventListener("click", () => {
							navigator.clipboard.writeText(preEl.innerText).then(() => {
								copyIcon.textContent = "check";
								setTimeout(() => copyIcon.textContent = "content_copy", 3000);
							});
						});
					}
				} catch {
					preEl.textContent = '[Failed to load file]';
				}
			});

			await Promise.all(txtPromises);
		}

		return true;
	}

	async getAssetFileInsides(file) {
		const res = await fetch(`https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(file)}`);
		const blob = await res.blob();
		return new Promise((resolve, reject) => {
			const reader = new FileReader();
			reader.onload = () => resolve(reader.result);
			reader.onerror = () => reject("Failed to read file");
			reader.readAsText(blob);
		});
	}
}

export class MessageBehaviour {
	constructor({ user_id, channel_id, server_id, access_token, socket, messageContainer }) {
		this.user_id = user_id;
		this.reactionRenderer = new ReactionsRender({ user_id, channel_id, server_id, access_token, socket });
		this.messageContainer = messageContainer;
	}

	applyCompactMode(el, msg, insertIndex, messageCache) {
		const prevMsg = messageCache[insertIndex - 1];
		if (!prevMsg) return;

		const prevSentBy = prevMsg?.el?.dataset?.sentBy || '';
		const msgSentBy = msg.sent_by ? msg.sent_by.toString() : msg.sent_by_bot ? msg.sent_by_bot.toString() : '';
		if (prevSentBy !== msgSentBy) return;

		const prevTime = new Date(prevMsg.timestamp).getTime();
		const currTime = new Date(msg.created_at).getTime();

		if (currTime - prevTime > 10 * 60 * 1000) return;
		if (el.querySelector(".message-command") || el.querySelector(".message-reply")) return;

		el.classList.add("compact");
	}

	async attach(el, msg) {
		const sent_by = msg.sent_by !== null ? msg.sent_by : msg.sent_by_bot;

		el.querySelector('.username').addEventListener('click', (e) => {
			e.stopPropagation();
			const userInfoProfile = document.querySelector(`.info-profile[data-user-id="${sent_by}"]`);
			if (userInfoProfile) {
				if (userInfoProfile.offsetParent !== null) {
					userInfoProfile.scrollIntoView({ behavior: "smooth", block: "center" });
					requestAnimationFrame(() => {
						if (userInfoProfile.getBoundingClientRect().top > 0) userInfoProfile.click();
					});
				} else {
					userInfoProfile.click();
				}
			}
		});

		const mentionTags = el.querySelectorAll(`.user-link[data-user-id="${this.user_id}"], .user-link[data-user-id="everyone"]`);
		if (mentionTags.length > 0) el.classList.add("mentioned");

		const messageReplyEl = el.querySelector(".message-reply");
		if (messageReplyEl) {
			messageReplyEl.style.cursor = "pointer";
			messageReplyEl.addEventListener("click", () => {
				const targetId = messageReplyEl.getAttribute("data-message-id");
				if (!targetId) return;
				const targetMsg = this.messageContainer.querySelector(`.message[data-message-id="${targetId}"]`);
				if (targetMsg) {
					targetMsg.scrollIntoView({ behavior: "smooth", block: "center" });
					targetMsg.classList.add("highlight-parent-msg");
					setTimeout(() => targetMsg.classList.remove("highlight-parent-msg"), 2000);
				}
			});
		}

		const reactionButton = el.querySelector("#reaction-btn");
		reactionButton.addEventListener("click", () => this.reactionRenderer.handleReactionClick(el, msg.id, null));

		const reactionsWrapper = el.querySelector(".message-reactions");
		if (reactionsWrapper) {
			const reactionsEl = msg.reactions ? await this.reactionRenderer.create({ reactions: msg.reactions }, msg.id) : document.createDocumentFragment();
			reactionsWrapper.appendChild(reactionsEl);
		}

		const embedContainer = el.querySelector('.message-embed');
		if (embedContainer) {
			embedContainer.addEventListener('click', (event) => {
				const btn = event.target.closest('button[data-btn-id]');
				if (!btn) return;
				const embedDiv = btn.closest('.embed');
				if (!embedDiv) return;
				const bot_id = embedDiv.dataset.botId;
				const btn_id = btn.dataset.btnId;
				socket.emit('embed_button', { bot_id, button_id: btn_id, access_token: this.access_token, server_id: this.server_id, channel_id: this.channel_id });
			});
		}
	}
}

export class MessageCache {
	constructor() {
		this.cache = [];
	}

	removeExisting(id) {
		const exists = this.cache.findIndex(m => m.id === id);
		if (exists !== -1) {
			const existing = this.cache[exists];
			existing.el.remove();
			this.cache.splice(exists, 1);
		}
	}

	insertIntoCache(msg, el) {
		let insertIndex = this.cache.findIndex(m => 
			msg.created_at < m.timestamp || 
			(msg.created_at === m.timestamp && msg.id < m.id)
		);
		if (insertIndex === -1) insertIndex = this.cache.length;
		this.cache.splice(insertIndex, 0, { id: msg.id, timestamp: msg.created_at, el });
		return insertIndex;
	}

	getById(id) {
		return this.cache.find(m => m.id === id);
	}

	getIndexById(id) {
		return this.cache.findIndex(m => m.id === id);
	}

	delete(id) {
		const index = this.getIndexById(id);
		if (index === -1) return null;
		const msg = this.cache[index];
		if (msg.el) msg.el.remove();
		this.cache.splice(index, 1);
		return index;
	}
}
