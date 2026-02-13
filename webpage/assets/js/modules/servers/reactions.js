// modules/servers/reactions.js
// Module description: This module helps with rendering reactions for a message.
import emojis from "../../emojis.js";

export default class ReactionsRender {
	constructor({ user_id, channel_id = null, server_id = null, access_token, socket, contact_id = null }) {
		this.user_id = user_id;
        this.channel_id = channel_id;
        this.server_id = server_id;
        this.access_token = access_token;
        this.socket = socket;
		this.contact_id = contact_id;
	}
    
	async create({ reactions }, msg_id) {
		if (!reactions || reactions.length === 0) return document.createDocumentFragment();

		const normalCounts = {};
		const superCounts = {};

		for (const r of reactions) {
			const key = r.reaction;
			if (r.super_reaction) {
				if (!superCounts[key]) superCounts[key] = [];
				superCounts[key].push(r);
			} else {
				if (!normalCounts[key]) normalCounts[key] = [];
				normalCounts[key].push(r);
			}
		}

		const grouped = [...Object.values(normalCounts), ...Object.values(superCounts)];

		const fragment = document.createDocumentFragment();
		for (const group of grouped) {
			const reactionEl = await this.reaction({ reactionGroup: group, count: group.length }, msg_id);
			if (reactionEl) fragment.appendChild(reactionEl);
		}

		const addReactionEl = document.createElement("div");
		addReactionEl.classList.add("reaction", "add-reaction");
		addReactionEl.innerHTML = `<span class="add-reaction-icon material-symbols-rounded">add_reaction</span>`;
		fragment.appendChild(addReactionEl);

		addReactionEl.addEventListener("click", () => this.handleReactionClick(addReactionEl, msg_id, null));

		const container = document.createElement("div");
		container.classList.add("message-reactions-container");
		container.appendChild(fragment);

		return container;
	}

	async reaction({ reactionGroup, count }, msg_id) {
		if (!reactionGroup || reactionGroup.length === 0) return null;

		const { reaction: emojiText, super_reaction } = reactionGroup[0];
		const renderedEmoji = await emojis.replaceText(emojiText);
		const isOwn = reactionGroup.some(r => String(r.user_id) === String(this.user_id));

		const div = document.createElement("div");
		div.className = "reaction" + (isOwn ? " own" : "");
		div.dataset.reactionName = emojiText;
		div.dataset.messageId = msg_id;
		div.innerHTML = `<span class="emoji">${renderedEmoji}</span>${count ? `<span class="count">${count}</span>` : ''}`;

		div.addEventListener("click", () => {
			this.handleReactionClick(div, msg_id, emojiText);
		});

		return div;
	}

	async handleReactionClick(el, msg_id, emoji = null) {
		if (!emoji) {
			emoji = await emojis.picker(null, el, true);
			if (!emoji) return;
		}

		this.socket.emit("add_reaction", { access_token: this.access_token, message_id: msg_id, reaction: emoji, server_id: this.server_id, channel_id: this.channel_id, contact_id: this.contact_id });
	}

	async updateReactionUI(el, msg_id, emoji, reactingUserId, removed = false, messageContainer) {
		const scrollTopBefore = messageContainer.scrollTop;
		const scrollHeightBefore = messageContainer.scrollHeight;
		const nearBottom = scrollHeightBefore - scrollTopBefore - messageContainer.clientHeight <= 10;

		let messageReactions = el.querySelector(".message-reactions-container");
		if (!messageReactions && !removed) {
			messageReactions = document.createElement("div");
			messageReactions.classList.add("message-reactions-container");
			const reactionsWrapper = el.querySelector(".message-reactions");
			reactionsWrapper.appendChild(messageReactions);
		}
		if (!messageReactions) return;

		const reactionEl = messageReactions.querySelector(`.reaction[data-reaction-name="${emoji}"]`);

		if (reactionEl) {
			const countEl = reactionEl.querySelector(".count");

			if (removed) {
				if (countEl) {
					const newCount = parseInt(countEl.textContent) - 1;
					if (newCount <= 0) {
						reactionEl.remove();
					} else {
						countEl.textContent = newCount;
						if (String(reactingUserId) === String(this.user_id)) {
							reactionEl.classList.remove("own");
						}
					}
				} else {
					reactionEl.remove();
				}
			} else {
				if (countEl) {
					countEl.textContent = parseInt(countEl.textContent) + 1;
				} else {
					const countSpan = document.createElement("span");
					countSpan.classList.add("count");
					countSpan.textContent = "1";
					reactionEl.appendChild(countSpan);
				}
				if (String(reactingUserId) === String(this.user_id)) {
					reactionEl.classList.add("own");
				}
			}
		} else if (!removed) {
			const newReactionEl = await this.reaction({
				reactionGroup: [{
					reaction: emoji,
					user_id: reactingUserId,
					reaction_user_info: { profile_picture: profile_picture_url }
				}],
				count: 1
			}, msg_id);

			const addReactionBtn = messageReactions.querySelector(".reaction.add-reaction");
			if (addReactionBtn) {
				addReactionBtn.before(newReactionEl);
			} else {
				messageReactions.appendChild(newReactionEl);
			}
		}

		const realReactions = messageReactions.querySelectorAll(".reaction:not(.add-reaction)");
		if (realReactions.length === 0) {
			messageReactions.remove();
			return;
		}

		let addBtn = messageReactions.querySelector(".reaction.add-reaction");
		if (!addBtn) {
			addBtn = document.createElement("div");
			addBtn.classList.add("reaction", "add-reaction");
			addBtn.innerHTML = `<span class="add-reaction-icon material-symbols-rounded">add_reaction</span>`;
			messageReactions.appendChild(addBtn);
		}

		if (!nearBottom) {
			const scrollHeightAfter = messageContainer.scrollHeight;
			messageContainer.scrollTop = scrollTopBefore + (scrollHeightAfter - scrollHeightBefore);
		} else {
			requestAnimationFrame(() => {
				messageContainer.scrollTop = messageContainer.scrollHeight;
			});
		}
	}
}