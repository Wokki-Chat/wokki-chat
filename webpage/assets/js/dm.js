let replyingTo = null;

let selectedFiles = [];

let available_commands = [];

const uploadContainer = document.querySelector(".input-container-2 .file-upload-container");

const messageContainer = document.querySelector("#message-container");
window.addEventListener('DOMContentLoaded', async () => {
  await emojis.load();
});

window.addEventListener("load", () => {

const textarea = document.getElementById("message-input");
const preview = document.getElementById("message-input-bg");

let typing = false;

let offset = 0;
const limit = 25;

function loadMessages(offsetValue = 0) {
  socket.emit("get_direct_messages", {
    access_token,
    dm_id,
    offset: offsetValue
  });
}

if (messageContainer) {
  messageContainer.addEventListener("scroll", () => {
    if (messageContainer.scrollTop === 0) {
      offset += limit;
      loadMessages(offset);
    }
  });
}
const minHeight = 18;

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
      showAvailableCommands(textarea.innerText, textarea);
    } else {
      const popup = document.querySelector(".available-commands");
      textarea.style.color = "transparent";
      preview.style.display = "block";
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
      show_mentions(query);
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

      selectedFiles = [];
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


loadMessages();
socket.on("all_direct_messages", async (messages) => {
  const isAtBottom = (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) < 5;

  async function getParentData(pid) {
    if (!pid) return { parent_message_text: null, parent_message_user: null };
    try {
      return await loadParentMessage(pid, socket);
    } catch {
      return { parent_message_text: null, parent_message_user: null };
    }
  }

  const fragment = document.createDocumentFragment();

  for (const msg of messages) {
    const parentData = await getParentData(msg.parent_message_id);
    const msgEl = createMessageElement(msg, parentData);
    if (msgEl) fragment.appendChild(msgEl);
  }

  const oldScrollHeight = messageContainer.scrollHeight;
  messageContainer.insertBefore(fragment, messageContainer.firstChild);
  const newScrollHeight = messageContainer.scrollHeight;
  messageContainer.scrollTop += (newScrollHeight - oldScrollHeight);

  await highlightAll();
  await addCodeblockInfo();
  emojis.replaceAll();

  if (isAtBottom) {
    await scrollToBottomWhenStable(messageContainer);
  }
});


let lastRenderedUser = null;
let lastRenderedTimestamp = null;

function createMessageElement({ username, message, created_at, sent_by, id, from_id, parent_message_id, assets, profile_picture }, parentData) {
  const sanitizedUsername = sanitize(username);
  const sanitizedMessage = sanitizeMsg(message);
  const createdAtDate = new Date(created_at);

  if (from_id !== dm_id && sent_by === dm_id) return null;

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
        return `<img src="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" alt="${asset.originalName}" class="message-asset-image" onclick="imageViewer('https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}', '${asset.originalName}')" />`;
      } else if (type === 'video') {
        return `<video src="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" controls class="message-asset-video"></video>`;
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
      } else {
        return `<a href="https://chat.wokki20.nl/uploads/messages/${encodeURIComponent(asset.savedName)}" download class="message-asset-file link">${sanitize(asset.savedName)}</a>`;
      }
    }).join('');
  }

  const parent_message_text_2 = parentData?.parent_message_text ? sanitize(parentData.parent_message_text).slice(0, 100) + (parentData.parent_message_text.length > 100 ? '...' : '') : null;
  const parent_message_user_2 = parentData?.parent_message_user ? sanitize(parentData.parent_message_user) : null;

  const msgEl = document.createElement("div");
  msgEl.innerHTML = `
    <div class="message ${hideHeader && !parent_message_id? 'compact' : ''}" data-message-id="${id}" data-sent-by="${sent_by}">
      ${
        parent_message_id
          ? `<div class="message-reply" data-message-id="${parent_message_id}">
              <img class="identification" src="/assets/images/identifier.svg">
              <p class="username-reply">@${parent_message_user_2 ?? ''}</p>
              <p class="message-text-reply">${parent_message_text_2 ?? ''}</p>
            </div>`
          : ''
      }
      <div class="message-content">
        <img class="profile-picture" src="${profile_picture}" style="opacity: ${hideHeader && !parent_message_id? '0' : '1'}; height: ${hideHeader && !parent_message_id? '0' : '30px'};" />
        <div class="name-text">
            <div class="username-date" style="display: ${hideHeader && !parent_message_id? 'none' : 'flex'};">
              <p class="username">${sanitizedUsername}</p>
              ${isPremium ? '<div class="premium-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_premium.svg">PREMIUM</div>' : ''}
              ${isStaff ? '<div class="staff-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_staff.svg">STAFF</div>' : ''}
              <p class="date" data-timestamp="${created_at}">${formatDate(created_at)}</p>
            </div>
          <div class="message-text">${sanitizedMessage}</div>
          ${assetsHTML ? `<div class="message-assets">${assetsHTML}</div>` : ''}

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
    </div>
  `;

  const msgWrapper = msgEl.querySelector('.message');
  const mentionTags = msgEl.querySelectorAll(`.user-link[data-user-id="${user_id}"], .user-link[data-user-id="everyone"]`);

  if (mentionTags.length > 0) {
    msgWrapper.classList.add("mentioned");
  }

  if (assets && assets.length > 0) {
    assets.forEach((asset, index) => {
      if (getAssetType(asset.savedName) === 'txt') {
        const preEl = msgEl.querySelector(`#txt-asset-${id}-${index} code`);
        if (!preEl) return;

        getAssetFileInsides(asset.savedName).then(contents => {
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
              const codeText = preEl.innerText;
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
        }).catch(() => {
          preEl.textContent = '[Failed to load file]';
        });
      }
    });
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

      socket.emit("delete_direct_message", { access_token, message_id: id, dm_id });
      deleteMsg(id);
    });
  }

  hljs.highlightAll();

  return msgEl;
}

socket.on("user_updated", (user) => {
    if (!usersList.some(u => String(u.id) === user.id)) return;
    renderUser(user);
});


function renderUser(user) {
    const userListContainer = document.querySelector(".dm-users");

    if (!usersList.some(u => String(u.id) === user.id)) return;
    if (String(user.id) === (user_id)) return;

    userListContainer.querySelectorAll(`[data-user-id="${user.id}"]`).forEach(el => el.remove());

    const userEl = document.createElement("div");
    userEl.classList.add("info-profile");
    userEl.setAttribute("data-user-id", user.id);
    const encodedUsername = encodeURIComponent(user.username);
    userEl.onclick = () => {
        window.location.href = `/dm/@${encodedUsername}`;
    };
    if (String(user.id) === String(dm_id)) {
        userEl.classList.add("active");
    }

    userEl.innerHTML = `
        <div class="self-info-profile-status" data-user-id="${user.id}">
            <img class="self-info-profile-picture" src="${user.profile_picture}" />
            <div class="self-info-status-circle-outer">
                <div class="self-info-status-circle-inner ${user.status}"></div>
            </div>
        </div>
        <div class="self-info-status-username">
            <div class="self-info-profile-username-container"><p class="self-info-username">${sanitize(user.username)}</p>
                ${user.premium ? '<div class="premium-tag"><span class="material-symbols-rounded">star</span>PREMIUM</div>' : ''}
                </div>
            <p class="self-info-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
        </div>
    `;

    userListContainer.appendChild(userEl);
}

socket.on("new_direct_message", async (msg) => {
  const isAtBottom = (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) < 5;

  if (String(msg.sent_by) !== String(dm_id) && String(msg.sent_by) !== user_id) return;

  let parent_message_text_2 = null;
  let parent_message_user_2 = null;

  if (msg.parent_message_id) {
    const { parent_message_text, parent_message_user } = await loadParentMessage(msg.parent_message_id, socket);
    if (parent_message_text && parent_message_user) {
      parent_message_text_2 = sanitize(parent_message_text);
      parent_message_user_2 = sanitize(parent_message_user);
    }
  }

  const parentInfo = { parent_message_text: parent_message_text_2, parent_message_user: parent_message_user_2 };
  const msgEl = createMessageElement(msg, parentInfo);

  messageContainer.appendChild(msgEl);

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

function send_message(textareaEl, uploadedFileNames = null) {
  const message = getCleanMessageFromTextarea(textareaEl);

  if (!message) return;

  const payload = {
    access_token,
    message,
    dm_id
  };

  if (replyingTo !== null && replyingTo !== undefined) {
    payload.parent_message_id = replyingTo;
    
  }

  if (uploadedFileNames !== null && uploadedFileNames !== undefined) {
    payload.file_names = uploadedFileNames;
  }

  socket.emit("send_direct_message", payload);
  textareaEl.style.minHeight = minHeight + 'px';
  textareaEl.innerText = "";
  replyingTo = null;
  if (document.querySelector(".replying-to")) {
    document.querySelector(".replying-to").remove();

  }
  if (typing) {
    typing = false;
    socket.emit('direct_typing', {
        access_token: access_token,
        typing: false,
        dm_id
      });
  }

  uploadContainer.innerHTML = '';
  selectedFiles = [];
}

socket.on("send_direct_message_response", (resp) => {
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

async function replyMessage(id) {
  if (document.querySelector(".replying-to")) document.querySelector(".replying-to").remove();
  replyingTo = id;
  document.getElementById("message-input").focus();

  const { parent_message_text, parent_message_user } = await loadParentMessage(id, socket);

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

async function loadParentMessage(parent_message_id) {
  if (!parent_message_id) 
    return { parent_message_text: "message deleted", parent_message_user: "deleted" };

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

    socket.emit("get_direct_message_by_id", { access_token, message_id: id, dm_id });

    function handler(message) {
      socket.off("direct_message_by_id", handler);
      resolve(message ?? null);
    }

    socket.on("direct_message_by_id", handler);

    setTimeout(() => {
      socket.off("direct_message_by_id", handler);
      reject(new Error("Timeout getting direct_message_by_id"));
    }, 5000);
  });
}

socket.on("direct_message_deleted", (message_id) => {
  deleteMsg(message_id);
});

function deleteMsg(id) {
  const msgEl = document.querySelector(`.message[data-message-id="${id}"]`);
  if (!msgEl) return;

  const parentNext = msgEl.parentElement.nextElementSibling;
  if (!parentNext || !parentNext.children.length) {
    msgEl.parentElement.remove();
    return;
  }

  const nextMsgEl = parentNext.children[0];

  msgEl.parentElement.remove();

  if (nextMsgEl) {
    const usernameDateEl = nextMsgEl.querySelector('.username-date');
    if (usernameDateEl) {
      usernameDateEl.style.display = 'flex';
    }
    const profilePic = nextMsgEl.querySelector('.profile-picture');
    if (profilePic) {
      profilePic.setAttribute("style", "opacity: 1; height: 30px;");
    }
  }
}

});
