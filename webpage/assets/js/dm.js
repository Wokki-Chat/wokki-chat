import emojis from "./emojis.js";
import { MessageHandler } from "./modules/servers/messages.js";
import { Sanitizer, TextareaFormatter } from "./modules/global/sanitization.js";
import { Mentions } from "./modules/users/mentions.js";
import { StaticProfileManager } from "./modules/users/profiles.js";
import { UserRenderer } from "./modules/users/profiles.js";
import { NotificationsManager } from "./modules/global/notifications.js";
import ReactionRenderer from "./modules/servers/reactions.js";
import Dev from "./modules/global/dev.js";
import * as jspt from "https://cdn.wokki20.nl/content/jspt-v2.1.0/jspt.module.js";
function initDm() {
    const el = document.querySelector('wchat-allowed-scripts');
    const scripts = el.getAttribute('value').split(';');
    if (!scripts.includes('dm.js')) return;

    const serverBar = document.querySelector(".server-bar");
    if (serverBar && window.matchMedia("(min-width: 768px)").matches) {
        serverBar.classList.remove("hidden");
    } else {
        serverBar.classList.add("hidden");
    }
    
    const access_token = document.getElementById("access-token").getAttribute("value");
    const contact_id = document.getElementById("contact-id").getAttribute("value");
    const user_id = document.getElementById("user-id").getAttribute("value");
    const premium = JSON.parse(document.getElementById("premium")?.getAttribute("value") || "false");

    const messageContainer = document.querySelector("#message-container");
    const textarea = document.getElementById("message-input");
    const preview = document.getElementById("message-input-bg");

	const contact_user_id = document.getElementById("contact-user-id").getAttribute("value");

	const contact_type = document.getElementById("contact-type").getAttribute("value");

	const usersContainer = document.querySelector(".users");

	const groupUsersContainer = document.querySelector(".group-users-content");

	const uploadContainer = document.querySelector(".input-container-2 .file-upload-container");

    const sanitizer = new Sanitizer(user_id, [], null);
    const textareaFormatter = new TextareaFormatter(user_id, [], null, textarea);
    const mentions = new Mentions({ user_id, channels: [], server_id: null, users_list: [] });
	const staticProfileManager = new StaticProfileManager(access_token, user_id);

	const userRenderer = new UserRenderer({ user_id, access_token, userListContainer: groupUsersContainer });
        
    const reactionRenderer = new ReactionRenderer({ user_id: user_id, access_token: access_token, socket: socket, contact_id: contact_id });

    const notificationsManager = new NotificationsManager({ access_token: access_token, socket: socket, contact_id: contact_id });

    const dev = new Dev();
    dev.init();

	const textareaEmojiOptions = document.getElementById("emoji-option");
	textareaEmojiOptions.addEventListener("click", async () => {
		await emojis.picker(textarea, textareaEmojiOptions, true);
	})
    
    const messageHandler = new MessageHandler({ 
        user_id: user_id, 
        access_token: access_token, 
        socket: socket, 
        messageContainer: messageContainer, 
        contact_id: contact_id, 
        textarea: textarea,
        uploadContainer: uploadContainer,
        premium: premium
    });

    let typing = false;
    let typingInterval;
    let offset = 0;
    const limit = 25;
    let selectedFiles = [];

	let usersList = [];

    async function loadMessages(offsetValue = 0) {
		messageHandler.initFileUpload();
        notificationsManager.listen();
        socket.emit("get_messages", {
            access_token,
            contact_id,
            offset: offsetValue
        });
        socket.emit("change_room", {
            access_token,
            contact_id
        });
    }

    (async () => {
        await emojis.load();
    })();

    loadMessages();

	socket.on("add_reaction", ({ message_id, reaction, user_id: reactingUserId }) => {
		const el = document.querySelector(`.message[data-message-id="${message_id}"]`);
		if (el) reactionRenderer.updateReactionUI(el, message_id, reaction, reactingUserId, false, messageContainer);
	});

	socket.on("remove_reaction", ({ message_id, reaction, user_id: reactingUserId }) => {
		const el = document.querySelector(`.message[data-message-id="${message_id}"]`);
		if (el) reactionRenderer.updateReactionUI(el, message_id, reaction, reactingUserId, true, messageContainer);
	});

	if (contact_type == "individual") {
		staticProfileManager.openStaticProfile(contact_user_id, usersContainer);
	} else {
		socket.emit("get_contact_users", { access_token, contact_id });

		socket.on("user_widget_updated", (data) => {
			if (!groupUsersContainer) return;
			const userIndex = usersList.findIndex(u => u.id === data.id);
			if (userIndex !== -1) {
				usersList[userIndex] = { ...usersList[userIndex], ...data };
				userRenderer.render(usersList[userIndex], groupUsersContainer);
			}

			const popup = document.querySelector(".user-info-profile-popup");
			if (popup && popup.dataset.userId === data.id) {
				const spotify = data.Spotify;
				if (spotify && spotify.item) userRenderer.updateSpotifyPopup(popup, spotify);
				else {
					const spotifyContainer = popup.querySelector(".dm-info-container .spotify-info")?.parentElement;
					if (spotifyContainer) spotifyContainer.remove();
					if (popup.spotifyInterval) clearInterval(popup.spotifyInterval);
				}
			}
		});

		socket.on("contact_users", async (users) => {
			if (!groupUsersContainer) return;
			groupUsersContainer.innerHTML = "";

			usersList = users;

			sanitizer.init(users);
			mentions.init(users);
			messageHandler.initU(users);

			await Promise.all(users.map(user => {
				userRenderer.render(user, groupUsersContainer);
			}));
		});

		socket.on("user_updated", async (user) => {
			if (!groupUsersContainer) return;
			
			userRenderer.render(user, groupUsersContainer);
		});
	}

    messageContainer.addEventListener("scroll", () => {
        if (messageContainer.scrollTop === 0) {
            offset += limit;
            socket.emit("get_messages", {
                access_token,
                contact_id,
                offset
            });
        }
    });

    socket.on("all_messages", async (messages) => {
        await messageHandler.handleAllMessages(messages, offset > 0);
    });

    socket.on("all_messages_nocache", async (messages) => {
        await messageHandler.handleAllMessages(messages, offset > 0);
    });

    if (textarea) {
		document.addEventListener("keydown", (e) => {
			if (e.target.tagName === "BODY" &&
				!e.target.closest("div[contenteditable]") &&
				e.target.tagName !== "INPUT" &&
				e.target.tagName !== "TEXTAREA"
			) {
				if (e.ctrlKey || e.metaKey || e.altKey || 
					e.key.length > 1 && e.key !== 'Enter' && e.key !== ' ') {
					return;
				}
				
				e.preventDefault();
				textarea.focus();
				
				const selection = window.getSelection();
				const range = selection.getRangeAt(0);
				const textNode = document.createTextNode(e.key);
				range.insertNode(textNode);
				range.setStartAfter(textNode);
				range.setEndAfter(textNode);
				selection.removeAllRanges();
				selection.addRange(range);
				
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
			}
		});
        const maxHeight = 250;
        const warningThreshold = 1000;
        const maxChars = premium ? 10000 : 3000;

        const messageInputWrapper = document.querySelector('.message-input-wrapper');
        const maxMessageLengthEl = document.querySelector('.max-message-length');
        const maxCharactersLeftEl = document.querySelector('.max-characters-left');

        document.querySelector(".input-container-2")?.addEventListener("click", () => textarea.focus());
        
        const inputContainer = document.querySelector(".input-container-2");

        const updateHeight = () => {
            let newHeight = Math.min(textarea.scrollHeight, maxHeight);
            if (messageInputWrapper) {
                messageInputWrapper.style.height = newHeight + 20 + 'px';
            }
            if (inputContainer) {
                inputContainer.style.minHeight = newHeight + 20 + 'px';
            }
        };

        const updateTypingStatus = () => {
            const value = textarea.innerText.trim();
            if (value.length && !typing) {
                typing = true;
                socket.emit('typing', { access_token, typing: true, contact_id });
                typingInterval = setInterval(() => {
                    if (typing) {
                        socket.emit('typing', { access_token, typing: true, contact_id });
                    } else {
                        clearInterval(typingInterval);
                    }
                }, 10000);
            } else if (!value.length && typing) {
                typing = false;
                socket.emit('typing', { access_token, typing: false, contact_id });
                clearInterval(typingInterval);
            }
        };

        const stopTyping = () => {
            if (typing) {
                typing = false;
                socket.emit('typing', { access_token, typing: false, contact_id });
                clearInterval(typingInterval);
            }
        };

        const updateCharsLeft = () => {
            const charsLeft = maxChars - textarea.innerText.length;
            if (maxMessageLengthEl && maxCharactersLeftEl) {
                if (charsLeft <= warningThreshold) {
                    maxMessageLengthEl.style.display = 'flex';
                    maxCharactersLeftEl.textContent = charsLeft;
                    maxCharactersLeftEl.classList.toggle('debt', charsLeft < 0);
                } else {
                    maxMessageLengthEl.style.display = 'none';
                    maxCharactersLeftEl.classList.remove('debt');
                }
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
                mentions.show(query, [], sanitizer);
            } else {
                mentions.hide();
                textarea.style.color = "transparent";
                if (preview) {
                    preview.style.display = "block";
                }
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
            handleMentions();

            if (preview) {
                preview.innerHTML = textareaFormatter.format(textarea.innerText);
            }
        });

        textarea.addEventListener('keydown', async (e) => {
            const text = textarea.innerText.trim();

            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();

                const uploadedNames = selectedFiles
                    .filter(f => f.savedName)
                    .map(f => ({ savedName: f.savedName, originalName: f.originalName }));

                messageHandler.send(uploadedNames.length ? uploadedNames : undefined);
                mentions.hide();
                
                if (preview) {
                    preview.innerHTML = "";
                }
                textarea.innerText = "";
                updateHeight();
				messageHandler.renderPreviews();
                stopTyping();
                return;
            }
        });

        textarea.addEventListener('blur', () => {
            stopTyping();
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
            if (preview) {
                preview.innerHTML = textareaFormatter.format(textarea.innerText);
            }
        });

        observer.observe(textarea, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    socket.on("send_message_response", (resp) => {
        if (!resp.success) {
            console.error("Failed to send message:", resp.error);

            if (resp.error?.includes("Rate limit exceeded")) {
                jspt.makeToast({
                    message: "Please wait before sending another message.",
                    style: "default-error",
                    duration: 3000,
                    close_on_click: true
                })
            }
        }
    });

	socket.on("new_message", async (msg) => {
		if (msg.contact_id !== contact_id) return;
		const isAtBottom = (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) < 5;

		await messageHandler.handleMessage(msg);

		if (isAtBottom) {
			messageContainer.scrollTop = messageContainer.scrollHeight - messageContainer.clientHeight;
		}
	});
	
	socket.on("message_deleted", (message_id) => {
		messageHandler.deleteMsg(message_id.message_id);
	});
}

if (typeof window.swup !== "undefined") {
    window.swup.hooks.on('page:view', (visit) => {
        initDm();
    });
}

initDm();