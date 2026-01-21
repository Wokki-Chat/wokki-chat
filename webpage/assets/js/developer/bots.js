function init_bots() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('developer/bots.js')) return;

    top_bar_profile = document.querySelector(".top-bar-profile");
    if (top_bar_profile) {
        top_bar_profile.addEventListener("click", () => {
            const dropdown = document.querySelector(".top-bar-profile-dropdown");
            if (dropdown) {
                dropdown.classList.toggle("active");
            }
        });
    }

    const sidebar_items = document.querySelectorAll('.sidebar-item');
    const currentPage = document.getElementById('page')?.value;

	console.log(currentPage);

	if (currentPage) {
		sidebar_items.forEach(el => el.classList.remove('active'));
		sidebar_items.forEach(el => {
			console.log(el.getAttribute('href'));
			if (el.getAttribute('href') === `${currentPage}`) {
				el.classList.add('active');
			}
		});
	}

	const access_token = document.getElementById("access-token").getAttribute("value");
	const user_id = document.getElementById("user-id").getAttribute("value");
	function openCreateBotModal() {
		let modalHtml = `
		<div class="modal" id="create-bot-modal">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title">Create Bot</h2>
					<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
				</div>
				<div class="modal-body" >
					<form id="create-bot-form" class="create-channel-form">
						<label for="bot-icon">Bot Icon (optional):</label>
						<label for="bot-icon" id="icon-preview" class="icon-preview" >
							<span class="material-symbols-rounded upload-icon">add</span>
						</label>
						<input type="file" id="bot-icon" name="bot-icon" accept="image/jpeg,image/png,image/gif" class="input-file-dark-bg file-input" style="display: none;">
						<label for="bot-name">Bot Name:</label>
						<input type="text" id="bot-name" name="bot-name" class="input-text-dark-bg w270" required maxlength="50" placeholder="Bot name (up to 50 characters)">
						<button type="submit" class="button-primary-filled">Create</button>
					</form>
				</div>
			</div>
		</div>
		`;

		document.body.insertAdjacentHTML("beforeend", modalHtml);

		const modal = document.getElementById("create-bot-modal");
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

		const botIconInput = document.getElementById("bot-icon");
		const iconPreview = document.getElementById("icon-preview");

		botIconInput.addEventListener("change", () => {
		const file = botIconInput.files[0];
		if (file && file.type.startsWith("image/")) {
			const img = new Image();
			img.onload = () => {
			if (img.width < 50 || img.height < 50) {
				Toastify({
				text: "Image dimensions must be at least 50x50px.",
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
				botIconInput.value = "";
				iconPreview.innerHTML = `<span class="material-symbols-rounded upload-icon">add</span>`;
			} else {
				iconPreview.innerHTML = `<img src="${img.src}" alt="Bot Icon" style="width: 100%; height: 100%; object-fit: cover;">`;
			}
			};
			const reader = new FileReader();
			reader.onload = e => {
			img.src = e.target.result;
			};
			reader.readAsDataURL(file);
		} else {
			iconPreview.innerHTML = `<span class="material-symbols-rounded upload-icon">add</span>`;
		}
		});

		const createBotForm = document.getElementById("create-bot-form");
		createBotForm.addEventListener("submit", (event) => {
		event.preventDefault();

		const bot_name = document.getElementById("bot-name").value;
		const botIconFile = botIconInput.files[0];

		const formData = new FormData();
		formData.append("bot_name", bot_name);
		if (botIconFile) {
			formData.append("profile_picture", botIconFile);
		}

		fetch("https://chat.wokki20.nl/app/create_bot.php", {
			method: "POST",
			headers: {
				Authorization: `Bearer ${access_token}`,
			},
			body: formData,
		})
		.then((response) => response.json())
		.then((data) => {
			const bot_id = data.bot_id;

			window.location.href = `https://chat.wokki20.nl/developer/bot/${bot_id}`;
		})
		.catch((error) => {
			Toastify({
				text: "Failed to create bot.",
				duration: 5000,
				gravity: "bottom",
				position: "right",
				close: true,
				stopOnFocus: true,
				style: {
				background: "var(--clr-popup-a20)",
				borderRadius: "12px",
				boxShadow: "none",
				},
			}).showToast();
		})
		.finally(() => {
			modal.remove();
		});
			modal.remove();
		});
	}

	const addBot = document.getElementById("add-bot");
	addBot.addEventListener("click", openCreateBotModal);
}

init_bots();