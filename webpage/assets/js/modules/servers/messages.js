// modules/servers/messages.js
// Module description: This module helps with managing messages in a server.

export default class MessageRenderer {
	constructor({ usersList, user_id, channels, server_id }) {
		this.usersList = usersList;
		this.user_id = user_id;
		this.channels = channels;
		this.server_id = server_id;
	}

	async create({ message, created_at, id: message_id, bot_message, sender_info, embed, parent_message_info, sent_by, command_info, sent_by_bot, reactions }) {
		if (!message && !embed) return null;

		const sanitizedUsername = sender_info.display_name ? sanitize(sender_info.display_name) : sanitize(sender_info.username);
		const sanitizedMessage = await sanitizeMsg(message, this.usersList, this.user_id, this.channels, this.server_id);
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
					${embeds ? `<div class="message-embed">${await this.Embeds({ embeds })}</div>` : ''}
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

	async Embed({ embed }) {
		if (!embed) return '';

		const bot_id = embed.bot_id || '';

		return `
			<div class="embed" data-bot-id="${bot_id}" style="border-left: 4px solid ${embed.color || 'var(--clr-primary-a0)'};">
			${embed.title ? `<h3 class="embed-title">${sanitize(embed.title)}</h3>` : ''}
			${embed.description ? `<p class="embed-description">${await sanitizeMsg(embed.description, this.usersList, this.user_id, this.channels, this.server_id)}</p>` : ''}
			
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

	async Embeds({ embeds }) {
		if (!embeds?.length) return '';
		const embedHtml = await Promise.all(embeds.map(e => this.Embed({ embed: e })));
		return `<div class="embeds-container">${embedHtml.join('')}</div>`;
	}
}