// modules/servers/reactions.js
// Module description: This module helps with rendering reactions for a message.
import emojis from "../../emojis.js";

export default class ReactionsRender {
	constructor({ user_id, channel_id, server_id, access_token, socket }) {
		this.user_id = user_id;
        this.channel_id = channel_id;
        this.server_id = server_id;
        this.access_token = access_token;
        this.socket = socket;
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
		const username = reactionGroup[0].reaction_user_info?.username || "";

		const div = document.createElement("div");
		div.className = "reaction" + (isOwn ? " own" : "");
		div.title = username;
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

		this.socket.emit("add_reaction", { access_token: this.access_token, message_id: msg_id, reaction: emoji, server_id: this.server_id, channel_id: this.channel_id });
	}
}