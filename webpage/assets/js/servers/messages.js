export class Message {
    constructor(data, user_id, usersList, channels, server_id) {
        this.data = data;
        this.user_id = user_id;
        this.usersList = usersList;
        this.channels = channels;
        this.server_id = server_id;
    }

    async render() {
        const { message, created_at, id: message_id, bot_message, sender_info, embed, parent_message_info, sent_by, command_info, sent_by_bot, reactions } = this.data;
        const { user_id, usersList, channels, server_id } = this;

		if (!message && !embed) {
			return null;
		}

		const sanitizedUsername = sender_info.display_name ? sanitize(sender_info.display_name) : sanitize(sender_info.username);
		const sanitizedMessage = await sanitizeMsg(message, usersList, user_id, channels, server_id);
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
					${embeds ? `<div class="message-embed">${await Embeds({ embeds })}</div>` : ''}
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
					String(sent_by) === user_id
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
}

export const messages = {
    create: async (data) => new Message(data)
}