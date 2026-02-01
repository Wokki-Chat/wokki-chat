const uploadContainer = document.querySelector(".input-container-2 .file-upload-container");

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

async function scrollToBottomWhenStable(container) {
  return new Promise((resolve) => {
    let lastHeight = container.scrollHeight;
    const observer = new MutationObserver(() => {
      const newHeight = container.scrollHeight;
      if (newHeight !== lastHeight) {
        lastHeight = newHeight;
        container.scrollTop = container.scrollHeight;
      }
    });

    observer.observe(container, { childList: true, subtree: true, characterData: true });

    setTimeout(() => {
      observer.disconnect();
      container.scrollTop = container.scrollHeight;
      resolve();
    }, 500);
  });
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

function sanitize(text) {
  const div = document.createElement("div");
  div.innerText = text;
  return div.innerHTML;
}

async function sanitizeMsg(text, usersList = [], user_id, channels, server_id) {
  function escapeHtml(str) {
    return str.replace(/[&<>"']/g, ch => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[ch]);
  }

  function isInsideATag(str, index) {
    const openTagIndex = str.lastIndexOf('<a ', index);
    if (openTagIndex === -1) return false;
    const openTagEnd = str.indexOf('>', openTagIndex);
    if (openTagEnd === -1) return false;
    const closeTagIndex = str.indexOf('</a>', openTagEnd);
    if (closeTagIndex === -1) return false;
    return index >= openTagIndex && index < closeTagIndex + 4;
  }

  const emojiStore = [];
  text = text.replace(/:([a-zA-Z0-9_+-]+):/g, (m) => {
    const id = emojiStore.length;
    emojiStore.push(m);
    return `__EMOJI_${id}__`;
  });

  const parts = text.split(/(```(\w+)?\n[\s\S]*?```)/g);

  let processed = await Promise.all(parts.map(async part => {
    if (!part) return '';
    if (part.startsWith('```')) {
      const match = part.match(/```(\w+)?\n([\s\S]*?)```/);
      if (!match) return '';
      const lang = match[1] || '';
      const code = match[2];
      const escapedCode = escapeHtml(code);
      return `<pre><code class="lang-${lang}" style="white-space: pre-wrap;">${escapedCode}</code></pre>`;
    } else {
      let escaped = escapeHtml(part);

      async function replaceWithCheck(regex, replacer) {
        const matches = [...escaped.matchAll(regex)];
        for (const match of matches.reverse()) {
          const offset = match.index;
          if (isInsideATag(escaped, offset)) continue;
          escaped = escaped.slice(0, offset) + await replacer(...match) + escaped.slice(offset + match[0].length);
        }
      }

      await replaceWithCheck(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, (match, linkText, url) => {
        return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${linkText}</a>`;
      });

      await replaceWithCheck(/(?<!["'>])(https?:\/\/[^\s<]+)/g, (url) => {
        if (!url.startsWith('https://open.spotify.com/track/') && !url.startsWith('https://chat.wokki20.nl/invite/')) {
          return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>`;
        }
        return url;
      });

      await replaceWithCheck(/^#{1,6} .*/gm, (line) => {
        const level = line.match(/^#+/)[0].length;
        const content = line.slice(level + 1).trim();
        return `<h${level} style="margin: 0;">${content}</h${level}>`;
      });

      await replaceWithCheck(/(^|\n)((- .+\n?)+)/g, (match, before, list) => {
        const items = list.trim().split('\n').map(i => i.replace(/^- /, '').trim());
        const lis = items.map(i => `<li>${i}</li>`).join('');
        return `${before}<ul>${lis}</ul>`;
      });

      await replaceWithCheck(/(?<!\\)~~(.+?)~~/g, (match, content) => `<del>${content}</del>`);
      await replaceWithCheck(/(?<!\\)`([^`\n]+)`/g, (match, code) => `<code>${code}</code>`);
      await replaceWithCheck(/(?<!\\)\*\*(?!\*\*)([^*]+?)\*\*/g, (match, content) => `<strong>${content}</strong>`);
      await replaceWithCheck(/(?<!\\)(\*|_)(.+?)\1/g, (match, wrap, content) => {
        if (content.includes(wrap)) return match;
        return `<em>${content}</em>`;
      });

      await replaceWithCheck(/(^|\n)((?:&gt; ?.*(?:\n|$))+)/g, (match, before, quoteBlock) => {
        const lines = quoteBlock.trim().split('\n').map(line => line.replace(/^&gt; ?/, '')).join('<br>');
        return `${before}<div class="quote">${lines}</div>`;
      });

      await replaceWithCheck(/#([^\s#<]+)/g, (match, channelName) => {
        if (!Array.isArray(channels)) return match;
        const channel = channels.find(c => c.name.toLowerCase() === channelName.toLowerCase());
        if (channel) {
          const url = `https://chat.wokki20.nl/server/${server_id}/channel/${channel.channel_id}`;
          return `<a href="${url}" rel="noopener noreferrer" class="channel-link">#${channelName}</a>`;
        }
        return match;
      });

      await replaceWithCheck(/&lt;@([^&]+)&gt;/g, (match, username) => {
        const cleanUsername = username.trim();
        if (cleanUsername.toLowerCase() === "everyone") {
          return `<a href="#" rel="noopener noreferrer" class="user-link everyone self" data-user-id="everyone">@everyone</a>`;
        }
        const user = usersList.find(u => u.username.toLowerCase() === cleanUsername.toLowerCase());
        if (user) {
          const url = `https://chat.wokki20.nl/profile/@${encodeURIComponent(cleanUsername)}`;
          return `<a href="${url}" rel="noopener noreferrer" class="user-link ${user.id == user_id ? "self" : ""}" data-user-id="${user.id}">@${cleanUsername}</a>`;
        }
        return match;
      });

      await replaceWithCheck(/&lt;t:(\d+):(\w+)&gt;/g, (match, timeNumber, type) => {
        const timestamp = parseInt(timeNumber, 10) * 1000;
        const date = new Date(timestamp);
        const isoTime = date.toISOString();
        let formatted = '';
        switch (type) {
          case 'R': {
            const now = Date.now();
            const diff = timestamp - now;
            const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
            const seconds = Math.round(diff / 1000);
            const minutes = Math.round(diff / 60000);
            const hours = Math.round(diff / 3600000);
            const days = Math.round(diff / 86400000);
            if (Math.abs(seconds) < 60) formatted = rtf.format(seconds, 'second');
            else if (Math.abs(minutes) < 60) formatted = rtf.format(minutes, 'minute');
            else if (Math.abs(hours) < 24) formatted = rtf.format(hours, 'hour');
            else formatted = rtf.format(days, 'day');
            break;
          }
          case 'F':
            formatted = date.toLocaleString(undefined, { dateStyle: 'long', timeStyle: 'short' });
            break;
          case 'D':
            formatted = date.toLocaleDateString(undefined, { dateStyle: 'long' });
            break;
          case 'T':
            formatted = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            break;
          default:
            formatted = isoTime;
        }
        return `<time datetime="${isoTime}" data-type="${type}" class="dynamic-time">${formatted}</time>`;
      });

      escaped = escaped.replace(/\n/g, '<br>');

      await replaceWithCheck(/(?<!["'>])(https?:\/\/chat\.wokki20\.nl\/invite\/[^\s)]+)/g, (url) => {
        const inviteId = url.split("/").pop();
        return `
          <a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>
          <div class="invite-item-container loading"
              data-invite-url="${url}"
              data-invite-id="${inviteId}">
            <p>Loading invite…</p>
          </div>
        `;
      });

      await replaceWithCheck(/(?<!["'>])(https?:\/\/open\.spotify\.com\/track\/[0-9A-Za-z]+)(\?[^\s]*)?/g, (url) => {
        const trackId = url.split("/").pop().split("?")[0];
        return `
          <a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>
          <div class="spotify-track-container loading"
            data-spotify-track-url="${url}"
            data-spotify-track-id="${trackId}">
            <p>Loading Spotify Embed…</p>
          </div>
        `;
      });

      escaped = escaped.replace(/\\([*_\-~`\\[\](){}])/g, '$1');

      escaped = escaped.replace(/__EMOJI_(\d+)__/g, (_, i) => emojiStore[i]);

      return escaped;
    }
  }));

  const result = processed.join('');

  setTimeout(() => {
    hljs.highlightAll();
  }, 0);

  return result.trim();
}

async function sanitizeMrk(text) {
  function escapeHtml(str) {
    return str.replace(/[&<>"']/g, ch => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[ch]);
  }

  const emojiStore = [];
  text = text.replace(/:([a-zA-Z0-9_+-]+):/g, (m) => {
    const id = emojiStore.length;
    emojiStore.push(m);
    return `__EMOJI_${id}__`;
  });

  text = escapeHtml(text);

  async function replaceWithCheck(str, regex, replacer) {
    const matches = [...str.matchAll(regex)];
    for (const match of matches.reverse()) {
      const offset = match.index;
      str = str.slice(0, offset) + await replacer(...match) + str.slice(offset + match[0].length);
    }
    return str;
  }

  text = await replaceWithCheck(text, /(^|\n)((- .+\n?)+)/g, (match, before, list) => {
    const items = list.trim().split('\n').map(i => i.replace(/^- /, '').trim());
    const lis = items.map(i => `<li>${i}</li>`).join('');
    return `${before}<ul>${lis}</ul>`;
  });

  text = await replaceWithCheck(text, /(?<!\\)~~(.+?)~~/g, (match, content) => `<del>${content}</del>`);
  text = await replaceWithCheck(text, /(?<!\\)`([^`\n]+)`/g, (match, code) => `<code>${code}</code>`);
  text = await replaceWithCheck(text, /(?<!\\)\*\*(?!\*\*)([^*]+?)\*\*/g, (match, content) => `<strong>${content}</strong>`);
  text = await replaceWithCheck(text, /(?<!\\)(\*|_)([^*_]+?)\1/g, (match, wrap, content) => `<em>${content}</em>`);

  text = await replaceWithCheck(text, /(^|\n)((?:&gt; ?.*(?:\n|$))+)/g, (match, before, quoteBlock) => {
    const lines = quoteBlock.trim().split('\n').map(line => line.replace(/^&gt; ?/, '')).join('<br>');
    return `${before}<div class="quote">${lines}</div>`;
  });

  text = await replaceWithCheck(text, /&lt;t:(\d+):(\w+)&gt;/g, (match, timeNumber, type) => {
    const timestamp = parseInt(timeNumber, 10) * 1000;
    const date = new Date(timestamp);
    const isoTime = date.toISOString();
    let formatted = '';
    switch (type) {
      case 'R': {
        const now = Date.now();
        const diff = timestamp - now;
        const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
        const seconds = Math.round(diff / 1000);
        const minutes = Math.round(diff / 60000);
        const hours = Math.round(diff / 3600000);
        const days = Math.round(diff / 86400000);
        if (Math.abs(seconds) < 60) formatted = rtf.format(seconds, 'second');
        else if (Math.abs(minutes) < 60) formatted = rtf.format(minutes, 'minute');
        else if (Math.abs(hours) < 24) formatted = rtf.format(hours, 'hour');
        else formatted = rtf.format(days, 'day');
        break;
      }
      case 'F':
        formatted = date.toLocaleString(undefined, { dateStyle: 'long', timeStyle: 'short' });
        break;
      case 'D':
        formatted = date.toLocaleDateString(undefined, { dateStyle: 'long' });
        break;
      case 'T':
        formatted = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        break;
      default:
        formatted = isoTime;
    }
    return `<time datetime="${isoTime}" data-type="${type}" class="dynamic-time">${formatted}</time>`;
  });

  text = text.replace(/\n/g, '<br>');
  text = text.replace(/\\([*_\-~`\\[\](){}])/g, '$1');

  text = text.replace(/__EMOJI_(\d+)__/g, (_, i) => emojiStore[i]);

  return text;
}

function formatDynamicTime() {
  const elements = document.querySelectorAll('time.dynamic-time');
  elements.forEach(el => {
    const date = new Date(el.getAttribute('datetime'));
    const type = el.dataset.type;

    let formatted;
    switch (type) {
      case 'R': {
        const now = Date.now();
        const diff = date - now;
        const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

        const seconds = Math.round(diff / 1000);
        const minutes = Math.round(diff / 60000);
        const hours = Math.round(diff / 3600000);
        const days = Math.round(diff / 86400000);

        if (Math.abs(seconds) < 60) {
          formatted = rtf.format(Math.round(seconds), 'second');
        } else if (Math.abs(minutes) < 60) {
          formatted = rtf.format(Math.round(minutes), 'minute');
        } else if (Math.abs(hours) < 24) {
          formatted = rtf.format(Math.round(hours), 'hour');
        } else {
          formatted = rtf.format(Math.round(days), 'day');
        }
        break;
      }

      case 'F':
        formatted = date.toLocaleString(undefined, {
          dateStyle: 'long',
          timeStyle: 'short',
        });
        break;

      case 'D':
        formatted = date.toLocaleDateString(undefined, {
          dateStyle: 'long',
        });
        break;

      case 'T':
        formatted = date.toLocaleTimeString(undefined, {
          hour: 'numeric',
          minute: '2-digit',
        });
        break;

      default:
        formatted = date.toISOString();
    }

    el.textContent = formatted;
  });
}

setInterval(formatDynamicTime, 1 * 1000);
document.addEventListener('DOMContentLoaded', formatDynamicTime);

function show_mentions(query, usersList) {
  const existingPopup = document.querySelector(".mentions-popup");
  if (existingPopup) existingPopup.remove();

  const matches = !query
    ? usersList
    : usersList.filter(user =>
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
      <span class="mention-username">${user.username}</span>
    `;

    userDiv.addEventListener("click", () => {
      insertMention(user.username);
      container.remove();
    });

    container.appendChild(userDiv);
  });

  document.querySelector(".input-container-2").insertAdjacentElement("afterbegin", container);
}

function hide_mentions() {
  const existingPopup = document.querySelector(".mentions-popup");
  if (existingPopup) existingPopup.remove();
}

function insertMention(username) {
  const textarea = document.getElementById("message-input");
  const text = textarea.innerHTML;

  const lastAtIndex = text.lastIndexOf("@");
  if (lastAtIndex === -1) return;

  const beforeAt = text.slice(0, lastAtIndex);
  const afterAt = text.slice(lastAtIndex);

  const newAfterAt = afterAt.replace(/^@\w*/, `<a class="user-link" contenteditable="false" style="cursor: default;">@${sanitize(username)}</a>&nbsp;`);

  textarea.innerHTML = beforeAt + newAfterAt;

  placeCaretAtEnd(textarea);
}

function placeCaretAtEnd(el) {
  el.focus();
  if (typeof window.getSelection != "undefined"
      && typeof document.createRange != "undefined") {
    const range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }
}

function showAvailableCommands(command, textarea, available_commands) {
    const existingPopup = document.querySelector(".available-commands");
    if (existingPopup) existingPopup.remove();
    if (available_commands.length === 0) return;

    document.getElementById("message-input").focus();

    document.querySelector(".input-container-2").insertAdjacentHTML("afterbegin", `
        <div class="available-commands"></div>
    `);

    const container = document.querySelector(".available-commands");
    let matchedCommands = [];

    available_commands.forEach(bot => {
        const profilePicture = bot.profile_picture;
        const username = bot.username;
        const bot_id = bot.id;

        bot.commands.forEach(command_info => {
            if (command_info.command.toLowerCase().startsWith(command.toLowerCase())) {
                matchedCommands.push({ command: command_info.command, bot_id: bot_id });

                const commandDiv = document.createElement('div');
                commandDiv.className = "available-command";
                commandDiv.dataset.command = command_info.command;
                commandDiv.dataset.botId = bot_id;
                commandDiv.innerHTML = `
                    <img class="availible-command-bot-profile-picture" src="${profilePicture}" />
                    <div class="availible-command-username-command">
                        <p class="availible-command-username">${username}</p>
                        <p class="availible-command-command">${sanitize(command_info.command)}</p>
                    </div>
                `;
                container.appendChild(commandDiv);
            }
        });
    });

    document.querySelectorAll(".available-command").forEach(el => {
      el.addEventListener("click", () => {
        const textarea = document.getElementById("message-input");
        const preview = document.getElementById("message-input-bg");

        textarea.style.color = "var(--clr-text-a0)";
        preview.style.display = "none";

        const bot_id = el.dataset.botId;
        const commandText = el.dataset.command;

        const bot = available_commands.find(b => b.id === bot_id);
        if (!bot) return;

        const commandObj = bot.commands.find(c => c.command === commandText);
        if (!commandObj) return;

        let options = null;
        if (commandObj.options) {
          try {
            options = JSON.parse(commandObj.options);
          } catch {}
        }

        textarea.innerHTML = `<span class="command-input" contenteditable="false">${commandText}<div class="command-options"></div></span>`;

        if (!options || options.length === 0) {
            const payload = {
                access_token,
                command: commandText,
                server_id: document.getElementById("server-id")?.getAttribute("value") || "",
                channel_id: document.getElementById("channel-id")?.getAttribute("value") || "",
                bot_id,
                options: {}
            };
            socket.emit("command", payload);
            resetComposer();
            return;
        }

        if (options) {
          options.forEach((option) => {
            textarea.querySelector('.command-options').innerHTML +=
              `<span class="command-option">${option.option_name}<input type="text" class="command-option-input" name="${option.option_name}" style="width:auto;" autocomplete="none"></span>`;
          });
        }

        const container = document.querySelector(".available-commands");
        if (container) container.remove();

        const commandSpan = textarea.querySelector('.command-input');
        if (!commandSpan) return;

        const commandOptions = textarea.querySelector('.command-options');

        setTimeout(() => {
          const firstInput = textarea.querySelector('.command-option-input');
          if (firstInput) firstInput.focus();
        }, 0);
        

        commandOptions.querySelectorAll('.command-option-input').forEach(input => {
          adjustWidth(input);
          input.addEventListener('click', (e) => e.stopPropagation())
          input.addEventListener('input', () => {
            adjustWidth(input);
            setTimeout(() => {
              input.focus(); 
            }, 0);
          });


          input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
              e.preventDefault();

              let valid = true;
              const data = {};

              options.forEach(option => {
                const inputEl = textarea.querySelector(`input[name="${option.option_name}"]`);
                if (!inputEl) return;

                const errorEl = inputEl.parentElement;
                const val = inputEl.value.trim();

                if (option.required && val === '') {
                  valid = false;
                  errorEl.classList.add('input-error');
                  inputEl.focus();
                  Toastify({
                    text: `Option '${option.option_name}' is required.`,
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
                  return;
                } else {
                  errorEl.classList.remove('input-error');
                }

                if (val !== '') {
                  if (option.option_type === 'boolean') {
                    const lowered = val.toLowerCase();
                    if (lowered !== 'true' && lowered !== 'false') {
                      valid = false;
                      errorEl.classList.add('input-error');
                      inputEl.focus();
                      Toastify({
                        text: `Option '${option.option_name}' must be 'true' or 'false'.`,
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
                      return;
                    }
                    data[option.option_name] = lowered === 'true';

                  } else if (option.option_type === 'number') {
                    if (isNaN(val)) {
                      valid = false;
                      errorEl.classList.add('input-error');
                      inputEl.focus();
                      Toastify({
                        text: `Option '${option.option_name}' must be a number.`,
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
                      return;
                    }
                    data[option.option_name] = Number(val);

                  } else if (option.option_type === 'user') {
                    if (!val.startsWith("@")) {
                      valid = false;
                      errorEl.classList.add('input-error');
                      inputEl.focus();
                      Toastify({
                        text: `Option '${option.option_name}' must be a user mention.`,
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
                      return;
                    }

                    const username = val.slice(1);

                    const user = usersList.find(u => u.username === username);

                    if (!user) {
                      valid = false;
                      errorEl.classList.add('input-error');
                      inputEl.focus();
                      Toastify({
                        text: `User @${username} is not in this server.`,
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
                      return;
                    }

                    data[option.option_name] = `${user.username}#${user.id}`;

                  } else {
                    data[option.option_name] = val;
                  }
                }
              });


              if (!valid) return;

              const payload = {
                access_token,
                command: commandText,
                server_id: document.getElementById("server-id")?.getAttribute("value") || "",
                channel_id: document.getElementById("channel-id")?.getAttribute("value") || "",
                bot_id,
                options: data
              };

              socket.emit("command", payload);

              resetComposer();

              const popup = document.querySelector(".available-commands");
              if (popup) popup.remove();
            }
          });
        });

        function resetComposer() {
          const textarea = document.getElementById("message-input");
          const preview  = document.getElementById("message-input-bg");
          const messageInputWrapper = document.querySelector('.message-input-wrapper');
          const inputContainer      = document.querySelector('.input-container-2');

          if (!textarea || !preview) return;

          textarea.innerHTML = "\u200B";
          textarea.style.color  = "transparent";
          preview.style.display = "block";

          if (messageInputWrapper) messageInputWrapper.style.height = "";
          if (inputContainer)      inputContainer.style.minHeight   = "";

          if (typeof typing !== "undefined" && typing) {
            typing = false;
            socket.emit("typing", { access_token, typing: false, channel_id, server_id });
          }

          textarea.dispatchEvent(new InputEvent("input", { bubbles: true }));

          setTimeout(() => {
            textarea.focus();
            if (textarea.textContent === "\u200B") textarea.textContent = "";
          }, 1);
        }



        function adjustWidth(el) {
          const tempSpan = document.createElement('span');
          tempSpan.style.position = 'absolute';
          tempSpan.style.visibility = 'hidden';
          tempSpan.style.whiteSpace = 'pre';
          tempSpan.style.font = getComputedStyle(el).font;
          tempSpan.textContent = el.value || el.placeholder || '';
          document.body.appendChild(tempSpan);

          const newWidth = tempSpan.offsetWidth + 5;
          el.style.width = newWidth + 'px';

          document.body.removeChild(tempSpan);
        }

      });
    });


    showAvailableCommands.lastMatches = matchedCommands;
}

function getCleanMessageFromTextarea(textareaEl) {
  const clone = textareaEl.cloneNode(true);

  clone.querySelectorAll("a.user-link").forEach(a => {
    const username = a.textContent.replace("@", "");
    const textNode = document.createTextNode(`<@${username}>`);
    a.replaceWith(textNode);
  });

  clone.querySelectorAll("br").forEach(br => {
    const textNode = document.createTextNode("\n");
    br.replaceWith(textNode);
  });

  return clone.innerText.trim();
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

function renderMarkdownInTextarea(text) {
  const escapeHtml = (str) =>
    str.replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[ch]);

  const emojiStore = [];
  text = text.replace(/:([a-zA-Z0-9_+-]+):/g, (m) => {
    const id = emojiStore.length;
    emojiStore.push(m);
    return `__EMOJI_${id}__`;
  });

  const escaped = escapeHtml(text);

  let rendered = escaped
    .replace(/(\\)?\*\*(.+?)\*\*/g, (match, esc, content) => {
      if (esc) return `<span class="md-escape">\\</span>**${content}**`;
      return `<span class="md-bold"><span class="md-syntax">**</span><b>${content}</b><span class="md-syntax">**</span></span>`;
    })

    .replace(/(\\)?([*_])([^*_]*?)\2/g, (match, esc, wrap, content) => {
      if (esc) return `<span class="md-escape">\\</span>${wrap}${content}${wrap}`;
      if (content.includes(wrap)) return match;
      return `<span class="md-italic"><span class="md-syntax">${wrap}</span><i>${content}</i><span class="md-syntax">${wrap}</span></span>`;
    })

    .replace(/(\\)?~~(.+?)~~/g, (match, esc, content) => {
      if (esc) return `<span class="md-escape">\\</span>~~${content}~~`;
      return `<span class="md-strike"><span class="md-syntax">~~</span><del>${content}</del><span class="md-syntax">~~</span></span>`;
    });

  rendered = rendered.replace(/__EMOJI_(\d+)__/g, (_, i) => emojiStore[i]);

  return rendered;
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

const topBarMenuButton = document.getElementById('top-bar-menu');
const serverBar = document.querySelector('.server-bar');
const channelBar = document.querySelector('.channel-bar');
const selfInfo = document.querySelector('.self-info');
topBarMenuButton.addEventListener('click', () => {
  serverBar.classList.toggle('active');
  channelBar.classList.toggle('active');
  selfInfo.classList.toggle('active');
  if (serverBar.classList.contains('active')) {
    topBarMenuButton.textContent = 'close';
  } else {
    topBarMenuButton.textContent = 'menu';
  }
});


window.addEventListener("load", () => {
  if (socket) {
    socket.on("disconnect", (reason) => {
      if (navigator.onLine) {
          showServerErrorModal();
      } else {
          showDisconnectModal();
      }
    });

    socket.on("connect", () => {
        hideDisconnectModal();
        hideServerErrorModal();
    });
  }

  function showServerErrorModal() {
      let modalHtml = `
        <div class="modal" id="server-error-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Disconnected from server</h2>
                </div>
                <div class="modal-body">
                    <p class="modal-text">You have been disconnected from the server for an unknown reason.</p>
                    <p class="modal-text">What can I do?</p>
                    <ul class="modal-list">
                        <li class="modal-list-item">Check your internet connection.</li>
                        <li class="modal-list-item">Try refreshing the page.</li>
                        <li class="modal-list-item">Try restarting your router.</li>
                        <li class="modal-list-item">Try restarting your computer.</li>
                    </ul>
                    <p class="modal-text">If none of these options work, Please contact support.</p>
                </div>
            </div>
        </div>
      `;

      document.body.insertAdjacentHTML("beforeend", modalHtml);      
  }

  function showDisconnectModal() {
      let modalHtml = `
        <div class="modal" id="disconnect-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Disconnected from server</h2>
                </div>
                <div class="modal-body">
                    <p class="modal-text">You have been disconnected from the server for due to network issues.</p>
                    <p class="modal-text">What can I do?</p>
                    <ul class="modal-list">
                        <li class="modal-list-item">Check your internet connection.</li>
                        <li class="modal-list-item">Try a different network.</li>
                        <li class="modal-list-item">Try restarting your router.</li>
                        <li class="modal-list-item">Try restarting your computer.</li>
                    </ul>
                    <p class="modal-text">If none of these options work, Please contact support.</p>
                </div>
            </div>
        </div>
      `;

      document.body.insertAdjacentHTML("beforeend", modalHtml);          
  }

  function hideServerErrorModal() {
      const serverErrorModal = document.getElementById("server-error-modal");
      if (serverErrorModal) {
          serverErrorModal.remove();
      }
  }
  function hideDisconnectModal() {
      const disconnectModal = document.getElementById("disconnect-modal");
      if (disconnectModal) {
          disconnectModal.remove();
      }
  }

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
