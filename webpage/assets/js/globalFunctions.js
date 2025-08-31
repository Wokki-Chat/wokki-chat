
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

function sanitize(text) {
  const div = document.createElement("div");
  div.innerText = text;
  return div.innerHTML;
}

function sanitizeMsg(text) {
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

  function unescapeMarkdown(str) {
    return str.replace(/\\([*_\-~`\\[\](){}])/g, '$1');
  }

  const parts = text.split(/(```(\w+)?\n[\s\S]*?```)/g);

  let processed = parts.map(part => {
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

      function replaceWithCheck(regex, replacer) {
        escaped = escaped.replace(regex, (...args) => {
          const match = args[0];
          const offset = args[args.length - 2];
          if (isInsideATag(escaped, offset)) return match;
          return replacer(...args);
        });
      }
      replaceWithCheck(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, (match, linkText, url) => {
        return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${linkText}</a>`;
      });

      replaceWithCheck(/(?<!["'>])(https?:\/\/[^\s<]+)/g, (url) => {
        if (!url.startsWith('https://chat.jonazwetsloot.nl/posts/') && !url.startsWith('https://chat.wokki20.nl/invite/')) {
          return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>`;
        }
        return url;
      });

      replaceWithCheck(/^#{1,6} .*/gm, (line) => {
        const level = line.match(/^#+/)[0].length;
        const content = line.slice(level + 1).trim();
        return `<h${level} style="margin: 0;">${content}</h${level}>`;
      });

      replaceWithCheck(/(^|\n)((- .+\n?)+)/g, (match, before, list) => {
        const items = list.trim().split('\n').map(i => i.replace(/^- /, '').trim());
        const lis = items.map(i => `<li>${i}</li>`).join('');
        return `${before}<ul>${lis}</ul>`;
      });

      replaceWithCheck(/(?<!\\)~~(.+?)~~/g, (match, content) => {
        return `<del>${content}</del>`;
      });

      replaceWithCheck(/(?<!\\)~~(.+?)~~/g, (match, content) => {
        return `<del>${content}</del>`;
      });

      replaceWithCheck(/(?<!\\)`([^`\n]+)`/g, (match, code) => {
        return `<code>${code}</code>`;
      });

      replaceWithCheck(/(?<!\\)\*\*(.+?)\*\*/g, (match, content) => {
        return `<strong>${content}</strong>`;
      });

      replaceWithCheck(/(?<!\\)(\*|_)(.+?)\1/g, (match, wrap, content) => {
        if (content.includes('**')) return match;
        return `<em>${content}</em>`;
      });

      replaceWithCheck(/(^|\n)((?:&gt; ?.*(?:\n|$))+)/g, (match, before, quoteBlock) => {
        const lines = quoteBlock
          .trim()
          .split('\n')
          .map(line => line.replace(/^&gt; ?/, ''))
          .join('<br>');
        return `${before}<div class="quote">${lines}</div>`;
      });



      replaceWithCheck(/#([^\s#<]+)/g, (match, channelName) => {
        if (typeof channels === 'undefined' || !Array.isArray(channels)) return match;

        const channel = channels.find(c => c.name.toLowerCase() === channelName.toLowerCase());
        if (channel) {
          const url = `https://chat.wokki20.nl/server/${server_id}/channel/${channel.channel_id}`;
          return `<a href="${url}" rel="noopener noreferrer" class="channel-link">#${channelName}</a>`;
        }
        return match;
      });
      
      replaceWithCheck(/&lt;@([^&]+)&gt;/g, (match, username) => {
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

      replaceWithCheck(/&lt;t:(\d+):(\w+)&gt;/g, (match, timeNumber, type) => {
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

            if (Math.abs(seconds) < 60) {
              formatted = rtf.format(seconds, 'second');
            } else if (Math.abs(minutes) < 60) {
              formatted = rtf.format(minutes, 'minute');
            } else if (Math.abs(hours) < 24) {
              formatted = rtf.format(hours, 'hour');
            } else {
              formatted = rtf.format(days, 'day');
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
            formatted = isoTime;
        }

        return `<time datetime="${isoTime}" data-type="${type}" class="dynamic-time">${formatted}</time>`;
      });

      escaped = escaped.replace(/\n/g, '<br>');

      replaceWithCheck(/(?<!["'>])(https?:\/\/chat\.wokki20\.nl\/invite\/[^\s)]+)/g, (url) => {
        const inviteId = url.split("/").pop();

        try {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", url, false);
            xhr.send();

            let serverName = "Unknown Server";
            let serverImage = "";
            let serverCreatedAt = "Unknown Date";
            let serverId = "";
            let inviteExpired = false;
            if (xhr.status === 200) {
                const html = xhr.responseText;
                const match = html.match(/<meta\s+name=["']server_name["']\s+content=["']([^"']+)["']\s*\/?>/i);
                if (match) serverName = match[1];
                const match2 = html.match(/<meta\s+name=["']server_image["']\s+content=["']([^"']+)["']\s*\/?>/i);
                if (match2) serverImage = match2[1];
                const match3 = html.match(/<meta\s+name=["']server_created_at["']\s+content=["']([^"']+)["']\s*\/?>/i);
                if (match3) serverCreatedAt = match3[1];
                const match5 = html.match(/<meta\s+name=["']server_id["']\s+content=["']([^"']+)["']\s*\/?>/i);
                if (match5) serverId = match5[1];
                const match6 = html.match(/<meta\s+name=["']invite_expired["']\s+content=["']([^"']+)["']\s*\/?>/i);
                if (match6) inviteExpired = match6[1];
            }

            let formattedDate = "Unknown Date";
            const parsedDate = new Date(serverCreatedAt);
            if (!isNaN(parsedDate)) {
                formattedDate = new Intl.DateTimeFormat('en-US', {month: 'short', day: 'numeric', year: 'numeric'}).format(parsedDate);
            }


            const xhr2 = new XMLHttpRequest();
            xhr2.open("GET", `https://chat.wokki20.nl/server/${serverId}`, false);
            xhr2.send();

            let isMember = false;
            if (xhr2.status === 200) {
                const html2 = xhr2.responseText;
                const match4 = html2.match(/<meta\s+name=["']is_in_server["']\s+content=["']([^"']+)["']\s*\/?>/i);
                if (match4) isMember = match4[1];
            }

            return `
            <a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>
            <div class="invite-item-container">
                <div class="invite-item-name-icon-container">
                  <img src="${serverImage}" alt="Server Icon" class="invite-item-icon">
                  <div class="invite-item-name-date-container">
                    <p class="invite-item-name">${sanitize(serverName)}</p>
                    <p class="invite-item-date">${formattedDate}</p>
                  </div>
                </div>
                <button class="invite-item-join-button button-primary-filled" data-invite-id="${inviteId}" ${inviteExpired ? "disabled" : ""} ${inviteExpired ? '' : `onclick="window.location.href = \`https://chat.wokki20.nl/server/${serverId}?invite=${inviteId}\`;"`}> ${isMember ? "Go To Server" : inviteExpired ? "Invite Expired" : "Join Server"}</button>
            </div>
            `;
        } catch (err) {
            console.error(err);
            return `
            <a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>
            <div class="invite-item-container">
                <div class="invite-item-name-icon-container">
                  <div class="invite-item-name-date-container">
                    <p class="invite-item-name">This invite is invalid</p>
                  </div>
                </div>
            </div>
            `;
        }
      });

      replaceWithCheck(/(?<!["'>])(https?:\/\/chat\.jonazwetsloot\.nl\/posts\/[^\s)]+)/g, (url) => {
          const messageId = url.split("/").pop();
          const client_id = "A81E404E-6F93-4347-89D2-A56A0BD962B9";
          const client_secret = "jLM3ig32A51wjN5qkCVPyLAbnglLizbqt77s";

          const tokenRequest = new XMLHttpRequest();
          tokenRequest.open("POST", "https://chat.jonazwetsloot.nl/api/v1/token", false);
          const formData = new FormData();
          formData.append("grant_type", "client_credentials");
          formData.append("client_id", client_id);
          formData.append("client_secret", client_secret);

          tokenRequest.send(formData);

          if (tokenRequest.status !== 200) return "Failed to get access token";

          const tokenData = JSON.parse(tokenRequest.responseText);
          if (!tokenData.access_token) return "Failed to get access token";

          const accessToken = tokenData.access_token;

          const postRequest = new XMLHttpRequest();
          postRequest.open("GET", `https://chat.jonazwetsloot.nl/api/v1/timeline?offset_id=${messageId}&limit=1&sort=time&format=html`, false);
          postRequest.setRequestHeader("Authorization", `Bearer ${accessToken}`);
          postRequest.send();

          if (postRequest.status !== 200) return "Failed to fetch post";

          const postData = JSON.parse(postRequest.responseText);
          if (postData.error) return "Failed to fetch post";

          console.log(postData);

          function convertTime(time) {
            const now = Date.now() / 1000;
            const diff = now - time;

            if (diff < 120) {
              const seconds = Math.round(diff);
              if (seconds === 0) {
                return "Just now";
              } else {
                return `${seconds} second${seconds > 1 ? 's' : ''}`;
              }
            } else if (diff < 60 * 60) {
              const minutes = Math.round(diff / 60);
              return `${minutes} minute${minutes > 1 ? 's' : ''}`;
            } else if (diff < 24 * 60 * 60) {
              const hours = Math.round(diff / (60 * 60));
              return `${hours} hour${hours > 1 ? 's' : ''}`;
            } else if (diff < 30 * 24 * 60 * 60) {
              const days = Math.round(diff / (24 * 60 * 60));
              return `${days} day${days > 1 ? 's' : ''}`;
            } else if (diff < 12 * 30 * 24 * 60 * 60) {
              const months = Math.round(diff / (30 * 24 * 60 * 60));
              return `${months} month${months > 1 ? 's' : ''}`;
            } else {
              const years = Math.round(diff / (12 * 30 * 24 * 60 * 60));
              return `${years} year${years > 1 ? 's' : ''}`;
            }
          }

          let post = `<link rel="stylesheet" href="https://chat.jonazwetsloot.nl/resources/stylesheet.css?v=1.8.3.17" /><div id="13291" class="message event" style="margin: 0px;"><div class="bar"><img class="profile-picture event" alt="Profile picture of ${postData[0].user}" src="https://chat.jonazwetsloot.nl/uploads/${postData[0].picture}"><div class="info"><a class="username js-link" target="_blank" href="https://chat.jonazwetsloot.nl/users/${postData[0].user}">${postData[0].user} <img src="https://chat.jonazwetsloot.nl/svg/verified.svg" title="This account has been verified"></a><p class="friendly-time" data-time="${postData[0].time}">${convertTime(postData[0].time)}</p></div><div class="like"><button></button><p>${postData[0].likes}</p></div></div><div class="content"><p>${postData[0].message}</p>${postData[0].attachments !== "" ? `<div class="gallery blurred event"><div class="blur-info blur-iframe"><h3>This post contains attachments</h3><p>Attachments are hidden for security reasons, you can view them on Chat.</p><p><a style="display: block;" class="button" target="_blank" href="https://chat.jonazwetsloot.nl/posts/${messageId}">View post</a></p></div></div>` : ""}</div></div>`;

          const endHtml = `<iframe style="max-width: 823px; width: 100%; height: 100%; border: none; outline: none; overflow: hidden; border-radius: 16px; " onload="this.style.height = this.contentWindow.document.body.scrollHeight + 'px';" srcdoc='${post}' scrolling="no" ></iframe>`;

          return endHtml;

      });

      escaped = escaped.replace(/\\([*_\-~`\\[\](){}])/g, '$1');

      return escaped;
    }
  });

  const result = processed.join('');

  setTimeout(() => {
    hljs.highlightAll();
  }, 0);

  return result.trim();
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


function show_mentions(query) {
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

function showAvailableCommands(command, textarea) {
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
                server_id,
                channel_id,
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
                server_id,
                channel_id,
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

    document.body.innerHTML += imageViewer;

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

  const escaped = escapeHtml(text);

  return escaped
    .replace(/(\\)?\*\*(.+?)\*\*/g, (match, esc, content) => {
      if (esc) return `<span class="md-escape">\\</span>**${content}**`;
      return `<span class="md-bold"><span class="md-syntax">**</span><b>${content}</b><span class="md-syntax">**</span></span>`;
    })

    .replace(/(\\)?(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)|(\\)?_(.+?)_/g, (match, esc1, g1, esc2, g2) => {
      if (esc1) return `<span class="md-escape">\\</span>*${g1}*`;
      if (esc2) return `<span class="md-escape">\\</span>_${g2}_`;
      const content = g1 || g2;
      return `<span class="md-italic"><span class="md-syntax">*</span><i>${content}</i><span class="md-syntax">*</span></span>`;
    })

    .replace(/(\\)?~~(.+?)~~/g, (match, esc, content) => {
      if (esc) return `<span class="md-escape">\\</span>~~${content}~~`;
      return `<span class="md-strike"><span class="md-syntax">~~</span><del>${content}</del><span class="md-syntax">~~</span></span>`;
    });
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
});