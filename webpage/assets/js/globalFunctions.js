async function highlightAll() {
  await hljs.highlightAll();
}

async function addCodeblockInfo() {
  const msgTexts = document.querySelectorAll(".message-text");

  for (const msgText of msgTexts) {
    const preBlocks = msgText.querySelectorAll("pre");

    for (const pre of preBlocks) {
      if (pre.querySelector(".lang-bar")) continue;

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
  }
}

function formatDate(created_at) {
  const now = new Date();
  const date = new Date(created_at);
  const diffMs = now - date;
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays > 7) {
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  if (diffDays === 1) {
    return `Yesterday at ${date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`;
  }

  if (diffDays >= 2) {
    return `${diffDays} days ago at ${date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`;
  }

  return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function formatFullDate(created_at) {
  const date = new Date(created_at);
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getAssetType(fileName) {
  const parts = fileName.toLowerCase().split('.');

  if (parts.length >= 3 && parts[parts.length - 1] === 'pfp' && ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'].includes(parts[parts.length - 2])) {
    return 'profile_picture';
  }
  
  const ext = parts[parts.length - 1];

  if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'].includes(ext)) return 'image';
  if (['mp4', 'webm', 'ogg'].includes(ext)) return 'video';
  if (['mp3', 'wav', 'ogg'].includes(ext)) return 'audio';
  if (['pdf', 'txt'].includes(ext)) return ext;
  return 'other';
}

function imageViewer(img_src, originalName) {
    if (!img_src) return;
    if (document.querySelector(".image-viewer-popup")) document.querySelector(".image-viewer-popup").remove();

    const imageViewer = `
        <div class="image-viewer-popup">
            <div class="image-viewer-popup-container">
                <div class="image-viewer-popup-options">
                    <div class="image-viewer-popup-option" id="image-viewer-popup-save">
                        <span class="material-symbols-rounded image-viewer-popup-option-icon">download</span>
                        <p class="image-viewer-popup-option-text">Save image</p>
                    </div>
                    <div class="image-viewer-popup-option" id="image-viewer-popup-new-tab">
                        <span class="material-symbols-rounded image-viewer-popup-option-icon">open_in_new</span>
                        <p class="image-viewer-popup-option-text">Open in new tab</p>
                    </div>
                    <div class="image-viewer-popup-option" id="image-viewer-popup-close">
                        <span class="material-symbols-rounded image-viewer-popup-option-icon">close</span>
                        <p class="image-viewer-popup-option-text">Close</p>
                    </div>
                </div>
                <div class="magnifier-circle" style="display: none;"></div>
                <img src="${img_src}" draggable="false" class="image-viewer-popup-image"/>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML("beforeend", imageViewer);

    const popup = document.querySelector(".image-viewer-popup");
    const image = popup.querySelector(".image-viewer-popup-image");
    const magnifier = popup.querySelector(".magnifier-circle");

    let zoomLevel = 1;
    let magnifierSize = 150;

    popup.addEventListener("click", (e) => {
        if (e.target === popup) popup.remove();
    });

    popup.querySelector(".image-viewer-popup-options").addEventListener("click", e => e.stopPropagation());
    image.addEventListener("click", e => e.stopPropagation());

    popup.querySelector("#image-viewer-popup-close").addEventListener("click", () => popup.remove());
    popup.querySelector("#image-viewer-popup-new-tab").addEventListener("click", () => window.open(img_src, '_blank'));
    popup.querySelector("#image-viewer-popup-save").addEventListener("click", () => {
        const a = document.createElement('a');
        a.href = img_src;
        a.download = originalName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    let isMagnifierActive = false;
    let lastMouseEvent = null;

    image.addEventListener("mousedown", (e) => {
        e.preventDefault();
        isMagnifierActive = true;
        magnifier.style.backgroundImage = `url('${img_src}')`;
        magnifier.style.display = "block";
        updateMagnifierPosition(e);
    });

    document.addEventListener("mouseup", () => {
        isMagnifierActive = false;
        magnifier.style.display = "none";
    });

    image.addEventListener("mousemove", (e) => {
        if (!isMagnifierActive) return;
        lastMouseEvent = e;
        updateMagnifierPosition(e);
    });


    image.addEventListener("wheel", (e) => {
        if (!isMagnifierActive) return;

        e.preventDefault();

        if (e.shiftKey) {
            const centerX = parseFloat(magnifier.style.left) + magnifierSize / 2;
            const centerY = parseFloat(magnifier.style.top) + magnifierSize / 2;

            magnifierSize += e.deltaY * -0.5;
            magnifierSize = Math.max(50, Math.min(400, magnifierSize));

            magnifier.style.width = magnifierSize + "px";
            magnifier.style.height = magnifierSize + "px";
            magnifier.style.left = (centerX - magnifierSize / 2) + "px";
            magnifier.style.top = (centerY - magnifierSize / 2) + "px";
        } else {
            zoomLevel *= e.deltaY < 0 ? 1.1 : 0.9;
            zoomLevel = Math.max(1, Math.min(5, zoomLevel));
            magnifier.style.setProperty("--zoom", zoomLevel);
        }

        if (lastMouseEvent) updateMagnifierPosition(lastMouseEvent);
    }, { passive: false });


    function updateMagnifierPosition(e) {
        const rect = image.getBoundingClientRect();
        const naturalWidth = image.naturalWidth;
        const naturalHeight = image.naturalHeight;

        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        const relX = x / rect.width;
        const relY = y / rect.height;

        const left = e.clientX - magnifierSize / 2;
        const top = e.clientY - magnifierSize / 2;

        magnifier.style.left = `${left}px`;
        magnifier.style.top = `${top}px`;

        const bgWidth = naturalWidth * zoomLevel;
        const bgHeight = naturalHeight * zoomLevel;

        const bgPosX = -(relX * bgWidth) + magnifierSize / 2;
        const bgPosY = -(relY * bgHeight) + magnifierSize / 2;

        magnifier.style.backgroundSize = `${bgWidth}px ${bgHeight}px`;
        magnifier.style.backgroundPosition = `${bgPosX}px ${bgPosY}px`;
    }
}

function getCaretCharacterOffsetWithin(element) {
  const selection = window.getSelection();
  let caretOffset = 0;
  if (selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    const preCaretRange = range.cloneRange();
    preCaretRange.selectNodeContents(element);
    preCaretRange.setEnd(range.endContainer, range.endOffset);
    caretOffset = preCaretRange.toString().length;
  }
  return caretOffset;
}

function setCaretCharacterOffsetWithin(element, offset) {
  const selection = window.getSelection();
  const range = document.createRange();
  let currentOffset = 0;

  function traverse(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      const nextOffset = currentOffset + node.length;
      if (offset <= nextOffset) {
        range.setStart(node, offset - currentOffset);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        throw 'found';
      }
      currentOffset = nextOffset;
    } else {
      for (let child of node.childNodes) traverse(child);
    }
  }

  try {
    traverse(element);
  } catch (e) {}
}

function initTopBarMenu() {
  const topBarMenuButton = document.getElementById('top-bar-menu');
  const serverBar = document.querySelector('.server-bar');
  const channelBar = document.querySelector('.channel-bar');
  const selfInfo = document.querySelector('.self-info');

  if (topBarMenuButton && serverBar && channelBar && selfInfo) {
    topBarMenuButton.replaceWith(topBarMenuButton.cloneNode(true));

    const newButton = document.getElementById('top-bar-menu');
    newButton.addEventListener('click', () => {
      serverBar.classList.toggle('hidden');
      channelBar.classList.toggle('active');
      selfInfo.classList.toggle('active');
      newButton.textContent = serverBar.classList.contains('hidden') ? 'menu' : 'close';
    });
  }
}

initTopBarMenu();

if (typeof window.swup !== "undefined") {
	window.swup.hooks.on('page:view', (visit) => {
		initTopBarMenu();
	});
}

window.addEventListener("load", () => {
  const msgContainer = document.querySelector(".input-container-2");

  function createStatusDiv(id, shortText, fullHtml) {
    removeStatusDiv(id);

    const div = document.createElement("div");
    div.id = id;
    div.className = "status-notice";

    div.innerHTML = `
      <span class="info-icon material-symbols-rounded">info</span>
      <span>${shortText}</span>
      <button class="reconnect-btn">Reconnect</button>
    `;

    div.querySelector(".reconnect-btn").addEventListener("click", () => {
      if (socket && !socket.connected) socket.connect();
    });

    div.querySelector(".info-icon").addEventListener("click", () => {
      jspt.makePopup({
        content_type: "html",
        content: fullHtml,
        header: "Connection Info",
      });
    });

    msgContainer.prepend(div);
  }

  function removeStatusDiv(id) {
    const existing = document.getElementById(id);
    if (existing) existing.remove();
  }

  function updateConnectionStatus() {
    if (navigator.onLine) {
      removeStatusDiv("disconnect-notice");
      removeStatusDiv("server-error-notice");
    } else {
      createStatusDiv(
        "disconnect-notice",
        "Disconnected due to network issues",
        `
          <p>You have been disconnected from the server due to network issues.</p>
          <ul>
            <li>Check your internet connection.</li>
            <li>Try a different network.</li>
            <li>Restart your router or computer.</li>
          </ul>
          <p>If none of these work, contact support.</p>
        `
      );
    }
  }

  function showServerError() {
    createStatusDiv(
      "server-error-notice",
      "Disconnected from server",
      `
        <p>You have been disconnected from the server for an unknown reason.</p>
        <ul>
          <li>Check your internet connection.</li>
          <li>Refresh the page.</li>
          <li>Restart your router or computer.</li>
        </ul>
        <p>If none of these work, contact support.</p>
      `
    );
  }

  if (socket) {
    socket.on("disconnect", () => {
      if (navigator.onLine) {
        showServerError();
      } else {
        updateConnectionStatus();
      }
    });

    socket.on("connect", updateConnectionStatus);
  }

  window.addEventListener("online", updateConnectionStatus);
  window.addEventListener("offline", updateConnectionStatus);

  let draggedServerClone = null;
	let originalServerElement = null;
	let lastServerTarget = null;
	let originalServerParent = null;
	let originalServerNextSibling = null;

	const serverDragStart = (e) => {
		if (e.target !== e.currentTarget) return;
		originalServerElement = e.currentTarget;
		originalServerParent = originalServerElement.parentElement;
		originalServerNextSibling = originalServerElement.nextElementSibling;

		const img = new Image();
		img.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgCMW3YkAAAAASUVORK5CYII=';
		e.dataTransfer.setDragImage(img, 0, 0);

		draggedServerClone = originalServerElement.cloneNode(true);
		draggedServerClone.style.position = 'absolute';
		draggedServerClone.style.pointerEvents = 'none';
		draggedServerClone.style.opacity = '0.8';
		draggedServerClone.style.zIndex = '1000';
    draggedServerClone.style.background = 'transparent';
    draggedServerClone.style.width = '69px';
		document.body.appendChild(draggedServerClone);

		moveServer(e);

		document.addEventListener('dragover', moveServer);
		document.addEventListener('dragend', stopServerDragging);
	};

	const moveServer = (e) => {
		if (!draggedServerClone) return;

		const rect = draggedServerClone.getBoundingClientRect();
		draggedServerClone.style.left = e.pageX - rect.width / 2 + 'px';
		draggedServerClone.style.top = e.pageY - rect.height / 2 + 'px';

		let target = document.elementFromPoint(e.clientX, e.clientY);
		if (target === draggedServerClone) target = target.parentElement;

		target = target.closest('.server-bar-item');

		if (target === originalServerElement) target = null;

		if (target) {
			if (lastServerTarget && lastServerTarget !== target) {
				lastServerTarget.classList.remove('server-position-line');
				lastServerTarget.removeAttribute('data-line');
			}

			target.classList.add('server-position-line');

			const targetRect = target.getBoundingClientRect();
			if (e.clientY - targetRect.top < targetRect.height / 2) {
				target.setAttribute('data-line', 'top');
			} else {
				target.setAttribute('data-line', 'bottom');
			}

			lastServerTarget = target;
		} else if (lastServerTarget) {
			lastServerTarget.classList.remove('server-position-line');
			lastServerTarget.removeAttribute('data-line');
			lastServerTarget = null;
		}
	};

	const stopServerDragging = () => {
		if (draggedServerClone) {
			draggedServerClone.remove();
			draggedServerClone = null;
		}

		if (lastServerTarget && originalServerElement) {
			const linePosition = lastServerTarget.getAttribute('data-line');
			if (linePosition) {
				if (linePosition === 'top') {
					lastServerTarget.parentElement.insertBefore(originalServerElement, lastServerTarget);
				} else {
					lastServerTarget.parentElement.insertBefore(originalServerElement, lastServerTarget.nextSibling);
				}
			}

			lastServerTarget.classList.remove('server-position-line');
			lastServerTarget.removeAttribute('data-line');
		}

		const cleanup = () => {
			lastServerTarget = null;
			originalServerElement = null;
			originalServerParent = null;
			originalServerNextSibling = null;
			document.removeEventListener('dragover', moveServer);
			document.removeEventListener('dragend', stopServerDragging);
		};

		const sendServerUpdate = () => {
			const servers = Array.from(document.querySelectorAll('.server-bar-item'));
			const serverId = originalServerElement.dataset.serverId;

			const position = servers.length - 1 - servers.indexOf(originalServerElement);

			const otherPositions = {};
			servers.forEach((server) => {
				if (server === originalServerElement) return;
				const id = server.dataset.serverId;
				const idx = servers.length - 1 - servers.indexOf(server);
				otherPositions[id] = idx;
			});

			fetch('https://chat.wokki20.nl/app/edit_server', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					Authorization: `Bearer ${access_token}`,
				},
				body: new URLSearchParams({
					action: 'edit_server_positioning',
					server_id: serverId,
					position: position,
					other_positions: JSON.stringify(otherPositions)
				})
			});
		};

		sendServerUpdate();
		cleanup();
	};

	const movableServers = document.querySelectorAll('.server-bar-item');
	movableServers.forEach((server) => {
		server.setAttribute('draggable', 'true');
		server.addEventListener('dragstart', serverDragStart, false);
	});

});
