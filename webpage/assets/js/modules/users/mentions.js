// modules/users/mentions.js
// Module description: This module helps loading mentions in a textarea.
import { Sanitizer, TextareaFormatter } from "../global/sanitization.js";

export class Mentions {
	constructor({ user_id, channels, server_id, users_list }) {
		this.user_id = user_id;
		this.channels = channels;
		this.server_id = server_id;
		this.users_list = users_list;
		this.sanitizer = new Sanitizer(this.user_id, this.channels, this.server_id);
        this.textareaFormatter = new TextareaFormatter();
	}

    init(users_list) {
        this.users_list = users_list;
    }

	show(query) {
		const existingPopup = document.querySelector(".mentions-popup");
		if (existingPopup) existingPopup.remove();

		const matches = !query
			? this.users_list
			: this.users_list.filter(user =>
				user.username.toLowerCase().startsWith(query.toLowerCase())
			);

		if (matches.length === 0) return;

		const container = document.createElement("div");
		container.className = "mentions-popup";

		matches.forEach(user => {
			const userDiv = document.createElement("div");
			userDiv.className = "mention-item";
			userDiv.innerHTML = `
				<img src="${user.profile_picture}" alt="${user.username}" class="mention-pfp" />
				<span class="mention-username">${this.sanitizer.sanitize(user.username)}</span>
			`;

			userDiv.addEventListener("click", () => {
				this.insert(user.username);
				container.remove();
			});

			container.appendChild(userDiv);
		});

		document.querySelector(".input-container-2").insertAdjacentElement("afterbegin", container);
	}

	insert(username) {
		const textarea = document.getElementById("message-input");
		const text = textarea.innerHTML;

		const lastAtIndex = text.lastIndexOf("@");
		if (lastAtIndex === -1) return;

		const beforeAt = text.slice(0, lastAtIndex);
		const afterAt = text.slice(lastAtIndex);

		const newAfterAt = afterAt.replace(
			/^@\w*/,
			`<a class="user-link" contenteditable="false" style="cursor: default;">@${this.sanitizer.sanitize(username)}</a>&nbsp;`
		);

		textarea.innerHTML = beforeAt + newAfterAt;

        this.textareaFormatter.caret_end(textarea);
	}

	hide() {
		const existingPopup = document.querySelector(".mentions-popup");
		if (existingPopup) existingPopup.remove();
	}
}