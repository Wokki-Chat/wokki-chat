import emojis from "./emojis.js";
import { MessageRenderer, MessageBehaviour, MessageHydrator, MessageCache } from "./modules/servers/messages.js";
import ReactionRenderer from "./modules/servers/reactions.js";
import SettingsManager from "./modules/servers/settings.js";
import { Sanitizer, TextareaFormatter } from "./modules/global/sanitization.js";
import { UserPopupManager } from "./modules/users/popups.js";
import { Mentions } from "./modules/users/mentions.js";
import { CommandsManager } from "./modules/global/commands.js";
function initServer() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('server.js')) return;

    const serverBar = document.querySelector(".server-bar");
    if (serverBar && window.matchMedia("(min-width: 768px)").matches) {
        serverBar.classList.remove("hidden");
    } else {
		serverBar.classList.add("hidden");
	}
	
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

	const username_text = document.getElementById("username-text").getAttribute("value");
	const profile_picture_url = document.getElementById("profile-picture-url").getAttribute("value");

	const messageContainer = document.querySelector("#message-container");
	
	const messageRenderer = new MessageRenderer({ user_id, channels, server_id });
	const messageCache = new MessageCache();
	const reactionRenderer = new ReactionRenderer({ user_id, channel_id, server_id, access_token, socket });
	const messageBehaviour = new MessageBehaviour({ user_id, channel_id, server_id, access_token, socket, messageContainer });
	const messageHydrator = new MessageHydrator({ user_id, channels, server_id });
	const settingsManager = new SettingsManager({ user_id, channel_id, server_id, access_token, socket });
	const mentions = new Mentions({ user_id, channels, server_id, users_list: [] });
	const commandsManager = new CommandsManager({ user_id, channel_id, server_id, access_token, socket });

	const userPopupManager = new UserPopupManager({ user_id, access_token });

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

	const textarea = document.getElementById("message-input");
	const preview = document.getElementById("message-input-bg");

	const sanitizer = new Sanitizer(user_id, channels, server_id);
	const textareaFormatter = new TextareaFormatter(user_id, channels, server_id, textarea);

	const textareaEmojiOptions = document.getElementById("emoji-option");
	textareaEmojiOptions.addEventListener("click", async () => {
		await emojis.picker(textarea, textareaEmojiOptions, true);
	})

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

	(async () => {
		await emojis.load();
	})();

	loadMessages();
	socket.emit("get_server_users", {
		access_token,
		server_id,
		channel_id
	});

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
		await handleAllMessages(messages);
	});

	socket.on("all_messages_nocache", async (messages) => {
		await handleAllMessages(messages);
	});

	async function handleAllMessages(messages) {

		if (!Array.isArray(messages)) {
			return;
		}

		for (const msg of messages) {
			await handleMessage(msg);
		}
	}

	function insertMessageEl(el, insertIndex) {
		if (insertIndex === messageContainer.children.length) {
			messageContainer.appendChild(el)
		} else {
			messageContainer.insertBefore(el, messageContainer.children[insertIndex])
		}
	}

	async function handleMessage(msg) {
		messageCache.removeExisting(msg.id);

		const el = await messageRenderer.create(msg, usersList);
		if (!el) return;

		const scrollTopBefore = messageContainer.scrollTop;
		const scrollHeightBefore = messageContainer.scrollHeight;
		const nearBottom = scrollHeightBefore - scrollTopBefore - messageContainer.clientHeight <= 10;

		const insertIndex = messageCache.insertIntoCache(msg, el);
		insertMessageEl(el, insertIndex);
		messageBehaviour.applyCompactMode(el, msg, insertIndex, messageCache.cache);
		messageBehaviour.attach(el, msg);
		
		const hydratePromise = messageHydrator.hydrate(el, msg.assets, msg.id).then(() => {
			if (nearBottom) {
				requestAnimationFrame(() => {
					messageContainer.scrollTop = messageContainer.scrollHeight;
				});
			}
		});

		await emojis.replaceEl(el);

		const customPlayer = el.querySelector(".custom-player");
		if (customPlayer) await initCustomPlayer(customPlayer);

		const replyBtn = el.querySelector("#reply-btn");
		replyBtn.addEventListener("click", () => replyMessage(msg.id));

		const deleteBtn = el.querySelector("#delete-btn");
		if (deleteBtn) {
			deleteBtn.addEventListener("click", () => {
				socket.emit("delete_message", { access_token, message_id: msg.id, server_id, channel_id });
				deleteMsg(msg.id);
			});
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

	async function updateReactionUI(el, msg_id, emoji, reactingUserId, removed = false) {
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
						if (String(reactingUserId) === String(user_id)) {
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
				if (String(reactingUserId) === String(user_id)) {
					reactionEl.classList.add("own");
				}
			}
		} else if (!removed) {
			const newReactionEl = await reactionRenderer.reaction({
				reactionGroup: [{
					reaction: emoji,
					user_id: reactingUserId,
					reaction_user_info: { username: username_text, profile_picture: profile_picture_url }
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

	socket.on("add_reaction", ({ message_id, reaction, user_id: reactingUserId }) => {
		const el = document.querySelector(`.message[data-message-id="${message_id}"]`);
		if (el) updateReactionUI(el, message_id, reaction, reactingUserId, false);
	});

	socket.on("remove_reaction", ({ message_id, reaction, user_id: reactingUserId }) => {
		const el = document.querySelector(`.message[data-message-id="${message_id}"]`);
		if (el) updateReactionUI(el, message_id, reaction, reactingUserId, true);
	});
	
	const customPlayers = new Map();

	function audioLoaded(audio) {
		return new Promise((resolve) => {
			if (audio.readyState >= 1) {
				resolve();
			} else {
				audio.addEventListener('loadedmetadata', () => resolve(), { once: true });
			}
		});
	}

	async function initCustomPlayer(el) {
		const audioSrc = el.dataset.audioSrc;
		const originalName = el.dataset.originalname;

		if (!audioSrc) return;

		let audio;
		if (customPlayers.has(audioSrc)) {
			audio = customPlayers.get(audioSrc);
		} else {
			audio = new Audio(audioSrc);
			audio.preload = "metadata";
			customPlayers.set(audioSrc, audio);
		}

		const playPauseBtn = el.querySelector('.play-pause');
		const seekBar = el.querySelector('.seek-bar');
		const currentTimeEl = el.querySelector('.current-time');
		const durationEl = el.querySelector('.duration');
		const timeBox = el.querySelector('.time-left-current');
		const downloadBtn = el.querySelector('#download');

		let updateInterval;

		function formatTime(seconds) {
			const mins = Math.floor(seconds / 60);
			const secs = Math.floor(seconds % 60).toString().padStart(2, '0');
			return `${mins}:${secs}`;
		}

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

		await audioLoaded(audio);

		seekBar.max = audio.duration;
		durationEl.textContent = `/ ${formatTime(audio.duration)}`;
		updateSeekBarProgress();

		playPauseBtn.addEventListener('click', () => {
			if (audio.paused) {
				audio.play();
				playPauseBtn.textContent = 'pause';

				updateInterval = setInterval(() => {
					seekBar.value = audio.currentTime;
					currentTimeEl.textContent = formatTime(audio.currentTime);
					updateSeekBarProgress();
				}, 250);
			} else {
				audio.pause();
				playPauseBtn.textContent = 'play_arrow';
				clearInterval(updateInterval);
			}
		});

		seekBar.addEventListener('input', () => {
			audio.currentTime = seekBar.value;
			currentTimeEl.textContent = formatTime(audio.currentTime);
			updateSeekBarProgress();
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
	}

	const builtInBot = {
		id: "wchat-built-in",
		username: "Built In",
		profile_picture: "/uploads/profile-pictures/default-profile.png",
		commands: [
			{
				command: "/help",
				options: "[]",
				description: "Show all available commands",
				builtIn: true
			},
			{
				command: "/update-info",
				options: "[]",
				description: "Show update and version information",
				builtIn: true
			}
		]
	};

	socket.on("server_commands_response", async (data) => {
		if (data.success) {
			available_commands = [builtInBot, ...data.bots];

			commandsManager.init(available_commands);
		}
	});

	socket.on("new_message", async (msg) => {

		if (msg.channel_id !== channel_id) return;

		if (channel_type === "voice") {
			return;
		}

		const isAtBottom = (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) < 5;

		await handleMessage(msg);

		if (isAtBottom) {
			messageContainer.scrollTop = messageContainer.scrollHeight - messageContainer.clientHeight;
		}
	});

	socket.on("message_deleted", (message_id) => {
		deleteMsg(message_id.message_id);
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

	let typingInterval;

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
				typingInterval = setInterval(() => {
					if (typing) {
						socket.emit('typing', { access_token, typing: true, channel_id, server_id });
					} else {
						clearInterval(typingInterval);
					}
				}, 10000);

			} else if (!value.length && typing) {
				typing = false;
				socket.emit('typing', { access_token, typing: false, channel_id, server_id });
				clearInterval(typingInterval);
			}
		};

		const stopTyping = () => {
			if (typing) {
				typing = false;
				socket.emit('typing', { access_token, typing: false, channel_id, server_id });
				clearInterval(typingInterval);
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
				commandsManager.show(textarea.innerText, textarea);
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
				textareaFormatter.caret_end(textarea);
				const afterAt = text.slice(lastAtIndex + 1);
				const query = afterAt.split(/\s|\n/)[0];
				textarea.style.color = "var(--clr-text-a0)";
				preview.style.display = "none";
				mentions.show(query, usersList, sanitizer);
			} else {
				mentions.hide();
				textarea.style.color = "transparent";
				preview.style.display = "block";
			}
		};

		textarea.addEventListener('input', (e) => {
			if (e.target !== textarea) return;

			if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
				textarea.innerHTML = '';
				mentions.hide();
			}

			updateHeight();
			updateTypingStatus();
			updateCharsLeft();
			handleCommandAutocomplete();
			handleMentions();

			preview.innerHTML = textareaFormatter.format(textarea.innerText);
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
				mentions.hide();
				
				preview.innerHTML = "";
				textarea.innerText = "";
				updateHeight();
				renderPreviews();
				stopTyping();
				return;
			}

			if (e.key === ' ' && text.startsWith('/')) {
				const matches = commandsManager.show.lastMatches;
				if (matches?.length === 1) {
					textarea.innerText = matches[0].command + " ";
					const popup = document.querySelector(".available-commands");
					if (popup) popup.remove();
					e.preventDefault();
				}
			}

			if (e.key === 'Enter') {
				const matches = commandsManager.show.lastMatches;
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
			stopTyping();
		});

		textarea.addEventListener('input', (e) => {
			if (e.target !== textarea) return;

			if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
				textarea.innerHTML = '';
				mentions.hide();
			}

			updateHeight();
			updateTypingStatus();
			updateCharsLeft();
			handleCommandAutocomplete();
			handleMentions();

			preview.innerHTML = textareaFormatter.format(textarea.innerText);
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

		const observer = new MutationObserver(() => {
			updateHeight();
			updateCharsLeft();
			preview.innerHTML = textareaFormatter.format(textarea.innerText);
		});

		observer.observe(textarea, {
			childList: true,
			subtree: true,
			characterData: true
		});
	}

	function deleteMsg(id) {
		const indexInCache = messageCache.delete(id);
		if (indexInCache === -1) return;

		const nextMsg = messageCache.cache[indexInCache];
		if (!nextMsg) return;

		const nextEl = nextMsg.el;
		const usernameDateEl = nextEl.querySelector('.username-date');
		if (usernameDateEl) usernameDateEl.style.display = 'flex';

		const profilePic = nextEl.querySelector('.profile-picture');
		if (profilePic) {
			profilePic.style.opacity = '1';
			profilePic.style.height = '30px';
		}

		const prevMsg = messageCache.cache[indexInCache - 1];
		if (prevMsg && prevMsg.el && prevMsg.sent_by === nextMsg.el.dataset.sentBy) {
			const prevTime = new Date(prevMsg.timestamp).getTime();
			const currTime = new Date(nextMsg.timestamp).getTime();
			if ((currTime - prevTime) <= 10 * 60 * 1000) {
				if (!nextEl.querySelector('.message-command') && !nextEl.querySelector('.message-reply')) {
					nextEl.classList.add('compact');
				}
			} else {
				nextEl.classList.remove('compact');
			}
		} else {
			nextEl.classList.remove('compact');
		}
	}

	function send_message(textareaEl, uploadedFileNames = null) {
		const message = textareaFormatter.cleanMsg(textareaEl);

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
			messageEl.querySelector(".message-text").innerHTML = await sanitizer.sanitizeMsg(message, usersList, user_id, channels, server_id);
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
			const parent_message_user = parentMessageInfo.sender ?? usersList.find(user => user.id == parentMessageInfo.sender_id)?.username ?? "Someone";

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
				<div class="self-info-profile-username-container"><p class="self-info-username">${user.display_name ? sanitizer.sanitize(user.display_name) : sanitizer.sanitize(user.username)}</p>
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

			const existingPopup = document.querySelector(`.user-info-profile-popup[data-user-id='${user.id}']`);
			if (existingPopup) {
				existingPopup.remove();
				userEl.classList.remove("active");
				return;
			}

			document.querySelector(".user-info-profile-popup")?.remove();

			const popup = await userPopupManager.openUserPopup(user);

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

		sanitizer.init(users);
		mentions.init(users);
		commandsManager.initU(users);

		await Promise.all(users.map(user => renderUser(user)));
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

	if (document.getElementById("new-category")) document.getElementById("new-category").addEventListener("click", openCreateCategoryModal);

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

	if (document.getElementById("new-channel")) document.getElementById("new-channel").addEventListener("click", openCreateChannelModal);

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

	const serverSettings = document.getElementById("server-settings");
	if (serverSettings) {
		serverSettings.addEventListener("click", async () => {
			await settingsManager.open();
		});
	}

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
if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initServer();
	});
}
initServer();