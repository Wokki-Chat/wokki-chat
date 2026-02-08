// modules/global/commands.js
// Module description: This module helps with executing commands.
import { Sanitizer } from "./sanitization.js";

export class CommandsManager {
    constructor({ user_id, channel_id, server_id, access_token, socket }) {
        this.sanitizer = new Sanitizer(user_id, channel_id, server_id);
        this.user_id = user_id;
        this.channel_id = channel_id;
        this.server_id = server_id;
        this.access_token = access_token;
        this.available_commands = [];
        this.socket = socket;
        this.currentOptionIndex = 0;
    }

    init(available_commands) {
        this.available_commands = available_commands;
    }

    show(input, textarea) {
        const existingPopup = document.querySelector(".available-commands");
        if (existingPopup) existingPopup.remove();
        if (this.available_commands.length === 0) return;

        textarea.focus();
        document.querySelector(".input-container-2").insertAdjacentHTML("afterbegin", `<div class="available-commands"></div>`);
        const container = document.querySelector(".available-commands");
        const matches = [];

        this.available_commands.forEach(bot => {
            const { profile_picture, username, id: bot_id } = bot;
            bot.commands.forEach(cmd => {
                if (cmd.command.toLowerCase().startsWith(input.toLowerCase())) {
                    matches.push({ command: cmd.command, bot_id });

                    const div = document.createElement('div');
                    div.className = "available-command";
                    div.dataset.command = cmd.command;
                    div.dataset.botId = bot_id;
                    div.innerHTML = `
                        <div class="availible-command-bot-container">
                            <img class="availible-command-bot-profile-picture" src="${profile_picture}" />
                            <div class="availible-command-desc-command">
                                <p class="availible-command-command">${this.sanitizer.sanitize(cmd.command)}</p>
                                <p class="availible-command-desc">${this.sanitizer.sanitize(cmd.description)}</p>
                            </div>
                        </div>
                        <p class="availible-command-username">${username}</p>
                    `;
                    container.appendChild(div);
                    div.addEventListener("click", () => this.select(div, textarea));
                }
            });
        });

        this.show.lastMatches = matches;
    }

    select(div, textarea) {
        const bot_id = div.dataset.botId;
        const commandText = div.dataset.command;
        const bot = this.available_commands.find(b => b.id === bot_id);
        if (!bot) return;
        const cmd = bot.commands.find(c => c.command === commandText);
        if (!cmd) return;

        if (cmd.builtIn) {
            if (commandText === "/update-info") {
                showUpdateInfo(this.sanitizer);
                this.reset(textarea);
                document.querySelector(".available-commands")?.remove();
                return;
            }
            if (commandText === "/help") return;
        }

        textarea.innerHTML = `<span class="command-input" contenteditable="false">${commandText}<div class="command-options"></div></span>`;
        this.handleOptions(cmd, textarea, bot_id);
        document.querySelector(".available-commands")?.remove();
    }

    handleOptions(cmd, textarea, bot_id) {
        const options = cmd.options ? JSON.parse(cmd.options || "[]") : [];
        const container = textarea.querySelector('.command-options');

        options.forEach(opt => {
            container.innerHTML += `
                <span class="command-option">${opt.option_name}</span>
            `;
        });

        const inputs = Array.from(container.querySelectorAll('.command-option-input'));
        if (inputs.length) inputs[0].focus();
        this.currentOptionIndex = 0;

        const focusNext = (backward = false) => {
            if (!inputs.length) return;
            this.currentOptionIndex += backward ? -1 : 1;
            if (this.currentOptionIndex < 0) this.currentOptionIndex = inputs.length - 1;
            if (this.currentOptionIndex >= inputs.length) this.currentOptionIndex = 0;
            inputs[this.currentOptionIndex].focus();
        };

        inputs.forEach((input, index) => {
            input.addEventListener('click', e => e.stopPropagation());
            input.addEventListener('input', () => { this.adjust(input); input.focus(); });

            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.send(cmd, textarea, bot_id);
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    focusNext(e.shiftKey);
                } else if (e.key === 'Backspace' && input.value === '') {
                    e.preventDefault();
                    input.parentElement.remove();
                    const newInputs = Array.from(container.querySelectorAll('.command-option-input'));
                    this.currentOptionIndex = Math.min(index, newInputs.length - 1);
                    if (newInputs.length) newInputs[this.currentOptionIndex].focus();
                }
            });
        });
    }

    send(cmd, textarea, bot_id) {
        const options = cmd.options ? JSON.parse(cmd.options || "[]") : [];
        const data = {};
        let valid = true;

        options.forEach(opt => {
            const input = textarea.querySelector(`input[name="${opt.option_name}"]`);
            if (!input) return;
            const val = input.value.trim();

            if (opt.required && val === '') {
                valid = false;
                input.parentElement.classList.add('input-error');
                input.focus();
                Toastify({ text: `Option '${opt.option_name}' is required.`, className:"copy-code-failed", duration:3000 }).showToast();
                return;
            }

            if (val !== '') {
                if (opt.option_type === 'boolean') data[opt.option_name] = val.toLowerCase() === 'true';
                else if (opt.option_type === 'number') data[opt.option_name] = Number(val);
                else if (opt.option_type === 'user') data[opt.option_name] = val;
                else data[opt.option_name] = val;
            }
        });

        if (!valid) return;

        const payload = {
            access_token: this.access_token || "",
            command: cmd.command,
            server_id: this.server_id || "",
            channel_id: this.channel_id || "",
            bot_id,
            options: data
        };

        this.socket.emit("command", payload);
        this.reset(textarea);
    }

    reset(textarea) {
        const preview = document.getElementById("message-input-bg");
        const messageInputWrapper = document.querySelector('.message-input-wrapper');
        const inputContainer = document.querySelector('.input-container-2');

        if (!textarea || !preview) return;

        textarea.innerHTML = "\u200B";
        textarea.style.color = "transparent";
        preview.style.display = "block";

        if (messageInputWrapper) messageInputWrapper.style.height = "";
        if (inputContainer) inputContainer.style.minHeight = "";

        textarea.dispatchEvent(new InputEvent("input", { bubbles: true }));
        setTimeout(() => {
            textarea.focus();
            if (textarea.textContent === "\u200B") textarea.textContent = "";
        }, 1);
    }

    adjust(el) {
        const temp = document.createElement('span');
        temp.style.position = 'absolute';
        temp.style.visibility = 'hidden';
        temp.style.whiteSpace = 'pre';
        temp.style.font = getComputedStyle(el).font;
        temp.textContent = el.value || el.placeholder || '';
        document.body.appendChild(temp);
        el.style.width = (temp.offsetWidth + 5) + 'px';
        document.body.removeChild(temp);
    }
}
