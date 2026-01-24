function initServer() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('server.js')) return;

    const serverBar = document.querySelector(".server-bar");
    if (serverBar) serverBar.classList.remove("hidden");
	
	const access_token = document.getElementById("access-token").getAttribute("value");
	const server_id = document.getElementById("server-id").getAttribute("value");
	const channel_id = document.getElementById("channel-id").getAttribute("value");
	const channel_name = document.getElementById("channel-name").getAttribute("value");
	const channel_type = document.getElementById("channel-type").getAttribute("value");

	const channels = JSON.parse(document.getElementById("channels").getAttribute("value"));
	const channel_groups = JSON.parse(document.getElementById("channel-groups").getAttribute("value"));

	const user_id = document.getElementById("user-id").getAttribute("value");

	const premium = JSON.parse(document.getElementById("premium").getAttribute("value"));

	const is_in_server = JSON.parse(document.getElementById("is-in-server").getAttribute("value"));

	document.querySelectorAll('.channel-group-name').forEach(el => {
		el.addEventListener('click', () => {
			el.parentElement.classList.toggle('expanded');
		});
	});

	document.getElementById("server-bar-item-home").classList.remove("active");
	const serverbar_servers = document.querySelectorAll('#server-bar-item-server');
	serverbar_servers.forEach(el => el.classList.remove('active'));
	serverbar_servers.forEach(el => {
		if (el.getAttribute('data-server-id') === server_id) {
			el.classList.add('active');
		}
	});

	const uploadContainer = document.querySelector(".input-container-2 .file-upload-container");

	let usersList = [];
	let replyingTo = null;

	let available_commands = [];

	let selectedFiles = [];

	const messageContainer = document.querySelector("#message-container");
	window.addEventListener('DOMContentLoaded', async () => {
		await emojis.load();
	});

	const textarea = document.getElementById("message-input");
	const preview = document.getElementById("message-input-bg");

	let typing = false;

	let offset = 0;
	const limit = 25;

	async function loadMessages(offsetValue = 0) {
		if (channel_type === "voice") {
			socket.emit("connect_to_voice_channel", {
				access_token,
				server_id,
				channel_id
			});
			return;
		}
		socket.emit("get_messages", {
			access_token,
			server_id,
			channel_id,
			offset: offsetValue
		});

		socket.emit("server_commands", {
			access_token,
			server_id
		});
		socket.emit("change_room", {
			access_token,
			server_id,
			channel_id
		});
	}

	async function preloadParentData(messages) {
		const pids = messages.map(m => m.parent_message_id).filter(Boolean);
		for (const pid of pids) {
			if (!parentCache.has(pid)) {
				try {
					const data = await loadParentMessage(pid);
					parentCache.set(pid, data);
				} catch {
					parentCache.set(pid, { parent_message_text: null, parent_message_user: null });
				}
			}
		}
	}

	loadMessages();
	socket.emit("get_server_users", {
		access_token,
		server_id,
		channel_id
	});

	const pendingMessages = [];

	async function processMessage(msg, parentData, container = messageContainer) {
		const msgEl = await createMessageElement(msg, parentData);
		if (!msgEl) return;
		container.appendChild(msgEl);
		await hydrateAssets(msgEl);
	}

	const parentCache = new Map();

	function getParentData(pid) {
		if (!pid) return { parent_message_text: null, parent_message_user: null };
		if (parentCache.has(pid)) return parentCache.get(pid);
		return { parent_message_text: null, parent_message_user: null };
	}

	messageContainer.addEventListener("scroll", () => {
		if (messageContainer.scrollTop === 0) {
			offset += limit;
			socket.emit("get_messages", {
				access_token,
				server_id,
				channel_id,
				offset
			});
		}
	});

	socket.on("all_messages", async (messages) => {
		console.log("[socket] all_messages received", messages);
		await handleAllMessages(messages);
	});

	socket.on("all_messages_nocache", async (messages) => {
		console.log("[socket] all_messages_nocache received", messages);
		await handleAllMessages(messages);
	});

	const messageCache = []; // stores messages by {id, timestamp, el}

	async function handleAllMessages(messages) {
		console.log("[handleAllMessages] start");

		if (!Array.isArray(messages)) {
			console.warn("[handleAllMessages] messages is not an array", messages);
			return;
		}

		for (const msg of messages) {
			console.log("[handleAllMessages] processing message", msg);

			const timestamp = msg.created_at;

			const existingIndex = messageCache.findIndex(m => m.id === msg.id); // find message in cache
			if (existingIndex !== -1) {
				const existing = messageCache[existingIndex];
				existing.el.remove();
				messageCache.splice(existingIndex, 1);
				console.warn("[handleAllMessages] existing message removed", msg.id);
			}

			const el = await createMessageElement(msg);
			if (!el) {
				console.warn("[handleAllMessages] createMessageElement returned null", msg);
				continue;
			}

			let insertIndex = messageCache.findIndex(m => timestamp < m.timestamp); // find position in cache
			if (insertIndex === -1) insertIndex = messageCache.length;

			// store scroll state before inserting
			const scrollTopBefore = messageContainer.scrollTop;
			const scrollHeightBefore = messageContainer.scrollHeight;
			const nearBottom = scrollHeightBefore - scrollTopBefore - messageContainer.clientHeight <= 10;

			messageCache.splice(insertIndex, 0, { id: msg.id, timestamp, el }); // add message to cache

			// insert into DOM at the same position
			if (insertIndex === messageContainer.children.length) {
				messageContainer.appendChild(el);
				if (insertIndex > 0) {
					const prevTimestamp = messageCache[insertIndex - 1].timestamp;
					console.log(`[handleAllMessages] message appended after ${prevTimestamp} (later)`, msg.id);
				} else {
					console.log("[handleAllMessages] message appended as first message", msg.id);
				}
			} else {
				messageContainer.insertBefore(el, messageContainer.children[insertIndex]);
				const prevTimestamp = messageCache[insertIndex - 1]?.timestamp;
				const nextTimestamp = messageCache[insertIndex + 1]?.timestamp || "none";
				if (prevTimestamp && timestamp > prevTimestamp) {
					console.log(`[handleAllMessages] message inserted after ${prevTimestamp} (later) before ${nextTimestamp}`, msg.id);
				} else if (nextTimestamp && timestamp < nextTimestamp) {
					console.log(`[handleAllMessages] message inserted before ${nextTimestamp} (earlier)`, msg.id);
				} else {
					console.log(`[handleAllMessages] message inserted at position ${insertIndex}`, msg.id);
				}
			}

			// adjust scrollTop to preserve visual position if not near bottom
			if (!nearBottom) {
				const scrollHeightAfter = messageContainer.scrollHeight;
				messageContainer.scrollTop = scrollTopBefore + (scrollHeightAfter - scrollHeightBefore);
			} else {
				// scroll to bottom if near bottom
				requestAnimationFrame(() => {
					messageContainer.scrollTop = messageContainer.scrollHeight;
				});
			}
		}

		console.log("[handleAllMessages] done");
	}

	async function createMessageElement({ message, created_at, id: message_id, bot_message, sender_info, embed }) {
		console.log("[createMessageElement] start", sender_info.username, message);

		if (!message && !embed) {
			console.warn("[createMessageElement] message empty", sender_info.username);
			return null;
		}

		const sanitizedUsername = sender_info.display_name ? sanitize(sender_info.display_name) : sanitize(sender_info.username); // sanitize the username
		const sanitizedMessage = await sanitizeMsg(message, usersList, user_id, channels, server_id); // sanitize the message
		const createdAtDate = new Date(created_at); // create a date object

		let embedsRaw = null; // create a variable to store the embed
		try {
			embedsRaw = typeof embed === 'string' ? JSON.parse(embed) : embed; // parse the embed
		} catch {
			embedsRaw = null; // if the embed is invalid, set it to null
		}

		const embeds = Array.isArray(embedsRaw) // check if the embed is an array
			? embedsRaw
			: embedsRaw
				? [embedsRaw]
				: null;

		const msgEl = document.createElement("div"); // Create a new message element
		msgEl.classList.add("message"); // add the message class
		msgEl.dataset.timestamp = created_at; // set the timestamp
		msgEl.dataset.messageId = message_id; // set the message id

		msgEl.innerHTML = `
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
				</div>
			</div>
		`; // set the message text

		console.log("[createMessageElement] created element", sanitizedUsername);

		return msgEl; // return the message element
	}

	async function Embed({ embed }) {
		if (!embed) return '';

		const bot_id = embed.bot_id || '';

		return `
			<div class="embed" data-bot-id="${bot_id}" style="border-left: 4px solid ${embed.color || 'var(--clr-primary-a0)'};">
			${embed.title ? `<h3 class="embed-title">${sanitize(embed.title)}</h3>` : ''}
			${embed.description ? `<p class="embed-description">${await sanitizeMsg(embed.description, usersList, user_id, channels, server_id)}</p>` : ''}
			
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

	async function Embeds({ embeds }) {
		if (!embeds?.length) return '';
		const embedHtml = await Promise.all(embeds.map(e => Embed({ embed: e })));
		return `<div class="embeds-container">${embedHtml.join('')}</div>`;
	}

	socket.on("server_commands_response", async (data) => {
		if (data.success) {
			available_commands = data.bots;
		}
	});

	socket.on("new_message", async (msg) => {

		if (msg.channel_id !== channel_id) return;

		if (channel_type === "voice") {
			return;
		}

		const isAtBottom = (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) < 5;

		let parent_message_text_2 = null;
		let parent_message_user_2 = null;

		if (msg.parent_message_id) {
			const { parent_message_text, parent_message_user } = await loadParentMessage(msg.parent_message_id);
			if (parent_message_text && parent_message_user) {
			parent_message_text_2 = sanitize(parent_message_text);
			parent_message_user_2 = sanitize(parent_message_user);
			}
		}

		const parentInfo = { parent_message_text: parent_message_text_2, parent_message_user: parent_message_user_2 };
		const msgEl = await createMessageElement(msg, parentInfo);

		messageContainer.appendChild(msgEl);
		normalizeCompactMessages(messageContainer);

		lastRenderedUser = sanitize(msg.username);
		lastRenderedTimestamp = new Date(msg.created_at);

		hljs.highlightAll();

		document.querySelectorAll(".message-text").forEach(msgText => {
			msgText.querySelectorAll("pre").forEach(pre => {
			if (!pre.querySelector(".lang-bar")) {
				const codeEl = pre.querySelector("code");
				let lang = "bash";
				if (codeEl) {
				const langClass = [...codeEl.classList].find(c => c.startsWith("lang-"));
				if (langClass) {
					lang = langClass.slice(5);
				}
				}
				
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
				if (!codeEl) return;
				const codeText = codeEl.innerText;

				navigator.clipboard.writeText(codeText).then(() => {
					copyIcon.textContent = "check";

					setTimeout(() => {
					copyIcon.textContent = "content_copy";
					}, 3000);
				}).catch(() => {
					Toastify({
					text: "Unable to copy code",
					className: "copy-code-failed",
					duration: 3000,
					close: true,
					gravity: "bottom",
					position: "right",
					style: {
						background: "var(--clr-error-a0)",
						boxShadow: "none",
						borderRadius: "12px"
					}
					}).showToast();
				});
				});
			}
			});
		});

		if (isAtBottom) {
			messageContainer.scrollTop = messageContainer.scrollHeight - messageContainer.clientHeight;

		}
		
		emojis.replaceEl(msgEl.querySelector(".message-text"));
	});

	function normalizeCompactMessages(container) {
		const messages = [...container.querySelectorAll(".message")];

		for (let i = 0; i < messages.length; i++) {
			const current = messages[i];
			const prev = messages[i - 1];

			current.classList.remove("compact");

			const pic = current.querySelector(".profile-picture");
			const header = current.querySelector(".username-date");

			if (!prev) {
				pic.style.opacity = "1";
				pic.style.height = "30px";
				header.style.display = "flex";
				continue;
			}

			const sameUser = current.dataset.sentBy === prev.dataset.sentBy;

			const currentTime = new Date(
				current.querySelector(".date")?.dataset.timestamp
			);
			const prevTime = new Date(
				prev.querySelector(".date")?.dataset.timestamp
			);

			const closeInTime = Math.abs(currentTime - prevTime) < 2 * 60 * 1000;

			if (sameUser && closeInTime) {
				current.classList.add("compact");
				pic.style.opacity = "0";
				pic.style.height = "0";
				header.style.display = "none";
			} else {
				pic.style.opacity = "1";
				pic.style.height = "30px";
				header.style.display = "flex";
			}
		}
	}

	async function onUsersListLoaded() {
		pendingMessages.sort((a, b) => new Date(a.msg.created_at) - new Date(b.msg.created_at));

		const processed = new Set();

		while (pendingMessages.length > 0) {
			let anyProcessed = false;

			for (let i = 0; i < pendingMessages.length; i++) {
				const { msg } = pendingMessages[i];

				const parentData = msg.parent_message_id
					? await getParentData(msg.parent_message_id)
					: { parent_message_text: null, parent_message_user: null };

				const existingEl = messageContainer.querySelector(`.message[data-message-id="${msg.id}"]`);
				if (existingEl) {
					const newWrapperEl = await createMessageElement(msg, parentData);
					if (newWrapperEl) {
						const newMsgEl = newWrapperEl.querySelector('.message');
						if (newMsgEl) existingEl.replaceWith(newMsgEl);
					}
				} else if (!msg.parent_message_id || processed.has(msg.parent_message_id)) {
					await processMessage(msg, parentData);
				}

				processed.add(msg.id);
				pendingMessages.splice(i, 1);
				i--;
				anyProcessed = true;
			}

			if (!anyProcessed) break;
		}
	}

	socket.on("livekit_token", async ({ token, url, room: roomName }) => {
		await openParticipantsPopup(token, roomName);
	});

	let popupWindow = localStorage.getItem("popupWindow") ?? null;

	async function openParticipantsPopup(token, roomName) {
	if (!popupWindow || popupWindow.closed || popupWindow === "null") {
		popupWindow = window.open("", `${channel_name}`, "width=400,height=600");
		localStorage.setItem("popupWindow", popupWindow);

		popupWindow.document.write(`
		<html lang="en" class="dark">
			<head>
			<title>${channel_name}</title>
			<script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
			<link rel="stylesheet" href="/assets/styles/main.css" />
			<script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
			<link
				href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
				rel="stylesheet"
			/>
			</head>
			<body>
			<div class="participants">

			</div>
			<div class="call-options">
				<div class="call-option-group">
					<div class="call-option" id="call-option-mic">
						<span class="material-symbols-rounded call-option-icon">mic</span>
					</div>
					<div class="call-option" id="call-option-video">
						<span class="material-symbols-rounded call-option-icon">videocam_off</span>
					</div>
				</div>
				<div class="call-hangup">
					<span class="material-symbols-rounded call-option-icon">call_end</span>
				</div>
			</div>
			<script>
				const server_id = "${server_id}";
				const channel_id = "${channel_id}";
				const access_token = "${access_token}";

				const socket = io("https://chat.wokki20.nl", {
					path: "/socket.io",
					transports: ["websocket"],
					query: {
					access_token: access_token
					},
				});

				socket.emit("get_server_users", {
				access_token,
				server_id,
				channel_id
				});

				const token = "${token}";
				const roomName = "${roomName}";
				window.addEventListener('load', async () => {
					await joinLiveKitRoom(token, roomName);
				})
				let usersList = [];
				socket.on("server_users", (users) => {
					usersList = users;
				})
			</script>
			<script src="/assets/js/popupCallLogic.js"></script>
			<script src="/assets/js/globalFunctions.js"></script>
			</body>
		</html>
		`);
		popupWindow.document.close();

		const checkPopupClosed = setInterval(() => {
		if (popupWindow.closed) {
			clearInterval(checkPopupClosed);
			localStorage.removeItem("popupWindow");
			window.location.href = `/server/${server_id}`;
		}
		}, 1);
	}
	}



	socket.on("message_deleted", (message_id) => {
		deleteMsg(message_id);
	});

	const minHeight = 18;

	function renderPreviews() {
		uploadContainer.innerHTML = '';

		const audioIcon = `<span class="material-symbols-rounded audio-icon-upload">music_note</span>`;
		const textIcon = `<span class="material-symbols-rounded text-icon-upload">description</span>`;

		selectedFiles.forEach(({ file, savedName, originalName }, index) => {
		const fileType = file.type || '';
		let previewHTML = '';

		if (fileType.startsWith('image/')) {
			const url = URL.createObjectURL(file);
			previewHTML = `<div class="upload-preview"><img src="${url}" alt="preview" class="image-preview" /></div>`;
		} else if (fileType.startsWith('video/')) {
			const url = URL.createObjectURL(file);
			previewHTML = `<div class="upload-preview"><video src="${url}" class="video-preview" muted pause loop></video></div>`;
		} else if (fileType.startsWith('audio/')) {
			previewHTML = `<div class="upload-preview">${audioIcon}</div>`;
		} else if (fileType === 'text/plain') {
			previewHTML = `<div class="upload-preview">${textIcon}</div>`;
		} else {
			previewHTML = `<div class="upload-preview">${textIcon}</div>`;
		}

		const fileBlock = document.createElement('div');
		fileBlock.classList.add('file-preview');

		fileBlock.innerHTML = `
			${previewHTML}
			<div class="file-name">${file.name}</div>
			<div class="file-options">
			<span class="material-symbols-rounded file-remove-icon" style="cursor:pointer;">delete</span>
			</div>
		`;

		fileBlock.querySelector('.file-remove-icon').addEventListener('click', async () => {
			const removed = selectedFiles.splice(index, 1)[0];
			if (removed.savedName) {
			try {
				const formData = new FormData();
				formData.append('savedName', removed.savedName);

				await fetch('https://chat.wokki20.nl/app/delete_file', {
				method: 'POST',
				headers: {
					"Authorization": `Bearer ${access_token}`
				},
				body: formData
				});
			} catch (err) {
				console.error("Failed to delete file from server", err);
			}
			}
			renderPreviews();
		});

		uploadContainer.appendChild(fileBlock);
		});
	}

	if (textarea) {
		const maxHeight = 250;
		const warningThreshold = 1000;
		const maxChars = premium ? 10000 : 3000;

		const messageInputWrapper = document.querySelector('.message-input-wrapper');

		const maxMessageLengthEl = document.querySelector('.max-message-length');
		const maxCharactersLeftEl = document.querySelector('.max-characters-left');

		document.querySelector(".input-container-2").addEventListener("click", () => textarea.focus());
		
		const inputContainer = document.querySelector(".input-container-2");

		const updateHeight = () => {
			let newHeight = Math.min(textarea.scrollHeight, maxHeight);
			messageInputWrapper.style.height = newHeight + 20 + 'px';
			inputContainer.style.minHeight = newHeight + 20 + 'px';
		};

		const updateTypingStatus = () => {
			const value = textarea.innerText.trim();
			if (value.length && !typing) {
			typing = true;
			socket.emit('typing', { access_token, typing: true, channel_id, server_id });
			} else if (!value.length && typing) {
			typing = false;
			socket.emit('typing', { access_token, typing: false, channel_id, server_id });
			}
		};

		const updateCharsLeft = () => {
			const charsLeft = maxChars - textarea.innerText.length;
			if (charsLeft <= warningThreshold) {
			maxMessageLengthEl.style.display = 'flex';
			maxCharactersLeftEl.textContent = charsLeft;
			maxCharactersLeftEl.classList.toggle('debt', charsLeft < 0);
			} else {
			maxMessageLengthEl.style.display = 'none';
			maxCharactersLeftEl.classList.remove('debt');
			}
		};

		const handleCommandAutocomplete = () => {
			if (textarea.innerText.startsWith('/')) {
			textarea.style.color = "var(--clr-text-a0)";
			preview.style.display = "none";
			showAvailableCommands(textarea.innerText, textarea, available_commands);
			} else {
			const popup = document.querySelector(".available-commands");
			if (popup) popup.remove();
			updateHeight();
			}
		};

		const handleMentions = () => {
			const text = textarea.innerHTML.trim();
			if (text.includes('@')) {
			const lastAtIndex = text.lastIndexOf("@");
			placeCaretAtEnd(textarea);
			const afterAt = text.slice(lastAtIndex + 1);
			const query = afterAt.split(/\s|\n/)[0];
			textarea.style.color = "var(--clr-text-a0)";
			preview.style.display = "none";
			show_mentions(query, usersList);
			} else {
			hide_mentions();
			textarea.style.color = "transparent";
			preview.style.display = "block";
			}
		};

		textarea.addEventListener('input', (e) => {
			if (e.target !== textarea) return;

			if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
			textarea.innerHTML = '';
			hide_mentions();
			}

			updateHeight();
			updateTypingStatus();
			updateCharsLeft();
			handleCommandAutocomplete();
			handleMentions();

			preview.innerHTML = renderMarkdownInTextarea(textarea.innerText);
		});

		textarea.addEventListener('keydown', async (e) => {
			const text = textarea.innerText.trim();

			if (e.key === "Enter" && !e.shiftKey) {
			e.preventDefault();

			if (text.startsWith("/")) return;

			const uploadedNames = selectedFiles
				.filter(f => f.savedName)
				.map(f => ({ savedName: f.savedName, originalName: f.originalName }));

			send_message(textarea, uploadedNames.length ? uploadedNames : undefined);
			hide_mentions();
			
			preview.innerHTML = "";
			textarea.innerText = "";
			updateHeight();
			renderPreviews();

			if (typing) {
				typing = false;
				socket.emit('typing', { access_token, typing: false, channel_id, server_id });
			}
			return;
			}

			if (e.key === ' ' && text.startsWith('/')) {
			const matches = showAvailableCommands.lastMatches;
			if (matches?.length === 1) {
				textarea.innerText = matches[0].command + " ";
				const popup = document.querySelector(".available-commands");
				if (popup) popup.remove();
				e.preventDefault();
			}
			}

			if (e.key === 'Enter') {
			const matches = showAvailableCommands.lastMatches;
			const match = matches?.find(m => m.command.toLowerCase() === text.toLowerCase());
			if (match) {
				e.preventDefault();
				socket.emit("command", {
				access_token,
				command: match.command,
				server_id,
				channel_id,
				bot_id: match.bot_id
				});
				textarea.innerText = "";
				preview.innerHTML = "";
				const popup = document.querySelector(".available-commands");
				if (popup) popup.remove();
			}
			}
		});

		textarea.addEventListener('blur', () => {
			if (typing) {
			typing = false;
			socket.emit('typing', { access_token, typing: false, channel_id, server_id });
			}
		});

		textarea.addEventListener('input', (e) => {
			if (e.target !== textarea) return;

			if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
			textarea.innerHTML = '';
			hide_mentions();
			}

			updateHeight();
			updateTypingStatus();
			updateCharsLeft();
			handleCommandAutocomplete();
			handleMentions();

			preview.innerHTML = renderMarkdownInTextarea(textarea.innerText);
		});

		textarea.addEventListener('paste', (e) => {
			e.preventDefault();

			const text = e.clipboardData.getData('text/plain');

			const selection = window.getSelection();
			if (!selection.rangeCount) return;
			selection.deleteFromDocument();
			selection.getRangeAt(0).insertNode(document.createTextNode(text));

			selection.collapseToEnd();

			textarea.dispatchEvent(new Event('input'));
		});

	}

	async function hydrateAssets(msgEl) {
		const lazyImages = Array.from(msgEl.querySelectorAll('img.lazyload'));
		const lazyVideos = Array.from(msgEl.querySelectorAll('video.lazyload'));

		await Promise.all(
			lazyImages.map(img => new Promise(resolve => {
				const tempImg = new Image();
				tempImg.src = img.dataset.src;
				tempImg.onload = () => {
					img.src = img.dataset.src;
					img.classList.remove('lazyload');
					img.style.opacity = '0';
					requestAnimationFrame(() => {
						img.style.transition = 'opacity 0.3s';
						img.style.opacity = '1';
					});
					resolve();
				};
				tempImg.onerror = resolve;
			}))
		);

		await Promise.all(
			lazyVideos.map(video => new Promise(resolve => {
				video.src = video.dataset.src;
				video.load();
				video.classList.remove('lazyload');
				video.onloadeddata = resolve;
				video.onerror = resolve;
			}))
		);
	}

	let lastRenderedUser = null;
	let lastRenderedTimestamp = null;

	async function old_createMessageElement({ username, message, created_at, sent_by, id, channel_id: channel_id_2, parent_message_id, assets, profile_picture, bot_message, command, command_user_id, embed, display_name }, parentData) {
		const sanitizedUsername = display_name ? sanitize(display_name) : sanitize(username);
		const sanitizedMessage = await sanitizeMsg(message, usersList, user_id, channels, server_id);
		const createdAtDate = new Date(created_at);

		if (channel_id_2 !== channel_id) return null;

		const isPremium = usersList.find(user => user.id === String(sent_by))?.premium ?? false;
		const isStaff = usersList.find(user => user.id === String(sent_by))?.staff ?? false;
		const isAtBottom = (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) < 5;

		let hideHeader = false;
		if (
			lastRenderedUser === sanitizedUsername &&
			lastRenderedTimestamp &&
			(createdAtDate - lastRenderedTimestamp) < 10 * 60 * 1000
		) {
			hideHeader = true;
		}

		let assetsHTML = '';
		if (assets && assets.length > 0) {
			assetsHTML = assets.map((asset, index) => {
			const type = getAssetType(asset.savedName);
			if (type === 'image') {
				return `<img data-src="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" alt="${asset.originalName}" class="message-asset-image lazyload" onclick="imageViewer('https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}', '${asset.originalName}')" />`;
			} else if (type === 'video') {
				return `<video data-src="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" controls class="message-asset-video lazyload"></video>`;
			} else if (type === 'audio') {
				return `
				<div class="custom-player" data-originalName="${asset.originalName}" data-src="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}">
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
				return `<a href="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" target="_blank" class="message-asset-pdf link">${sanitize(asset.originalName)}</a>`;
			} else if (type === 'txt') {
				return `<pre class="message-asset-text" id="txt-asset-${id}-${index}"><div class="lang-bar"><p>Plaintext</p><span class="material-symbols-rounded">content_copy</span></div><code class="lang-plaintext">Loading...</code></pre>`;
			} else if (type === 'profile_picture') {
				return `<img src="https://chat.wokki20.nl/uploads/profile-pictures/${encodeURIComponent(asset.savedName.slice(0, -4))}" alt="${asset.originalName}" class="message-asset-image message-asset-profile-picture" onclick="imageViewer('https://chat.wokki20.nl/uploads/profile-pictures/${encodeURIComponent(asset.savedName.slice(0, -4))}', '${asset.originalName}')" />`;
			} else {
				return `<a href="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" download class="message-asset-file link">${sanitize(asset.savedName)}</a>`;
			}
			}).join('');
		}

		const parent_message_text_2 = parentData?.parent_message_text ? sanitize(parentData.parent_message_text).slice(0, 100) + (parentData.parent_message_text.length > 100 ? '...' : '') : null;
		const parent_message_user_2 = parentData?.parent_message_user ? sanitize(parentData.parent_message_user) : null;

		const username_command = command_user_id ? sanitize(usersList.find(user => user.id === String(command_user_id))?.username ?? '') : null;
		const msgEl = document.createElement("div");
		msgEl.classList.add('message');
		msgEl.setAttribute('data-message-id', id);
		msgEl.setAttribute('data-sent-by', sent_by);
		msgEl.innerHTML = `
			${
				parent_message_id
				? `<div class="message-reply" data-message-id="${parent_message_id}">
					<img class="identification" src="/assets/images/identifier.svg">
					<p class="username-reply">@${parent_message_user_2 ?? ''}</p>
					<p class="message-text-reply">${parent_message_text_2 ?? ''}</p>
					</div>`
				: ''
			}
			${
				command !== null && command && command_user_id !== null && command_user_id
				? `<div class="message-command">
					<img class="identification" src="/assets/images/identifier.svg">
					<p class="username-command">@${username_command ?? ''}</p>
					<p>used</p>
					<p class="used-command">${command ?? ''}</p>
					</div>`
				: ''
			}
			<div class="message-content">
				<img class="profile-picture" src="${profile_picture}" style="opacity: ${hideHeader && !parent_message_id && !command ? '0' : '1'}; height: ${hideHeader && !parent_message_id && !command ? '0' : '30px'};" />
				<div class="name-text">
					<div class="username-date" style="display: ${hideHeader && !parent_message_id && !command ? 'none' : 'flex'};">
					<p class="username" id="username">${sanitizedUsername}</p>
					${isPremium ? '<div class="premium-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_premium.svg"><p class="premium-tag-tooltip">Premium</p></div>' : ''}
					${isStaff ? '<div class="staff-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_staff.svg"><p class="staff-tag-tooltip">Staff</p></div>' : ''}
					${bot_message == 1 ? '<div class="bot-tag"><span class="material-symbols-rounded">check</span>BOT</div>' : ''}
					<p class="date" data-timestamp="${created_at}">${formatDate(created_at)}</p>
					</div>
				<div class="message-text">${sanitizedMessage}</div>
				${assetsHTML ? `<div class="message-assets">${assetsHTML}</div>` : ''}
				${embeds ? `<div class="message-embed">${await Embeds({ embeds })}</div>` : ''}

				</div>
			</div>
			<div class="message-options">
				<div class="message-option" id="reply-btn">
				<span class="material-symbols-rounded">reply</span>
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

		msgEl.querySelector('.username').addEventListener('click', (e) => {
			e.stopPropagation();
			scrollToUser(sent_by);
		})

		function scrollToUser(user) {
			const userInfoProfile = document.querySelector(`.info-profile[data-user-id="${user}"]`);
			if (userInfoProfile) {
				if (userInfoProfile.offsetParent !== null) {
					userInfoProfile.scrollIntoView({ behavior: "smooth", block: "center" });
					requestAnimationFrame(() => {
						if (userInfoProfile.getBoundingClientRect().top > 0) {
							userInfoProfile.click();
						}
					});
				} else {
					userInfoProfile.click();
				}
			}
		}

		hydrateInvites(msgEl);
		hydrateSpotifyTracks(msgEl);

		const msgWrapper = msgEl;
		const mentionTags = msgEl.querySelectorAll(`.user-link[data-user-id="${user_id}"], .user-link[data-user-id="everyone"]`);

		if (mentionTags.length > 0) {
			msgWrapper.classList.add("mentioned");
		}

		if (assets && assets.length > 0) {
			const txtPromises = assets.map(async (asset, index) => {
				if (getAssetType(asset.savedName) !== 'txt') return;

				const preEl = msgEl.querySelector(`#txt-asset-${id}-${index} code`);
				if (!preEl) return;

				try {
					const contents = await getAssetFileInsides(asset.savedName);
					preEl.textContent = sanitize(contents);

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
								setTimeout(() => {
									copyIcon.textContent = "content_copy";
								}, 3000);
							});
						});
					}
				} catch {
					preEl.textContent = '[Failed to load file]';
				}
			});

			await Promise.all(txtPromises);
		}

		const messageReplyEl = msgEl.querySelector(".message-reply");
		if (messageReplyEl) {
			messageReplyEl.style.cursor = "pointer";
			messageReplyEl.addEventListener("click", () => {
			const targetId = messageReplyEl.getAttribute("data-message-id");
			if (!targetId) return;

			const targetMsg = messageContainer.querySelector(`.message[data-message-id="${targetId}"]`);
			if (targetMsg) {
				targetMsg.scrollIntoView({ behavior: "smooth", block: "center" });
				targetMsg.classList.add("highlight-parent-msg");
				setTimeout(() => {
					targetMsg.classList.remove("highlight-parent-msg");
				}, 2000);
			}
			});
		}

		const customPlayer = msgEl.querySelector(".custom-player");

		if (customPlayer) {
			const audioSrc = customPlayer.dataset.src;
			const originalName = customPlayer.dataset.originalname;
			const audio = new Audio(customPlayer.dataset.src);
			const playPauseBtn = customPlayer.querySelector('.play-pause');
			const seekBar = customPlayer.querySelector('.seek-bar');
			const currentTimeEl = customPlayer.querySelector('.current-time');
			const durationEl = customPlayer.querySelector('.duration');
			const timeBox = customPlayer.querySelector('.time-left-current');
			const downloadBtn = customPlayer.querySelector('#download');

			let updateInterval;

			function updateSeekBarProgress() {
				const val = seekBar.value;
				const max = seekBar.max || 100;
				const percentage = (val / max) * 100;
				seekBar.style.background = `
					linear-gradient(
					to right,
					var(--clr-primary-a0) 0%,
					var(--clr-primary-a0) ${percentage}%,
					var(--clr-input-border-bg-dark) ${percentage}%,
					var(--clr-input-border-bg-dark) 100%
					)
				`;
			}

			playPauseBtn.addEventListener('click', () => {
				if (audio.paused) {
					audio.play();
					playPauseBtn.textContent = 'pause';

					updateInterval = setInterval(() => {
						seekBar.value = audio.currentTime;
						currentTimeEl.textContent = formatTime(audio.currentTime);
						updateSeekBarProgress();
					}, 500);
				} else {
					audio.pause();
					playPauseBtn.textContent = 'play_arrow';
					clearInterval(updateInterval);
				}
			});

			downloadBtn.addEventListener('click', () => {
				const a = document.createElement('a');
				a.href = audioSrc;
				a.download = originalName;
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			});


			requestAnimationFrame(() => {
				const width = timeBox.offsetWidth;
				timeBox.style.minWidth = `${width}px`;
			});

			audio.addEventListener('loadedmetadata', () => {
				seekBar.max = audio.duration;
				durationEl.textContent = `/ ${formatTime(audio.duration)}`;
				updateSeekBarProgress();
			});

			seekBar.addEventListener('input', () => {
				audio.currentTime = seekBar.value;
				currentTimeEl.textContent = formatTime(audio.currentTime);
				updateSeekBarProgress();
			});

			function formatTime(seconds) {
				const mins = Math.floor(seconds / 60);
				const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
				return `${mins}:${secs}`;
			}
		}


		lastRenderedUser = sanitizedUsername;
		lastRenderedTimestamp = createdAtDate;

		const replyBtn = msgEl.querySelector("#reply-btn");
		replyBtn.addEventListener("click", () => {
			replyMessage(id);
		});

		const deleteBtn = msgEl.querySelector("#delete-btn");
		if (deleteBtn) {
			deleteBtn.addEventListener("click", () => {

			socket.emit("delete_message", { access_token, message_id: id, server_id, channel_id });
				deleteMsg(id);
			});
		}

		const embedContainer = msgEl.querySelector('.message-embed');

		if (embedContainer) {
			embedContainer.addEventListener('click', (event) => {
				const btn = event.target.closest('button[data-btn-id]');
				if (!btn) return;

				const embedDiv = btn.closest('.embed');
				if (!embedDiv) return;

				const bot_id = embedDiv.dataset.botId;
				const btn_id = btn.dataset.btnId;

				socket.emit('embed_button', {
					bot_id,
					button_id: btn_id,
					access_token,
					server_id,
					channel_id
				});
			});
		}
		
		setTimeout(() => {
			hljs.highlightAll();
		}, 0);
		return msgEl;
	}

	async function getAssetFileInsides(file) {
		return fetch(`https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(file)}`)
			.then(response => response.blob())
			.then(blob => {
			return new Promise((resolve, reject) => {
				const reader = new FileReader();
				reader.onload = () => resolve(reader.result);
				reader.onerror = () => reject("Failed to read file");
				reader.readAsText(blob);
			});
			});
	}

	function deleteMsg(id) {
		const msgEl = document.querySelector(`.message[data-message-id="${id}"]`);
		if (!msgEl) return;

		const nextMsgEl = msgEl.nextElementSibling;

		msgEl.remove();

		if (nextMsgEl && nextMsgEl.classList.contains('message')) {
			const usernameDateEl = nextMsgEl.querySelector('.username-date');
			if (usernameDateEl) {
				usernameDateEl.style.display = 'flex';
			}
			const profilePic = nextMsgEl.querySelector('.profile-picture');
			if (profilePic) {
				profilePic.style.opacity = '1';
				profilePic.style.height = '30px';
			}
		}
	}

	function send_message(textareaEl, uploadedFileNames = null) {
		const message = getCleanMessageFromTextarea(textareaEl);

		if (!message) return;

		if (channel_type === "voice") {
			return;
		}

		const payload = {
			access_token,
			message,
			server_id,
			channel_id,
		};

		if (replyingTo !== null && replyingTo !== undefined) {
			payload.parent_message_id = replyingTo;
			
		}

		if (uploadedFileNames !== null && uploadedFileNames !== undefined) {
			payload.file_names = uploadedFileNames;
		}

		if (message !== '\u200B') socket.emit("send_message", payload);
		textareaEl.style.minHeight = minHeight + 'px';
		textareaEl.innerText = "";
		replyingTo = null;
		if (document.querySelector(".replying-to")) {
			document.querySelector(".replying-to").remove();

		}
		if (typing) {
			typing = false;
			socket.emit('typing', { access_token: access_token, typing: false, channel_id: channel_id, server_id: server_id });
		}

		uploadContainer.innerHTML = '';
		selectedFiles = [];
	}

	socket.on("send_message_response", (resp) => {
		if (!resp.success) {
			console.error("Failed to send message:", resp.error);

			if (resp.error?.includes("Rate limit exceeded")) {
			Toastify({
				text: "Please wait before sending your next message",
				duration: 3000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
				}
			}).showToast();
			}
		}
	});

	socket.on("connect_error", (err) => console.error("Connection error:", err));
	socket.on("error", (err) => console.error("Socket error:", err));
	socket.on("disconnect", (reason) => console.warn("Socket disconnected:", reason));

	socket.on("update_message", async ({id, message, embed, updated_at}) => {
		const messageEl = document.querySelector(`.message[data-message-id="${id}"]`);
		if (!messageEl) return;
		
		if (message) {
			messageEl.querySelector(".message-text").innerHTML = await sanitizeMsg(message, usersList, user_id, channels, server_id);
			hydrateInvites(messageEl);
			hydrateSpotifyTracks(messageEl);
		}
		if (embed) {
			messageEl.querySelector(".embeds-container").innerHTML = await Embeds({ embeds: embed });
		}
	});

	const typingUsers = new Set();

	let dots = 0;
	let dotsDirection = 1;

	socket.on("users_typing", ({ user_ids, channel_id: channel_id_2, server_id: server_id_2 }) => {
		typingUsers.clear();

		user_ids.forEach(id => {
			if (String(id) === user_id) return;

			if (channel_id_2 !== null && channel_id_2 !== channel_id) return;

			if (server_id_2 !== null && server_id_2 !== server_id) return;

			typingUsers.add(id);
		});


		const names = Array.from(typingUsers).map(id => {
			return usersList.find(user => user.id == id)?.username ?? "Someone";
		});

		let baseText = "";
		if (names.length === 0) {
			baseText = "";
		} else if (names.length === 1) {
			baseText = `${names[0]} is typing`;
		} else if (names.length === 2) {
			baseText = `${names[0]} and ${names[1]} are typing`;
		} else {
			baseText = `${names.slice(0, -1).join(", ")}, and ${names.slice(-1)} are typing`;
		}

		const indicator = document.querySelector(".typing-indicator");
		if (!indicator) return;
		if (baseText === "") {
			indicator.innerText = "";
			clearInterval(indicator._dotInterval);
			return;
		}

		if (indicator._dotInterval) clearInterval(indicator._dotInterval);

		dots = 0;
		dotsDirection = 1;
		indicator.innerText = baseText;

		indicator._dotInterval = setInterval(() => {
			let dotStr = "";
			for (let i = 0; i < dots; i++) dotStr += ".";

			indicator.innerText = baseText + dotStr;

			dots += dotsDirection;
			if (dots === 3 || dots === 0) dotsDirection *= -1;
		}, 300);
	});

	async function loadParentMessage(parent_message_id) {
		if (!parent_message_id) return { parent_message_text: "message deleted", parent_message_user: "deleted" };

		try {
			const parentMessageInfo = await getMessageById(parent_message_id);
			if (!parentMessageInfo) {
			return { parent_message_text: "message deleted", parent_message_user: "deleted" };
			}

			const parent_message_text = parentMessageInfo.message;
			const parent_message_user = usersList.find(user => user.id == parentMessageInfo.sent_by)?.username ?? "Someone";

			return { parent_message_text, parent_message_user };

		} catch (err) {
			console.error("Failed to get parent message:", err);
			return { parent_message_text: "message deleted", parent_message_user: "deleted" };
		}
	}

	function getMessageById(id) {
		return new Promise((resolve, reject) => {
			if (!id) return resolve(null);

			socket.emit("get_message_by_id", { access_token, message_id: id, server_id, channel_id });

			function handler(message) {
			if (message === null || message === undefined) {
				socket.off("message_by_id", handler);
				resolve(null);
				return;
			}

			socket.off("message_by_id", handler);
			resolve(message);
			}

			socket.on("message_by_id", handler);

			setTimeout(() => {
			socket.off("message_by_id", handler);
			reject(new Error("Timeout getting message_by_id"));
			}, 5000);
		});
	}

	const onlineUsersEl = document.getElementById("online-users");
	const offlineUsersEl = document.getElementById("offline-users");

	async function renderUser(user) {
		const userListContainer = document.querySelector(".users");

		userListContainer.querySelectorAll(`[data-user-id="${user.id}"]`).forEach(el => el.remove());

		const userEl = document.createElement("div");
		userEl.classList.add("info-profile");
		userEl.setAttribute("data-user-id", user.id);
		userEl.innerHTML = `
			<div class="self-info-profile-status" data-user-id="${user.id}">
				<img class="self-info-profile-picture" src="${user.profile_picture}" />
				<div class="self-info-status-circle-outer">
					<div class="self-info-status-circle-inner ${user.status}"></div>
				</div>
			</div>
			<div class="self-info-status-username">
				<div class="self-info-profile-username-container"><p class="self-info-username">${user.display_name ? sanitize(user.display_name) : sanitize(user.username)}</p>
					${user.bot ? '<div class="bot-tag"><span class="material-symbols-rounded">check</span>BOT</div>' : ''}
					</div>
				<p class="self-info-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
			</div>
		`;

		if (user.status === "offline") {
			offlineUsersEl.appendChild(userEl);
		} else {
			onlineUsersEl.appendChild(userEl);
		}

		userEl.onclick = async(event) => {
			event.stopPropagation();
			const allUserEls = document.querySelectorAll(".info-profile");
			allUserEls.forEach(el => el.classList.remove("active"));
			
			userEl.classList.add("active");

			document.querySelector(".user-info-profile-popup")?.remove();

			let popup = document.createElement("div");
			popup.className = "user-info-profile-popup";
			let lightText = false;
			if (user.profile_color_primary && user.profile_color_accent) {
				let color = user.profile_color_primary;
				let r = parseInt(color.slice(1,3),16);
				let g = parseInt(color.slice(3,5),16);
				let b = parseInt(color.slice(5,7),16);
				r = Math.max(0, r - r * 0.1);
				g = Math.max(0, g - g * 0.1);
				b = Math.max(0, b - b * 0.1);
				let darker = `#${((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1)}`;
				popup.style.background = darker;
				popup.style.border = `4px solid ${user.profile_color_accent}`;

				let brightness = (r*299 + g*587 + b*114) / 1000;
				lightText = brightness <= 150;
				popup.dataset.lightText = lightText.toString();
				popup.dataset.customStyle = 'true';
			}
			popup.innerHTML = `
				${user.profile_banner ? `<img draggable="false" class="user-info-profile-popup-banner" src="${user.profile_banner}">` : ''}
				<div class="user-info-profile-popup-profile-picture-username-status">
					<div class="user-info-profile-popup-profile-status">
						<img draggable="false" class="dm-info-profile-picture" src="${user.profile_picture}">
						<div class="user-info-profile-popup-status-circle-outer">
							<div class="user-info-profile-popup-status-circle-inner ${user.status}"></div>
						</div>
					</div>
					<div class="user-info-profile-popup-status-username">
						<div class="user-info-profile-popup-username-container"><p class="user-info-profile-popup-username">${user.display_name ? sanitize(user.display_name) : sanitize(user.username)}</p>${user.bot ? '<div class="bot-tag"><span class="material-symbols-rounded">check</span>BOT</div>' : ''}</div>
						<p class="user-info-profile-popup-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
					</div>
				</div>
				<div class="dm-info-container" ${user.profile_color_primary && user.profile_color_accent ? `style="background-color: rgba(255, 255, 255, 0.1); border: none;"` : ''}>
					<div class="dm-info-item">
						<p class="dm-info-item-value dm-info-item-username-original">${sanitize(user.username)}</p>
					</div>
					<div class="dm-info-tags" ${!user.premium && (!user.tags || user.tags.length === 0 ) ? 'style="display: none;"' : ''}>
						${user.premium ? '<div class="dm-info-tag"><img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/tag_premium.svg"><p class="dm-info-tag-tooltip">Premium</p></div>' : ''}
					</div>	
					<div class="dm-info-item">
						<p class="dm-info-item-key">Bio</p>
						<p class="dm-info-item-value">${user.bio ? await sanitizeMrk(user.bio) : user.bot ? 'This bot has no bio yet' : 'This user has no bio yet'}</p>
					</div>
					<div class="dm-info-item">
						<p class="dm-info-item-key">Joined on</p>
						<p class="dm-info-item-value">${new Intl.DateTimeFormat('en-US', {month: 'short', day: 'numeric', year: 'numeric'}).format(new Date(user.created_at))}</p>
					</div>
					${user.bot ? '' : `
						<a class="button-primary-filled no-underline dm-info-profile-link" ${user.profile_color_primary && user.profile_color_accent ? `data-custom-style="true" style="color: rgb(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}); background-color: rgba(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}, 0.1); border: 1px solid rgba(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}, 0.3);"` : ''} href="/profile/@${encodeURIComponent(user.username)}">View full profile</a>
					`}
				</div>

				${user.widgets?.Spotify?.item ? `
					<div class="dm-info-container" ${user.profile_color_primary && user.profile_color_accent ? `style="background-color: rgba(255, 255, 255, 0.1); border: none;"` : ''}>
						<div class="dm-info-item">
							<p class="dm-info-item-key">Playing Spotify</p>
							<div class="spotify-info">
								<div class="spotify-info-cover">
									<img src="${user.widgets.Spotify.item.album.images[0]?.url}" alt="Album cover" />
								</div>
								<div class="spotify-info-text">
									<a href="${user.widgets.Spotify.item.external_urls.spotify}" target="_blank" class="spotify-track-name" title="${user.widgets.Spotify.item.name}">
										${user.widgets.Spotify.item.name.length > 23 ? user.widgets.Spotify.item.name.slice(0, 20) + '…' : user.widgets.Spotify.item.name}
									</a>
									<p class="spotify-artists">
										${user.widgets.Spotify.item.artists.map(artist => {
											const name = artist.name.length > 18 ? artist.name.slice(0, 15) + '…' : artist.name;
											return `<a href="${artist.external_urls.spotify}" target="_blank" title="${artist.name}">${name}</a>`;
										}).join(', ')}
									</p>
									<div class="spotify-progress-container">
										<span class="spotify-time-left">${msToTime(user.widgets.Spotify.progress_ms)}</span>
										<div class="spotify-progress-bar-wrapper">
											<div class="spotify-progress-bar"></div>
										</div>
										<span class="spotify-time-right">${msToTime(user.widgets.Spotify.item.duration_ms)}</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				` : ''}
			`;

			user.tags.forEach(tag => {
				const tagEl = document.createElement("div");
				tagEl.classList.add("dm-info-tag");
				tagEl.innerHTML = `
					<img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/${tag.tag_icon}.svg">
					<p class="dm-info-tag-tooltip">${tag.tag_name}</p>
				`;
				popup.querySelector(".dm-info-tags").appendChild(tagEl);
			});

			document.body.appendChild(popup);

			let rect = userEl.getBoundingClientRect();
			let popupHeight = popup.offsetHeight;
			let viewportHeight = window.innerHeight;

			let top = rect.top + window.scrollY;

			popup.style.top = "";
			popup.style.bottom = "";

			if (top + popupHeight > window.scrollY + viewportHeight - 15) {
				popup.style.bottom = "15px";
				popup.style.top = "";
			} else {
				popup.style.top = top + "px";
			}

			if (user.widgets?.Spotify?.item) {
				updateSpotifyPopup(popup, user.widgets.Spotify);
			}

			if (!document.body.hasAttribute("data-popup-listener")) {
				document.addEventListener("click", (e) => {
					const popupEl = document.querySelector(".user-info-profile-popup");
					if (popupEl && !popupEl.contains(e.target)) {
						document.querySelectorAll(".info-profile.active").forEach(el => {
							el.classList.remove("active");
						});
						popupEl.remove();
					}
				});
				document.body.setAttribute("data-popup-listener", "true");
			}
		};
	}

	function updateSpotifyPopup(popup, spotify) {
		const progressBar = popup.querySelector(".spotify-progress-bar");
		const timeLeftEl = popup.querySelector(".spotify-time-left");
		const timeRightEl = popup.querySelector(".spotify-time-right");
		const trackNameEl = popup.querySelector(".spotify-track-name");
		const artistsEl = popup.querySelector(".spotify-artists");
		const albumCoverEl = popup.querySelector(".spotify-info-cover img");

		const duration = spotify.item.duration_ms;
		const startTime = Date.now() - spotify.progress_ms;

		timeRightEl.textContent = msToTime(duration);

		if (popup.spotifyInterval) clearInterval(popup.spotifyInterval);

		function tick() {
			let progress = Math.min(Date.now() - startTime, duration);
			timeLeftEl.textContent = msToTime(progress);
			progressBar.style.width = (progress / duration) * 100 + "%";
		}

		tick();
		popup.spotifyInterval = setInterval(tick, 1000);

		if (trackNameEl && spotify.item.name !== trackNameEl.title) {
			trackNameEl.textContent = spotify.item.name.length > 23 ? spotify.item.name.slice(0, 20) + '…' : spotify.item.name;
			trackNameEl.title = spotify.item.name;
			trackNameEl.href = spotify.item.external_urls.spotify;
		}

		if (artistsEl) {
			artistsEl.innerHTML = spotify.item.artists.map(artist => {
				const name = artist.name.length > 18 ? artist.name.slice(0, 15) + '…' : artist.name;
				return `<a href="${artist.external_urls.spotify}" target="_blank" title="${artist.name}">${name}</a>`;
			}).join(', ');
		}

		if (albumCoverEl) albumCoverEl.src = spotify.item.album.images[0]?.url;
	}

	socket.on("user_widget_updated", (data) => {
		const userIndex = usersList.findIndex(u => u.id === data.id);
		if (userIndex !== -1) {
			usersList[userIndex] = { ...usersList[userIndex], ...data };
			renderUser(usersList[userIndex]);
		}

		const popup = document.querySelector(".user-info-profile-popup");
		if (popup && popup.dataset.userId === data.id) {
			const spotify = data.Spotify;
			if (spotify && spotify.item) updateSpotifyPopup(popup, spotify);
			else {
				const spotifyContainer = popup.querySelector(".dm-info-container .spotify-info")?.parentElement;
				if (spotifyContainer) spotifyContainer.remove();
				if (popup.spotifyInterval) clearInterval(popup.spotifyInterval);
			}
		}
	});

	function msToTime(ms) {
		const totalSeconds = Math.floor(ms / 1000);
		const minutes = Math.floor(totalSeconds / 60);
		const seconds = totalSeconds % 60;
		return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
	}

	socket.on("server_users", async (users) => {
		onlineUsersEl.innerHTML = "";
		offlineUsersEl.innerHTML = "";

		const onlineLabel = document.createElement("p");
		onlineLabel.classList.add("online-msg");
		onlineLabel.innerText = "Online";
		onlineUsersEl.appendChild(onlineLabel);

		const offlineLabel = document.createElement("p");
		offlineLabel.classList.add("offline-msg");
		offlineLabel.innerText = "Offline";
		offlineUsersEl.appendChild(offlineLabel);

		usersList = users;

		await Promise.all(users.map(user => renderUser(user)));
		onUsersListLoaded();
	});

	socket.on("user_updated", async (user) => {
		if (!usersList.some(u => String(u.id) === user.id)) return;
		await renderUser(user);
	});


	async function replyMessage(id) {
		if (document.querySelector(".replying-to")) document.querySelector(".replying-to").remove();
		replyingTo = id;
		document.getElementById("message-input").focus();

		const { parent_message_text, parent_message_user } = await loadParentMessage(id);

		document.querySelector(".input-container-2").insertAdjacentHTML("afterbegin", `
			<div class="replying-to">
				<div class="replying-to-username-container">
				<p>Replying to:</p>
				<p class="replying-to-username">${parent_message_user}</p> 
				</div>
				<span class="material-symbols-rounded close-replying-to" id="close-replying-to">close</span>
			</div>
		`);

		document.getElementById("close-replying-to").addEventListener("click", () => {
			replyingTo = null;
			document.querySelector(".replying-to").remove();
		});
	}

	document.getElementById("new-category").addEventListener("click", openCreateCategoryModal);

	function openCreateCategoryModal() {
	let modalHtml = `
		<div class="modal" id="create-category-modal">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title">Create Category</h2>
					<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
				</div>
				<div class="modal-body">
					<form id="create-category-form" class="create-category-form">
						<label for="category-name">Category Name:</label>
						<input type="text" id="category-name" name="category-name" class="input-text-dark-bg w270" required>
						<button type="submit" class="button-primary-filled">Create</button>
					</form>
				</div>
			</div>
		</div>
	`;

	document.body.insertAdjacentHTML("beforeend", modalHtml);

	const modal = document.getElementById("create-category-modal");
	const modalContent = modal.querySelector(".modal-content");

	setTimeout(() => {
		function handleClickOutside(event) {
			if (!modalContent.contains(event.target)) {
				modal.remove();
				document.removeEventListener("click", handleClickOutside);
			}
		}

		document.addEventListener("click", handleClickOutside);
	}, 10);

	const modalCloseBtn = document.getElementById("close-modal-btn");
	modalCloseBtn.addEventListener("click", () => {
		modal.remove();
	});

	const createCategoryForm = document.getElementById("create-category-form");
	createCategoryForm.addEventListener("submit", (event) => {
		event.preventDefault();
		const categoryName = document.getElementById("category-name").value;
		createCategory(categoryName);
		modal.remove();
	});
	}

	document.getElementById("new-channel").addEventListener("click", openCreateChannelModal);

	function openCreateChannelModal() {
	let modalHtml = `
		<div class="modal" id="create-channel-modal">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title">Create Channel</h2>
					<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
				</div>
				<div class="modal-body">
					<form id="create-channel-form" class="create-channel-form">
						<label for="channel-name">Channel Name:</label>
						<input type="text" id="channel-name" name="channel-name" class="input-text-dark-bg w270" required>
						<label for="channel-category">Category:</label>
						<select id="channel-category" name="channel-category" class="input-text-dark-bg w270" required>
							<option value="" disabled selected>Select a category</option>
						</select>
						<label for="channel-type">Channel Type:</label>
						<select id="channel-type" name="channel-type" class="input-text-dark-bg w270" required>
							<option value="text" selected>Text</option>
							<option value="voice">Voice</option>
						</select>
						<button type="submit" class="button-primary-filled">Create</button>
					</form>
				</div>
			</div>
		</div>
	`;

	document.body.insertAdjacentHTML("beforeend", modalHtml);

	const modal = document.getElementById("create-channel-modal");
	const modalContent = modal.querySelector(".modal-content");

	channel_groups.forEach((category) => {
		const option = document.createElement("option");
		option.value = category.channel_group_id;
		option.textContent = category.channel_group_name;
		document.getElementById("channel-category").appendChild(option);
	})

	setTimeout(() => {
		function handleClickOutside(event) {
			if (!modalContent.contains(event.target)) {
				modal.remove();
				document.removeEventListener("click", handleClickOutside);
			}
		}

		document.addEventListener("click", handleClickOutside);
	}, 10);

	const modalCloseBtn = document.getElementById("close-modal-btn");
	modalCloseBtn.addEventListener("click", () => {
		modal.remove();
	});

	const createChannelForm = document.getElementById("create-channel-form");
	createChannelForm.addEventListener("submit", (event) => {
		event.preventDefault();
		const channelName = modal.querySelector("#channel-name").value;
		const category = modal.querySelector("#channel-category").value;
		const channelType = modal.querySelector("#channel-type").value;
		createChannel(channelName, category, channelType);
		modal.remove();
	});
	}


	function createCategory(name) {
	const formData = new FormData();
	formData.append("action", "create_category");
	formData.append("category_name", name);
	formData.append("server_id", server_id);

	fetch("/app/edit_server", {
		method: "POST",
		headers: {
			"Authorization": `Bearer ${access_token}`
		},
		body: formData
	})
	.then(response => {
		if (!response.ok) throw new Error("Failed to create category");
		return response.json();
	})
	.then(data => {
		if (data.status === "success") {
			Toastify({
				text: "Category created!",
				duration: 3000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
				}
			}).showToast();

			window.location.reload();

		} else {
			Toastify({
				text: "Failed to create category. Please try again.",
				duration: 5000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",  
				borderRadius: "12px", 
				boxShadow: "none"
				}
			}).showToast();
		}
	})
	.catch(error => {
		console.error("Error creating category:", error);
	});
	}

	function createChannel(name, category, type = "text") {
	const formData = new FormData();
	formData.append("action", "create_channel");
	formData.append("channel_name", name);
	formData.append("channel_category_id", category);
	formData.append("server_id", server_id);
	formData.append("channel_type", type);

	fetch("/app/edit_server", {
		method: "POST",
		headers: {
			"Authorization": `Bearer ${access_token}`
		},
		body: formData
	})
	.then(response => {
		if (!response.ok) throw new Error("Failed to create channel");
		return response.json();
	})
	.then(data => {
		if (data.status === "success") {
			Toastify({
				text: "Channel created!",
				duration: 3000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",   
				borderRadius: "12px",     
				boxShadow: "none"
				}
			}).showToast();

			window.location.reload();

		} else {
			Toastify({
				text: "Failed to create channel. Please try again.",
				duration: 5000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",   
				borderRadius: "12px", 
				boxShadow: "none"
				}
			}).showToast();
		}
	})
	.catch(error => {
		console.error("Error creating channel:", error);
	});
	}
	

	function leaveServer() {
		let modalHtml = `
		<div class="modal" id="leave-modal">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title">Leave Server</h2>
					<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
				</div>
				<div class="modal-body">
					<form id="leave-form" class="leave-form">
						<label>Are you sure you want to leave this server? You will no longer be able to access it unless you get invited again.</label>
						<button type="submit" class="button-primary-filled">Leave Server</button>
					</form>
				</div>
			</div>
		</div>
	`;

	document.body.insertAdjacentHTML("beforeend", modalHtml);

	const modal = document.getElementById("leave-modal");
	const modalContent = modal.querySelector(".modal-content");

	setTimeout(() => {
		function handleClickOutside(event) {
			if (!modalContent.contains(event.target)) {
				modal.remove();
				document.removeEventListener("click", handleClickOutside);
			}
		}

		document.addEventListener("click", handleClickOutside);
	}, 10);

	const modalCloseBtn = document.getElementById("close-modal-btn");
	modalCloseBtn.addEventListener("click", () => {
		modal.remove();
	});

	const leaveServerForm = document.getElementById("leave-form");
	leaveServerForm.addEventListener("submit", (event) => {
		event.preventDefault();
		leaveServerRequest();
		modal.remove();
	});
	}

	function leaveServerRequest() {
	const formData = new FormData();
	formData.append("action", "leave_server");
	formData.append("server_id", server_id);

	fetch("/app/edit_server", {
		method: "POST",
		headers: {
			"Authorization": `Bearer ${access_token}`
		},
		body: formData
	})
	.then(response => {
		if (!response.ok) throw new Error("Failed to leave server");
		return response.json();
	})
	.then(data => {
		if (data.status === "success") {
			Toastify({
				text: "You have left the server. ",
				duration: 3000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
				}
			}).showToast();

			window.location.href = "/home";
		} else {
			Toastify({
				text: "Failed to leave server. Please try again.",
				duration: 5000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
				}
			}).showToast();
		}
	})
	.catch(error => {
		console.error("Error leaving server:", error);
	});
	}

	const invitePeopleBtn = document.getElementById("invite-people");
	invitePeopleBtn.addEventListener("click", showInviteModal);

	const leaveServerBtn = document.getElementById("leave-server");
	leaveServerBtn.addEventListener("click", leaveServer);

	function showInviteModal() {
		if (!is_in_server) {
			return;
		}
		
		let modalHtml = `
			<div class="modal" id="invite-modal">
				<div class="modal-content">
					<div class="modal-header">
						<h2 class="modal-title">Create Invite</h2>
						<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
					</div>
					<div class="modal-body">
						<form id="invite-form" class="leave-form">
							<label for="invite-expiration">Invite Expiration:</label>
							<select id="invite-expiration" name="invite-expiration" required class="input-text-dark-bg w270">
								<option value="1 hour">1 hour</option>
								<option value="12 hours">12 hours</option>
								<option value="1 day">1 day</option>
								<option value="7 days" selected>7 days</option>
								<option value="30 days">30 days</option>
								<option value="never">Never</option>
							</select>
							<button type="submit" class="button-primary-filled">Create Invite</button>
						</form>
					</div>
				</div>
			</div>
		`;

		document.body.insertAdjacentHTML("beforeend", modalHtml);

		const modal = document.getElementById("invite-modal");
		const modalContent = modal.querySelector(".modal-content");

		setTimeout(() => {
			function handleClickOutside(event) {
				if (!modalContent.contains(event.target)) {
					modal.remove();
					document.removeEventListener("click", handleClickOutside);
				}
			}

			document.addEventListener("click", handleClickOutside);
		}, 10);

		const modalCloseBtn = document.getElementById("close-modal-btn");
		modalCloseBtn.addEventListener("click", () => {
			modal.remove();
		});

		const inviteServerForm = document.getElementById("invite-form");
		inviteServerForm.addEventListener("submit", (event) => {
			event.preventDefault();
			createInvite(inviteServerForm.elements["invite-expiration"].value);
			modal.remove();
		});
	}

	function createInvite(expiry) {
	const formData = new FormData();
	formData.append("action", "create_invite");
	formData.append("server_id", server_id);
	formData.append("expires_in", expiry);

	fetch("/app/edit_server", {
		method: "POST",
		headers: {
			"Authorization": `Bearer ${access_token}`
		},
		body: formData
	})
	.then(response => {
		if (!response.ok) throw new Error("Failed to create invite");
		return response.json();
	})
	.then(data => {
		if (data.status === "success") {
			const url = data.url;

			let modalHtml = `
			<div class="modal" id="invite-modal-2">
				<div class="modal-content">
					<div class="modal-header">
						<h2 class="modal-title">Create Invite</h2>
						<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
					</div>
					<div class="modal-body">
						<form id="invite-form-2" class="leave-form">
							<label for="invite-url">Your invitation has been created. ${expiry === "never" ? "" : `This link will expire in ${expiry}`}</label>
							<div class="input-group"><input type="text" id="invite-url" name="invite-url" class="input-text-dark-bg w270" value="${url}" readonly onclick="this.select()"><button class="button-primary-filled copy-btn" id="copy-btn" data-clipboard-target="#invite-url">Copy</button></div>
							<button type="submit" class="button-primary-filled">Okay</button>
						</form>
					</div>
				</div>
			</div>
			`;

			document.body.insertAdjacentHTML("beforeend", modalHtml);

			const modal = document.getElementById("invite-modal-2");
			const modalContent = modal.querySelector(".modal-content");

			setTimeout(() => {
				function handleClickOutside(event) {
					if (!modalContent.contains(event.target)) {
						modal.remove();
						document.removeEventListener("click", handleClickOutside);
					}
				}

				document.addEventListener("click", handleClickOutside);
			}, 10);

			const copyBtn = document.getElementById("copy-btn");
			copyBtn.addEventListener("click", (e) => {
			e.preventDefault();
			navigator.clipboard.writeText(url).then(() => {
				Toastify({
				text: "Copied to clipboard",
				duration: 5000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
					background: "var(--clr-popup-a20)",
					borderRadius: "12px",
					boxShadow: "none"
				}
				}).showToast();
			}).catch(err => {
				console.error("Failed to copy: ", err);
			});
			});
			
			copyBtn.click();

			const modalCloseBtn = document.getElementById("close-modal-btn");
			modalCloseBtn.addEventListener("click", () => {
				modal.remove();
			});

			const okayBtn = document.querySelector("#invite-form-2 button[type='submit']");
			okayBtn.addEventListener("click", (e) => {
				e.preventDefault();
				const modal = document.getElementById("invite-modal-2");
				modal.remove();
			});
		} else {
			Toastify({
				text: "Failed to create invite. Please try again.",
				duration: 5000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
				}
			}).showToast();
		}
	})
	.catch(error => {
		console.error("Error creating invite:", error);
	});
	}

	const MAX_FILES = 10;
	const MAX_SIZE = 25 * 1024 * 1024;
	const fileInput = document.getElementById('file-input');

	const ALLOWED_TYPES = [
	"image/png",
	"image/jpeg",
	"image/gif",
	"image/webp",
	"image/bmp",
	"video/mp4",
	"video/webm",
	"video/ogg",
	"audio/mpeg",
	"audio/wav",
	"audio/ogg",
	"application/pdf",
	"text/plain"
	];

	if (fileInput) {
	
	fileInput.addEventListener('change', async (e) => {
		const files = Array.from(e.target.files);

		const dotFrames = ["", ".", "..", "...", "..", "."];
		let dotIndex = 0;

		const uploadingToast = Toastify({
		text: `Uploading ${files.length} file${files.length > 1 ? 's' : ''}${dotFrames[dotIndex]}`,
		duration: -1,
		gravity: "bottom",
		position: "right",
		close: true,
		stopOnFocus: true,
		style: {
			background: "var(--clr-popup-a20)",
			borderRadius: "12px",
			boxShadow: "none"
		}
		});
		uploadingToast.showToast();

		const intervalId = setInterval(() => {
		dotIndex = (dotIndex + 1) % dotFrames.length;
		uploadingToast.text = `Uploading ${files.length} file${files.length > 1 ? 's' : ''}${dotFrames[dotIndex]}`;

		const toastElem = document.querySelector(".toastify");
		if (toastElem) {
			for (const node of toastElem.childNodes) {
			if (node.nodeType === Node.TEXT_NODE) {
				node.textContent = uploadingToast.text + ' ';
				break;
			}
			}
		}
		}, 500);

		if ((selectedFiles.length + files.length) > MAX_FILES) {
		clearInterval(intervalId);
		uploadingToast.hideToast();
		Toastify({
			text: `You can only upload up to ${MAX_FILES} files at once.`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
			background: "var(--clr-popup-a20)",
			borderRadius: "12px",
			boxShadow: "none"
			}
		}).showToast();
		fileInput.value = '';
		return;
		}

		for (const file of files) {
		if (file.size > MAX_SIZE) {
			clearInterval(intervalId);
			uploadingToast.hideToast();
			Toastify({
			text: `File size exceeds the 25MB upload limit.`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
			fileInput.value = '';
			return;
		}
		}

		fileInput.value = '';

		for (const file of files) {
		try {
			const savedName = await upload_single_file(file);
			selectedFiles.push({ file, savedName, originalName: file.name });
		} catch (err) {
			clearInterval(intervalId);
			uploadingToast.hideToast();
			Toastify({
			text: `Failed to upload ${file.name}`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "#ff3b3b",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
		}
		}

		clearInterval(intervalId);
		uploadingToast.hideToast();

		if (selectedFiles.length > 0) {
		Toastify({
			text: `Successfully uploaded ${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''}.`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
			background: "var(--clr-popup-a20)",
			borderRadius: "12px",
			boxShadow: "none"
			}
		}).showToast();
		}

		renderPreviews();
	});

	document.addEventListener('paste', async (event) => {
		const items = event.clipboardData?.items;
		if (!items) return;

		for (const item of items) {
		if (item.kind !== 'file') continue;

		const file = item.getAsFile();
		if (!file) continue;

		if (!ALLOWED_TYPES.includes(file.type)) {
			Toastify({
			text: `File type not allowed: ${file.type}`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "#ff3b3b",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
			continue;
		}

		if (selectedFiles.length >= MAX_FILES) {
			Toastify({
			text: `You can only upload up to ${MAX_FILES} files.`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
			return;
		}

		if (file.size > MAX_SIZE) {
			Toastify({
			text: `Pasted file is too big (limit: 25MB).`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "#ff3b3b",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
			return;
		}

		const toast = Toastify({
			text: `Uploading pasted file...`,
			duration: -1,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
			background: "var(--clr-popup-a20)",
			borderRadius: "12px",
			boxShadow: "none"
			}
		});
		toast.showToast();

		try {
			const savedName = await upload_single_file(file);
			selectedFiles.push({ file, savedName, originalName: file.name });

			renderPreviews();

			toast.hideToast();
			Toastify({
			text: `Pasted file uploaded successfully!`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
		} catch (err) {
			console.error("Upload failed:", err);
			toast.hideToast();
			Toastify({
			text: `Failed to upload pasted file.`,
			duration: 5000,
			gravity: "bottom",
			position: "right",
			close: true,
			stopOnFocus: true,
			style: {
				background: "#ff3b3b",
				borderRadius: "12px",
				boxShadow: "none"
			}
			}).showToast();
		}
		}
	});


	async function upload_single_file(file) {
		const formData = new FormData();
		formData.append("files[]", file);

		const response = await fetch("https://chat.wokki20.nl/app/upload_file", {
		method: "POST",
		body: formData,
		headers: {
			"Authorization": `Bearer ${access_token}`
		}
		});

		const result = await response.json();

		if (result.status === 'success' && Array.isArray(result.files) && result.files[0]) {
		return result.files[0].saved_name;
		} else {
		throw new Error("Upload failed");
		}
	}
	};

	let draggedClone = null;
	let originalElement = null;
	let lastTarget = null;
	let originalParent = null;
	let originalNextSibling = null;

	const channelDragStart = (e) => {
		originalElement = e.currentTarget;
		originalParent = originalElement.parentElement;
		originalNextSibling = originalElement.nextElementSibling;

		const img = new Image();
		img.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgCMW3YkAAAAASUVORK5CYII=';
		e.dataTransfer.setDragImage(img, 0, 0);

		draggedClone = originalElement.cloneNode(true);
		draggedClone.style.position = 'absolute';
		draggedClone.style.pointerEvents = 'none';
		draggedClone.style.opacity = '0.8';
		draggedClone.style.zIndex = '1000';
		document.body.appendChild(draggedClone);

		moveChannel(e);

		document.addEventListener('dragover', moveChannel);
		document.addEventListener('dragend', stopDragging);
	};

	const moveChannel = (e) => {
		if (!draggedClone) return;

		const rect = draggedClone.getBoundingClientRect();
		draggedClone.style.left = e.pageX - rect.width / 2 + 'px';
		draggedClone.style.top = e.pageY - rect.height / 2 + 'px';

		let target = document.elementFromPoint(e.clientX, e.clientY);
		if (target === draggedClone) target = target.parentElement;

		target = target.closest('.channel-bar-channel');

		if (target === originalElement) target = null;

		if (target) {
			if (lastTarget && lastTarget !== target) {
				lastTarget.classList.remove('channel-position-line');
				lastTarget.removeAttribute('data-line');
			}

			target.classList.add('channel-position-line');

			const targetRect = target.getBoundingClientRect();
			if (e.clientY - targetRect.top < targetRect.height / 2) {
				target.setAttribute('data-line', 'top');
			} else {
				target.setAttribute('data-line', 'bottom');
			}

			lastTarget = target;
		} else if (lastTarget) {
			lastTarget.classList.remove('channel-position-line');
			lastTarget.removeAttribute('data-line');
			lastTarget = null;
		}
	};

	const stopDragging = () => {
		if (draggedClone) {
			draggedClone.remove();
			draggedClone = null;
		}

		if (lastTarget && originalElement) {
			const linePosition = lastTarget.getAttribute('data-line');
			if (linePosition) {
				if (linePosition === 'top') {
					lastTarget.parentElement.insertBefore(originalElement, lastTarget);
				} else {
					lastTarget.parentElement.insertBefore(originalElement, lastTarget.nextSibling);
				}
			}

			lastTarget.classList.remove('channel-position-line');
			lastTarget.removeAttribute('data-line');
		}

		const originalGroup = originalParent.closest('.channel-group');
		const newGroup = originalElement.parentElement.closest('.channel-group');

		const cleanup = () => {
			lastTarget = null;
			originalElement = null;
			originalParent = null;
			originalNextSibling = null;
			document.removeEventListener('dragover', moveChannel);
			document.removeEventListener('dragend', stopDragging);
		};

		const sendUpdate = () => {
			const channels = Array.from(newGroup.querySelectorAll('.channel-bar-channel'));
			const serverId = server_id;
			const channelId = originalElement.dataset.channelId;
			const channelGroup = newGroup.dataset.channelGroup;

			const index = channels.length - 1 - channels.indexOf(originalElement);

			const otherChannelIndexes = {};

			channels.forEach((channel) => {
				if (channel === originalElement) return;
				const id = channel.dataset.channelId;
				const idx = channels.length - 1 - channels.indexOf(channel);
				otherChannelIndexes[id] = idx;
			});

			fetch('https://chat.wokki20.nl/app/edit_server', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					Authorization: `Bearer ${access_token}`,
				},
				body: new URLSearchParams({
					action: 'edit_channel_index',
					server_id: serverId,
					channel_id: channelId,
					index: index,
					channel_group: channelGroup,
					other_channel_indexes: JSON.stringify(otherChannelIndexes)
				})
			});
		};

		if (originalGroup !== newGroup) {
			jspt.makePopup({
				content_type: 'html',
				header: 'Confirm Category Change',
				custom_id: 'move-confirm',
				content: `
					<p class="popup-text">Are you sure you want to move this channel to a different category?</p>
					<p class="popup-subtext">This will also remove the original category if no other channels are in it.</p>
					<div class="channel-preview">
						<p class="popup-subtext">This channel will be moved:</p>
						${originalElement.outerHTML}
					</div>
					<div style="display:flex; gap:10px; margin-top:15px;">
						<button class="button-primary-filled" id="move-yes">Yes</button>
						<button class="button-primary-outline" id="move-no">No</button>
					</div>
				`
			});

			setTimeout(() => {
				const yesBtn = document.getElementById('move-yes');
				const noBtn = document.getElementById('move-no');

				if (yesBtn) {
					yesBtn.addEventListener('click', () => {
						jspt.closePopup('move-confirm');
						sendUpdate();
						cleanup();
					});
				}

				if (noBtn) {
					noBtn.addEventListener('click', () => {
						if (originalNextSibling) {
							originalParent.insertBefore(originalElement, originalNextSibling);
						} else {
							originalParent.appendChild(originalElement);
						}
						jspt.closePopup('move-confirm');
						cleanup();
					});
				}
			}, 50);
		} else {
			sendUpdate();
			cleanup();
		}
	};

	const movableChannels = document.querySelectorAll('#channel[movable="true"]');
	movableChannels.forEach((channel) => {
		channel.addEventListener('dragstart', channelDragStart, false);
	});

	let draggedGroupClone = null;
	let originalGroupElement = null;
	let lastGroupTarget = null;
	let originalGroupParent = null;
	let originalGroupNextSibling = null;

	const groupDragStart = (e) => {
		if (e.target !== e.currentTarget) return;
		originalGroupElement = e.currentTarget;
		originalGroupParent = originalGroupElement.parentElement;
		originalGroupNextSibling = originalGroupElement.nextElementSibling;

		const img = new Image();
		img.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgCMW3YkAAAAASUVORK5CYII=';
		e.dataTransfer.setDragImage(img, 0, 0);

		draggedGroupClone = originalGroupElement.cloneNode(true);
		draggedGroupClone.style.position = 'absolute';
		draggedGroupClone.style.pointerEvents = 'none';
		draggedGroupClone.style.opacity = '0.8';
		draggedGroupClone.style.zIndex = '1000';
		document.body.appendChild(draggedGroupClone);

		moveGroup(e);

		document.addEventListener('dragover', moveGroup);
		document.addEventListener('dragend', stopGroupDragging);
	};

	const moveGroup = (e) => {
		if (!draggedGroupClone) return;

		const rect = draggedGroupClone.getBoundingClientRect();
		draggedGroupClone.style.left = e.pageX - rect.width / 2 + 'px';
		draggedGroupClone.style.top = e.pageY - rect.height / 2 + 'px';

		let target = document.elementFromPoint(e.clientX, e.clientY);
		if (target === draggedGroupClone) target = target.parentElement;

		target = target.closest('.channel-group');

		if (target === originalGroupElement) target = null;

		if (target && target.getAttribute('draggable') !== 'true') {
			target = null;
		}

		if (target) {
			if (lastGroupTarget && lastGroupTarget !== target) {
				lastGroupTarget.classList.remove('group-position-line');
				lastGroupTarget.removeAttribute('data-line');
			}

			target.classList.add('group-position-line');

			const targetRect = target.getBoundingClientRect();
			if (e.clientY - targetRect.top < targetRect.height / 2) {
				target.setAttribute('data-line', 'top');
			} else {
				target.setAttribute('data-line', 'bottom');
			}

			lastGroupTarget = target;
		} else if (lastGroupTarget) {
			lastGroupTarget.classList.remove('group-position-line');
			lastGroupTarget.removeAttribute('data-line');
			lastGroupTarget = null;
		}
	};

	const stopGroupDragging = () => {
		if (draggedGroupClone) {
			draggedGroupClone.remove();
			draggedGroupClone = null;
		}

		if (lastGroupTarget && originalGroupElement) {
			const linePosition = lastGroupTarget.getAttribute('data-line');
			if (linePosition) {
				if (linePosition === 'top') {
					lastGroupTarget.parentElement.insertBefore(originalGroupElement, lastGroupTarget);
				} else {
					lastGroupTarget.parentElement.insertBefore(originalGroupElement, lastGroupTarget.nextSibling);
				}
			}

			lastGroupTarget.classList.remove('group-position-line');
			lastGroupTarget.removeAttribute('data-line');
		}

		const cleanup = () => {
			lastGroupTarget = null;
			originalGroupElement = null;
			originalGroupParent = null;
			originalGroupNextSibling = null;
			document.removeEventListener('dragover', moveGroup);
			document.removeEventListener('dragend', stopGroupDragging);
		};

		const sendGroupUpdate = () => {
			const groups = Array.from(document.querySelectorAll('.channel-group'));
			const groupId = originalGroupElement.dataset.channelGroup;

			const index = groups.length - 1 - groups.indexOf(originalGroupElement);

			const otherGroupIndexes = {};
			groups.forEach((group) => {
				if (group === originalGroupElement) return;
				const id = group.dataset.channelGroup;
				const idx = groups.length - 1 - groups.indexOf(group);
				otherGroupIndexes[id] = idx;
			});

			fetch('https://chat.wokki20.nl/app/edit_server', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					Authorization: `Bearer ${access_token}`,
				},
				body: new URLSearchParams({
					action: 'edit_channel_group',
					server_id: server_id,
					group_id: groupId,
					index: index,
					other_group_indexes: JSON.stringify(otherGroupIndexes)
				})
			});
		};

		sendGroupUpdate();
		cleanup();
	};

	const movableGroups = document.querySelectorAll('.channel-group');
	movableGroups.forEach((group) => {
		group.addEventListener('dragstart', groupDragStart, false);
	});

	hljs.highlightAll();
}

initServer();