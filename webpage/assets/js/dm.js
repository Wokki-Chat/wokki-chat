import emojis from "./emojis.js";
import { MessageHandler } from "./modules/servers/messages.js";

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

	const messageContainer = document.querySelector("#message-container");

	const textarea = document.getElementById("message-input");
	const preview = document.getElementById("message-input-bg");

	const messageHandler = new MessageHandler({ user_id, access_token, socket, messageContainer, contact_id: contact_id, textarea: textarea });

	let offset = 0;
	const limit = 25;

	async function loadMessages(offsetValue = 0) {
		socket.emit("get_messages", {
			access_token,
			contact_id: contact_id,
			offset: offsetValue
		});
		socket.emit("change_room", {
			access_token,
			contact_id: contact_id
		});
	}

	(async () => {
		await emojis.load();
	})();

	loadMessages();

	messageContainer.addEventListener("scroll", () => {
		if (messageContainer.scrollTop === 0) {
			offset += limit;
			socket.emit("get_messages", {
				access_token,
				contact_id: contact_id,
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
			await messageHandler.handleMessage(msg);
		}
	}

	textarea.addEventListener('keydown', async (e) => {
		if (e.key === "Enter" && !e.shiftKey) {
			e.preventDefault();

			messageHandler.send();
			
			preview.innerHTML = "";
			textarea.innerText = "";
			return;
		}
	});
}
if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initDm();
	});
}
initDm();