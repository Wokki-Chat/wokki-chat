import { Sanitizer, TextareaFormatter } from "./modules/global/sanitization.js";
import { NotificationsManager } from "./modules/global/notifications.js";
import Dev from "./modules/global/dev.js";
import DevtoolsPanel from "./modules/settings/devtools.js";
function initSettings() {
	const el = document.querySelector('wchat-allowed-scripts');
	const scripts = el.getAttribute('value').split(';');
	if (!scripts.includes('settings.js')) return;

	const serverBar = document.querySelector(".server-bar");
	if (serverBar) serverBar.classList.add("hidden");

	document.addEventListener('keydown', event => {
		if (event.key === 'Escape') {
			window.swup.navigate(returnUrl, { cache: { read: false, write: true } });
			event.preventDefault();
		}
	});

	const sidebarMenuButton = document.getElementById('settings-sidebar-button');
	const settingsTabs = document.querySelector('.settings-tabs');

	if (sidebarMenuButton && settingsTabs) {
		sidebarMenuButton.replaceWith(sidebarMenuButton.cloneNode(true));

		const newButton = document.getElementById('settings-sidebar-button');
		newButton.addEventListener('click', () => {
			settingsTabs.classList.toggle('active');
			newButton.textContent = settingsTabs.classList.contains('active') ? 'close' : 'menu';
		});
	}
  
	const access_token = document.getElementById("access-token").getAttribute("value");
	const user_id = document.getElementById("user-id").getAttribute("value");
	const premium = JSON.parse(document.getElementById("premium").getAttribute("value"));
	const returnUrl = document.getElementById("return-url").getAttribute("value");
	const active_tab = document.getElementById("active-tab").getAttribute("value");
	const connections = JSON.parse(document.getElementById("connections").getAttribute("value"));

	const sanitizer = new Sanitizer();
	const textareaFormatter = new TextareaFormatter();
		
	const notificationsManager = new NotificationsManager({ access_token: access_token, socket: socket });
	notificationsManager.listen();

	const devtoolsPanel = new DevtoolsPanel({ access_token: access_token, socket: socket, worker_name: serverName });

	const dev = new Dev();
	dev.init();

	if (active_tab === "account") {

		socket.on("user_updated", (user) => {
			if (user.id !== user_id) return;
			document.querySelector(".user-info-profile-popup-status-circle-inner").style.backgroundColor = `var(--clr-status-${user.status.toLowerCase()})`;
			document.querySelector(".user-info-profile-popup-status").textContent = user.status.charAt(0).toUpperCase() + user.status.slice(1);
		});
		const textarea = document.getElementById("message-input");
		const preview = document.getElementById("message-input-bg");

		const minHeight = 18;

		if (textarea) {
			const maxHeight = 250;
			const warningThreshold = 200;
			const maxChars = 200;

			const messageInputWrapper = document.querySelector('.message-input-wrapper');

			const maxMessageLengthEl = document.querySelector('.max-message-length');
			const maxCharactersLeftEl = document.querySelector('.max-characters-left');

			document.querySelector(".input-container-2").addEventListener("click", () => textarea.focus());
			
			const inputContainer = document.querySelector(".input-container-2");

			const updateHeight = () => {
				let newHeight = Math.min(textarea.scrollHeight, maxHeight);
				messageInputWrapper.style.height = newHeight + 'px';
				inputContainer.style.minHeight = newHeight + 'px';
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

			textarea.addEventListener('input', (e) => {
				if (e.target !== textarea) return;

				if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
					textarea.innerHTML = '';
				}

				updateHeight();
				updateCharsLeft();

				preview.innerHTML = textareaFormatter.format(textarea.innerText);
			});

			textarea.addEventListener('input', (e) => {
				if (e.target !== textarea) return;

				if (textarea.textContent.trim() === '' && textarea.innerHTML !== '') {
					textarea.innerHTML = '';;
				}

				updateHeight();
				updateCharsLeft();

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

			textarea.addEventListener('keydown', (e) => {
				if (e.key === 'Enter') {
					e.preventDefault();

					const selection = window.getSelection();
					if (!selection.rangeCount) return;

					const range = selection.getRangeAt(0);
					range.deleteContents();

					const textNode = document.createTextNode('\n\n');
					range.insertNode(textNode);

					range.setStartAfter(textNode);
					range.setEndAfter(textNode);
					selection.removeAllRanges();
					selection.addRange(range);

					textarea.dispatchEvent(new Event('input'));
				}
			});

			updateHeight();
			updateCharsLeft();
			preview.innerHTML = textareaFormatter.format(textarea.innerText);
		}
		Coloris({
			theme: 'default',
			themeMode: 'dark',
			format: 'hex',
			formatToggle: false,
			alpha: false,
			forceAlpha: false,
			parent: '.setting-page',
			onChange: (color, input) => updateProfileColor(color, input),
			el: '#profile-color-primary, #profile-color-accent'
		});

		const updateProfileColor = (color, input) => {
			const userProfileStatic = document.querySelector(".user-info-profile-static");
			if (input.id === "profile-color-accent") {
				const profileColorAccent = document.getElementById("profile-color-accent");
				profileColorAccent.style.backgroundColor = color;
				userProfileStatic.style.border = `4px solid ${color}`;
			} else if (input.id === "profile-color-primary") {
				const profileColorPrimary = document.getElementById("profile-color-primary");
				profileColorPrimary.style.backgroundColor = color;
				let r = parseInt(color.slice(1,3),16);
				let g = parseInt(color.slice(3,5),16);
				let b = parseInt(color.slice(5,7),16);
				r = Math.max(0, r - r * 0.1);
				g = Math.max(0, g - g * 0.1);
				b = Math.max(0, b - b * 0.1);
				let darker = `#${((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1)}`;
				userProfileStatic.style.backgroundColor = darker;

				let brightness = (r*299 + g*587 + b*114) / 1000;
				lightText = brightness <= 150;
				userProfileStatic.dataset.lightText = lightText;
				userProfileStatic.dataset.customStyle = 'true';

				const username = document.querySelector(".user-info-profile-popup-username");
				username.style.color = lightText ? "var(--clr-white-a0)" : "var(--clr-black-a0)";

				const dmProfileLink = document.querySelector(".dm-info-profile-link");
				dmProfileLink.style.color = lightText ? "rgb(255, 255, 255)" : "rgb(0, 0, 0)";
				dmProfileLink.style.backgroundColor = "rgba(255, 255, 255, 0.1)";
				dmProfileLink.style.border = "1px solid rgba(255, 255, 255, 0.3)";
				dmProfileLink.dataset.customStyle = 'true';

				const dmInfoContainer = document.querySelector(".dm-info-container");
				dmInfoContainer.style.backgroundColor = "rgba(255, 255, 255, 0.1)";
				dmInfoContainer.style.border = "none";
			}
			showSaveResetButtons();
		};

		const profile_picture_input = document.getElementById("profile-picture");
		const banner_picture_input = document.getElementById("banner-picture");
		const display_name_input = document.getElementById("display-name");
		const profilePicturePreview = document.querySelector(".dm-info-profile-picture");
		const bannerPicturePreview = document.querySelector(".dm-info-banner-picture-upload");
		const bioInput = document.getElementById("message-input");

		const displayNameOnProfile = document.querySelector(".user-info-profile-popup-username");
		const displayNameOnMessage = document.querySelector(".username");

		const messageProfilePicture = document.querySelector(".message.message-static .message-content .profile-picture");

		const bioProfile = document.getElementById("bio-profile");
		const bannerProfile = document.querySelector(".user-info-profile-popup-banner");

		const deleteBannerButton = document.getElementById("profile-banner-option-delete");

		let originalProfilePicture = profile_picture_input.getAttribute("value");
		let originalBannerPicture = banner_picture_input.getAttribute("value");
		let originalDisplayName = display_name_input.getAttribute("value");
		let originalUsername = display_name_input.getAttribute("placeholder");
		let originalBio = bioInput.textContent;
		
		let originalProfileColorPrimary = document.getElementById("profile-color-primary").getAttribute("value");
		let originalProfileColorAccent = document.getElementById("profile-color-accent").getAttribute("value");
		let selectedProfileFile = null;
		let selectedBannerFile = null;

		const accountFormChanged = () => {
			return display_name_input.value !== originalDisplayName || profilePicturePreview.dataset.tempSrc || bannerPicturePreview.dataset.tempSrc || bioInput.textContent !== originalBio;
		};

		const showSaveResetButtons = () => {
			let container = document.querySelector(".unsaved-changes-container");
			if (!container) {
				container = document.createElement("div");
				container.className = "unsaved-changes-container";

				const message = document.createElement("span");
				message.className = "unsaved-changes-message";
				message.textContent = "You have unsaved changes. Please save or reset before leaving.";

				const buttonsDiv = document.createElement("div");
				buttonsDiv.className = "unsaved-changes-buttons";

				const resetBtn = document.createElement("button");
				resetBtn.textContent = "Reset";
				resetBtn.className = "link reset-account-changes-button";
				const saveBtn = document.createElement("button");
				saveBtn.textContent = "Save";
				saveBtn.className = "button-primary-filled";

				buttonsDiv.appendChild(resetBtn);
				buttonsDiv.appendChild(saveBtn);

				container.appendChild(message);
				container.appendChild(buttonsDiv);

				document.querySelector(".setting-page").appendChild(container);

				resetBtn.addEventListener("click", () => {
					display_name_input.value = originalDisplayName;
					if (profilePicturePreview.dataset.tempSrc) {
						profilePicturePreview.src = originalProfilePicture;
						messageProfilePicture.src = originalProfilePicture;
						delete profilePicturePreview.dataset.tempSrc;
						delete profilePicturePreview.dataset.tempFile;
					}
					if (bannerPicturePreview.dataset.tempSrc) {
						bannerPicturePreview.src = originalBannerPicture;
						bannerProfile.src = originalBannerPicture;
						delete bannerPicturePreview.dataset.tempSrc;
						delete bannerPicturePreview.dataset.tempFile;
					}
					if (displayNameOnProfile) displayNameOnProfile.textContent = originalDisplayName !== "" ? originalDisplayName : originalUsername;
					if (displayNameOnMessage) displayNameOnMessage.textContent = originalDisplayName !== "" ? originalDisplayName : originalUsername;
					if (bioInput) bioInput.textContent = originalBio;
					if (preview) preview.innerHTML = textareaFormatter.format(originalBio);
					container.remove();
				});

				saveBtn.addEventListener("click", () => {
					const formData = new FormData();
					if (profilePicturePreview.dataset.tempFile) {
						formData.append("profile_picture", selectedProfileFile);
					}
					if (bannerPicturePreview.dataset.tempFile) {
						formData.append("banner_picture", selectedBannerFile);
					}
					if (display_name_input.value !== originalDisplayName) {
						formData.append("display_name", display_name_input.value);
					}
					function rgbToHex(rgb) {
						const matches = rgb.match(/\d+/g);
						if (!matches) return; 
						const [r, g, b] = matches.map(Number);
						return "#" + [r, g, b].map(x => x.toString(16).padStart(2, "0")).join("");
					}


					const colorPrimary = rgbToHex(document.getElementById("profile-color-primary").style.backgroundColor);
					const colorAccent = rgbToHex(document.getElementById("profile-color-accent").style.backgroundColor);
					if (colorPrimary !== originalProfileColorPrimary) formData.append("profile_color_primary", colorPrimary);
					if (colorAccent !== originalProfileColorAccent) formData.append("profile_color_accent", colorAccent);

					if (bioInput.textContent !== originalBio) {
						formData.append("bio", bioInput.textContent);
					}

					fetch("/app/update_profile", {
						method: "POST",
						headers: {
							"Authorization": `Bearer ${access_token}`
						},
						body: formData
					})
					.then(res => res.json())
					.then(data => {
						if (data.status === "success") {
							originalDisplayName = display_name_input.value;
							if (profilePicturePreview.dataset.tempSrc) {
								originalProfilePicture = profilePicturePreview.src;
								delete profilePicturePreview.dataset.tempSrc;
								delete profilePicturePreview.dataset.tempFile;
							}
							if (bannerPicturePreview.dataset.tempSrc) {
								originalBannerPicture = bannerPicturePreview.src;
								delete bannerPicturePreview.dataset.tempSrc;
								delete bannerPicturePreview.dataset.tempFile;
							}
							if (bioInput.textContent !== originalBio) {
								originalBio = bioInput.textContent;
							}
							if (colorPrimary !== originalProfileColorPrimary) {
								originalProfileColorPrimary = colorPrimary;
							}
							if (colorAccent !== originalProfileColorAccent) {
								originalProfileColorAccent = colorAccent;
							}
							container.remove();
						} else {
							throw new Error(data.message || "Update failed");
						}
					})
					.catch(() => {
						alert("Something went wrong!");
					});
				});
			}
		};

		if (profilePicturePreview) {
			profilePicturePreview.addEventListener("click", () => {
				const fileInput = document.createElement("input");
				fileInput.type = "file";
				fileInput.accept = premium
					? "image/png,image/jpeg,image/jpg,image/webp,image/gif"
					: "image/png,image/jpeg,image/jpg,image/webp";
				fileInput.style.display = "none";
				document.body.appendChild(fileInput);
				fileInput.click();

				fileInput.addEventListener("change", () => {
					const file = fileInput.files[0];
					if (!file) return;

					const allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp"];
					if (premium) allowedTypes.push("image/gif");

					if (!allowedTypes.includes(file.type)) {
						alert("File type not allowed.");
						return;
					}

					const reader = new FileReader();
					reader.onload = e => {
						profilePicturePreview.src = e.target.result;
						profilePicturePreview.dataset.tempSrc = e.target.result;
						profilePicturePreview.dataset.tempFile = file;

						messageProfilePicture.src = e.target.result;

                		selectedProfileFile = file;
						showSaveResetButtons();
					};
					reader.readAsDataURL(file);
				});
			});
		}

		if (bannerPicturePreview) {
			bannerPicturePreview.addEventListener("click", () => {
				const fileInput = document.createElement("input");
				fileInput.type = "file";
				fileInput.accept = premium
					? "image/png,image/jpeg,image/jpg,image/webp,image/gif"
					: "image/png,image/jpeg,image/jpg,image/webp";
				fileInput.style.display = "none";
				document.body.appendChild(fileInput);
				fileInput.click();

				fileInput.addEventListener("change", () => {
					const file = fileInput.files[0];
					if (!file) return;

					const allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp"];
					if (premium) allowedTypes.push("image/gif");

					if (!allowedTypes.includes(file.type)) {
						alert("File type not allowed.");
						return;
					}

					const reader = new FileReader();
					reader.onload = e => {
						bannerPicturePreview.src = e.target.result;
						bannerPicturePreview.dataset.tempSrc = e.target.result;
						bannerPicturePreview.dataset.tempFile = file;

						bannerProfile.src = e.target.result;

                		selectedBannerFile = file;
						showSaveResetButtons();
					};
					reader.readAsDataURL(file);
				});
			});
		}

		bioInput.addEventListener("input", async () => {
			if (bioProfile) {
				let text = bioInput.textContent;
				text = await sanitizer.sanitizeMrk(text);
				bioProfile.innerHTML = text || "You have no bio yet.";
			}
			if (accountFormChanged()) showSaveResetButtons();
		});

		display_name_input.addEventListener("input", () => {
			if (displayNameOnProfile && display_name_input.value !== "") displayNameOnProfile.textContent = sanitizer.sanitize(display_name_input.value);
			else if (displayNameOnProfile) displayNameOnProfile.textContent = originalDisplayName !== "" ? sanitizer.sanitize(originalDisplayName) : sanitizer.sanitize(originalUsername);

			if (displayNameOnMessage && display_name_input.value !== "") displayNameOnMessage.textContent = sanitizer.sanitize(display_name_input.value);
			else if (displayNameOnMessage) displayNameOnMessage.textContent = originalDisplayName !== "" ? sanitizer.sanitize(originalDisplayName) : sanitizer.sanitize(originalUsername);
			if (accountFormChanged()) showSaveResetButtons();
		});

		if (deleteBannerButton) {
			deleteBannerButton.addEventListener("click", () => {
				const image = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAsAAAAGMAQMAAADuk4YmAAAAA1BMVEX///+nxBvIAAAAAXRSTlMAQObYZgAAADlJREFUeF7twDEBAAAAwiD7p7bGDlgYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAwAGJrAABgPqdWQAAAABJRU5ErkJggg==";
				bannerPicturePreview.src = image;
				bannerPicturePreview.dataset.tempSrc = image;
				bannerPicturePreview.dataset.tempFile = null;
				bannerProfile.src = image;
				selectedBannerFile = null;
				if (accountFormChanged()) showSaveResetButtons();
			});
		}

		(async () => {
			bioProfile.innerHTML = (await sanitizer.sanitizeMrk(bioInput.textContent)) || "You have no bio yet.";
		})();

	}

	if (active_tab === "media") {
		const deleteButtons = document.querySelectorAll(".file-remove-icon");
		const shareButtons = document.querySelectorAll(".media-share-icon");

		shareButtons.forEach(shareButton => {
			shareButton.addEventListener("click", async () => {
				try {
					const url = `${window.location.origin}/uploads/messages/${shareButton.dataset.savedName}`;
					await navigator.clipboard.writeText(url);
					jspt.makeToast({
						message: "Link copied to clipboard!",
						style: "success",
						duration: 3000
					});
				} catch (err) {
					jspt.makeToast({
						message: "Failed to copy link, try again later.",
						style: "error",
						duration: 3000
					})
				}
			});
		});
		
		deleteButtons.forEach(deleteButton => {
			deleteButton.addEventListener("click", async () => {
				try {
					const formData = new FormData();
					formData.append('savedName', deleteButton.dataset.savedName);

					const response = await fetch('/app/delete_file', {
						method: 'POST',
						headers: {
							"Authorization": `Bearer ${access_token}`
						},
						body: formData
					});

					if (response.ok) {
						deleteButton.parentElement.parentElement.remove();
					}
				} catch (err) {
					jspt.makeToast({
						message: "Failed to delete file, try again later.",
						style: "error",
						duration: 3000
					})
				}
			});
		});
	}

	if (active_tab === "devtools") {
		const connectedWorkerEl = document.getElementById("connected-worker");
		const connectedServerEl = document.getElementById("connected-server");
		const switchServerBtn = document.getElementById("switch-server-btn");
		devtoolsPanel.init({ connectedWorkerEl, connectedServerEl, switchServerBtn });
	}

	if (active_tab === "connections") {
		const chatAction = document.getElementById("chat-action");
		const spotifyAction = document.getElementById("spotify-action");
		const githubAction = document.getElementById("github-action");

		function handleAction(element) {
			const action = element.dataset.action;

			if (typeof window[action] === "function") {
				window[action]();
			} else {
				try {
					eval(action);
				} catch (e) {
					console.error("Failed to execute action:", e);
				}
			}
		}

		chatAction.addEventListener("click", () => handleAction(chatAction));
		spotifyAction.addEventListener("click", () => handleAction(spotifyAction));
		githubAction.addEventListener("click", () => handleAction(githubAction));
	}

	function openConnectionModal(connection) {
		connections.forEach(conn => {
			if (conn["connection_name"] === connection) {
				connection = conn;
			}
		})
		let spotifyToggleHtml = '';
		let githubToggleHtml = '';
		if (connection["connection_name"] === "Spotify") {
			spotifyToggleHtml = `
			<div class="connection-modal-spotify-toggle">
				<div class="option-description-container">
					<p class="option-title">Show on profile</p>
					<p class="option-description">Show what you're listening to on your profile</p>
				</div>
				<div class="container">
					<label class="switch" for="spotify-checkbox">
						<input type="checkbox" id="spotify-checkbox" ${connection["show_on_profile"] ? "checked" : ""} />
						<div class="slider round"></div>
					</label>
				</div>
			</div>
			`;
		}
		if (connection["connection_name"] === "GitHub") {
			githubToggleHtml = `
			<div class="connection-modal-spotify-toggle">
				<div class="option-description-container">
					<p class="option-title">Show on profile</p>
					<p class="option-description">Show your GitHub commit graph on your profile</p>
				</div>
				<div class="container">
					<label class="switch" for="github-checkbox">
						<input type="checkbox" id="github-checkbox" ${connection["show_on_profile"] ? "checked" : ""} />
						<div class="slider round"></div>
					</label>
				</div>
			</div>
			`;
		}

		let modalHtml = `
		<div class="modal" id="connection-modal">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title">${connection["connection_name"]}</h2>
					<span class="close-modal-btn material-symbols-rounded" id="close-modal-btn">close</span>
				</div>
				<div class="modal-body connection-modal">
					<img src="${connection["connection_user_image"] || "../assets/icons/svg/questionmark.svg"}" alt="Connection Image" class="connection-modal-userimage">
					<p class="connection-modal-username">${connection["connection_user_name"]}</p>
					<div class="connection-modal-connected-since">
						<p class="connection-modal-connected-since-title">Connected since:</p>
						<p class="connection-modal-connected-since-date">${formatFullDate(connection["connected_at"])}</p>
					</div>
					${spotifyToggleHtml}
					${githubToggleHtml}
					<div class="connection-modal-buttons">
						<button class="connection-modal-button button-primary-filled" onclick="window.open('${connection["connection_user_url"]}', '_blank')">Open Profile Page</button>
						<button class="connection-modal-button button-primary-outline" data-id="${connection["connection_name"]}" id="unlink-connection-btn">Unlink</button>
					</div>
				</div>
			</div>
		</div>
		`;

		document.body.insertAdjacentHTML("beforeend", modalHtml);

		const unlinkConnectionBtn = document.getElementById("unlink-connection-btn");
		unlinkConnectionBtn.addEventListener("click", () => unlinkConnection(unlinkConnectionBtn.dataset.id));

		const modal = document.getElementById("connection-modal");
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

		const spotifyCheckbox = document.getElementById("spotify-checkbox");
		if (spotifyCheckbox) {
			spotifyCheckbox.addEventListener("change", () => {
				const formData = new FormData();
				formData.append("spotify_widget_show", spotifyCheckbox.checked ? "true" : "false");

				fetch("/app/widgets", {
					method: "POST",
					headers: {
						"Authorization": `Bearer ${access_token}`
					},
					body: formData
				});
			});
		}

		const githubCheckbox = document.getElementById("github-checkbox");
		if (githubCheckbox) {
			githubCheckbox.addEventListener("change", () => {
				const formData = new FormData();
				formData.append("github_widget_show", githubCheckbox.checked ? "true" : "false");

				fetch("/app/widgets", {
					method: "POST",
					headers: {
						"Authorization": `Bearer ${access_token}`
					},
					body: formData
				});
			});
		}
	}

  function unlinkConnection(connection) {
      const connectionNameLower = connection.toLowerCase();
      window.location.href = `/connections/${connectionNameLower}_unlink`;
  }
}
if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initSettings();
	});
}
initSettings();