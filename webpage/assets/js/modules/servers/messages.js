// modules/servers/messages.js
// Module description: This module helps with managing messages in a server.
import ReactionsRender from "./reactions";

export default class MessageRenderer {
	constructor({ user_id, channels, server_id }) {
		this.user_id = user_id;
		this.channels = channels;
		this.server_id = server_id;
	}

	async create({ message, created_at, id: message_id, bot_message, sender_info, embed, parent_message_info, sent_by, command_info, sent_by_bot, reactions }, usersList) {
		if (!message && !embed) return null;

		const sanitizedUsername = sender_info.display_name ? sanitize(sender_info.display_name) : sanitize(sender_info.username);
		const sanitizedMessage = await sanitizeMsg(message, usersList, this.user_id, this.channels, this.server_id);
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
					<p class="username-reply">@${sanitize(parent_message_info.username)}</p>
					<p class="message-text-reply">${sanitize(parent_message_info.message_preview)}</p>
					</div>`
				: ''
			}
			${
				command_info && command_info !== null
				? `<div class="message-command">
					<img class="identification" src="/assets/images/identifier.svg">
					<p class="username-command">@${sanitize(command_info?.username) ?? ''}</p>
					<p>used</p>
					<p class="used-command">${sanitize(command_info?.command) ?? ''}</p>
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

		return msgEl;
	}

	async Embed({ embed }, usersList) {
		if (!embed) return '';

		const bot_id = embed.bot_id || '';

		return `
			<div class="embed" data-bot-id="${bot_id}" style="border-left: 4px solid ${embed.color || 'var(--clr-primary-a0)'};">
			${embed.title ? `<h3 class="embed-title">${sanitize(embed.title)}</h3>` : ''}
			${embed.description ? `<p class="embed-description">${await sanitizeMsg(embed.description, usersList, this.user_id, this.channels, this.server_id)}</p>` : ''}
			
			${embed.fields && embed.fields.length > 0 ? `
				<div class="embed-fields">
				${embed.fields.map(field => `
					<div class="embed-field"><strong>${sanitize(field.name)}</strong>${sanitize(field.value)}</div>
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
						? `<a href="${btn.url}" target="_blank" rel="noopener noreferrer" class="button-primary-filled ${btn.disabled ? 'disabled' : ''}"  style="${customStyle}">${sanitize(btn.label)}</a>`
						: `<button class="button-primary-filled ${btn.disabled ? 'disabled' : ''}" style="${customStyle}" ${btn.disabled ? 'disabled="true"' : ''} data-btn-id="${btn.id}">${sanitize(btn.label)}</button>`;
					}).join('')}
					</div>
				`).join('')}
				</div>
			` : ''}
			
			${embed.footer ? `<h5 class="embed-footer">${sanitize(embed.footer)}</h5>` : ''}
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
}

export class MessageBehavior {
	constructor({ user_id, channel_id, server_id, access_token, socket }) {
		this.user_id = user_id;
		this.reactionRenderer = new ReactionsRender({ user_id, channel_id, server_id, access_token, socket });
	}

	async attach(el, msg, insertIndex, messageCache) {
		const timestamp = msg.created_at;
		const sent_by = msg.sent_by !== null ? msg.sent_by : msg.sent_by_bot;

		const prevMsg = messageCache[insertIndex - 1];
		if (prevMsg && prevMsg.sent_by === sent_by) {
			const prevTime = new Date(prevMsg.timestamp).getTime();
			const currTime = new Date(timestamp).getTime();
			if ((currTime - prevTime) <= 10 * 60 * 1000) {
				if (!el.querySelector(".message-command") && !el.querySelector(".message-reply")) {
					el.classList.add("compact");
				}
			}
		}

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

		const mentionTags = el.querySelectorAll(`.user-link[data-user-id="${user_id}"], .user-link[data-user-id="everyone"]`);
		if (mentionTags.length > 0) el.classList.add("mentioned");

		const messageReplyEl = el.querySelector(".message-reply");
		if (messageReplyEl) {
			messageReplyEl.style.cursor = "pointer";
			messageReplyEl.addEventListener("click", () => {
				const targetId = messageReplyEl.getAttribute("data-message-id");
				if (!targetId) return;
				const targetMsg = messageContainer.querySelector(`.message[data-message-id="${targetId}"]`);
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
		insertIndex = this.cache.findIndex(m => msg.created_at < m.timestamp);
		if (insertIndex === -1) insertIndex = this.cache.length;
		this.cache.splice(insertIndex, 0, { id: msg.id, timestamp: msg.created_at, el });
		return insertIndex;
	}
}