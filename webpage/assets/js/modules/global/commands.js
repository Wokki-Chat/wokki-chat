// modules/global/commands.js
// Module description: This module helps with executing commands.
import { Sanitizer } from "./sanitization.js";

export class CommandsManager {
    constructor({ user_id, channel_id, server_id, access_token, socket }) {
        this.sanitizer = new Sanitizer();
        this.user_id = user_id;
        this.channel_id = channel_id;
        this.server_id = server_id;
        this.access_token = access_token;
        this.available_commands = [];
        this.socket = socket;
        
        this.currentOptions = null;
        this.currentCommand = null;
        this.currentBotId = null;
    }

    init(available_commands) {
        this.available_commands = available_commands;
    }

    show(command, textarea) {
        const existingPopup = document.querySelector(".available-commands");
        if (existingPopup) existingPopup.remove();
        if (this.available_commands.length === 0) return;

        textarea.focus();

        document.querySelector(".input-container-2").insertAdjacentHTML("afterbegin", `
            <div class="available-commands"></div>
        `);

        const container = document.querySelector(".available-commands");
        let matchedCommands = [];

        this.available_commands.forEach(bot => {
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
                        <div class="availible-command-bot-container">
                            <img class="availible-command-bot-profile-picture" src="${profilePicture}" />
                            <div class="availible-command-desc-command">
                                <p class="availible-command-command">${this.sanitizer.sanitize(command_info.command)}</p>
                                <p class="availible-command-desc">${this.sanitizer.sanitize(command_info.description)}</p>
                            </div>
                        </div>
                        <p class="availible-command-username">${username}</p>
                    `;
                    
                    commandDiv.addEventListener("click", () => this.handleCommandClick(commandDiv));
                    
                    container.appendChild(commandDiv);
                }
            });
        });

        this.show.lastMatches = matchedCommands;
    }

    handleCommandClick(commandElement) {
        const textarea = document.getElementById("message-input");
        const preview = document.getElementById("message-input-bg");

        textarea.style.color = "var(--clr-text-a0)";
        preview.style.display = "none";

        const bot_id = commandElement.dataset.botId;
        const commandText = commandElement.dataset.command;

        const bot = this.available_commands.find(b => b.id === bot_id);
        if (!bot) return;

        const commandObj = bot.commands.find(c => c.command === commandText);
        if (!commandObj) return;

        if (commandObj.builtIn) {
            if (commandText === "/update-info") {
                showUpdateInfo(this.sanitizer);
                this.resetComposer();
                const popup = document.querySelector(".available-commands");
                if (popup) popup.remove();
                return;
            }

            if (commandText === "/help") {
                return;
            }
        }

        let options = null;
        if (commandObj.options) {
            try {
                options = JSON.parse(commandObj.options);
            } catch {}
        }

        this.currentCommand = commandText;
        this.currentBotId = bot_id;
        this.currentOptions = options;

        textarea.innerHTML = `<span class="command-input" contenteditable="false">${commandText}<div class="command-options"></div></span>`;

        if (!options || options.length === 0) {
            this.submitCommand({});
            return;
        }

        this.setupOptionInputs(options, textarea);

        const container = document.querySelector(".available-commands");
        if (container) container.remove();
    }

    setupOptionInputs(options, textarea) {
        const commandOptions = textarea.querySelector('.command-options');
        
        options.forEach((option, index) => {
            const optionSpan = document.createElement('span');
            optionSpan.className = 'command-option';
            optionSpan.dataset.optionName = option.option_name;
            optionSpan.dataset.required = option.required || false;
            
            optionSpan.innerHTML = `
                <span class="option-name">${option.option_name}</span>
                <input type="text" 
                       class="command-option-input" 
                       name="${option.option_name}" 
                       style="width:auto;" 
                       autocomplete="off"
                       data-index="${index}">
            `;
            
            commandOptions.appendChild(optionSpan);
        });

        setTimeout(() => {
            const firstInput = textarea.querySelector('.command-option-input');
            if (firstInput) firstInput.focus();
        }, 0);

        const inputs = commandOptions.querySelectorAll('.command-option-input');
        inputs.forEach((input, index) => {
            this.adjustWidth(input);
            
            input.addEventListener('click', (e) => e.stopPropagation());
            
            input.addEventListener('input', () => {
                this.adjustWidth(input);
                setTimeout(() => input.focus(), 0);
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const nextIndex = e.shiftKey ? index - 1 : index + 1;
                    const nextInput = inputs[nextIndex];
                    if (nextInput) {
                        nextInput.focus();
                    }
                }
                
                else if (e.key === 'Backspace' && input.value === '') {
                    e.preventDefault();
                    const optionSpan = input.closest('.command-option');
                    const isRequired = optionSpan.dataset.required === 'true';
                    
                    if (!isRequired || true) {
                        const prevInput = inputs[index - 1];
                        optionSpan.remove();
                        if (prevInput) {
                            prevInput.focus();
                        } else {
                            this.resetComposer();
                        }
                    }
                }
                
                else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.validateAndSubmitCommand();
                }
            });
        });
    }

    validateAndSubmitCommand() {
        const textarea = document.getElementById("message-input");
        if (!this.currentOptions) {
            this.submitCommand({});
            return;
        }

        let valid = true;
        const data = {};

        const remainingOptions = Array.from(textarea.querySelectorAll('.command-option')).map(span => {
            return this.currentOptions.find(opt => opt.option_name === span.dataset.optionName);
        }).filter(Boolean);

        remainingOptions.forEach(option => {
            const inputEl = textarea.querySelector(`input[name="${option.option_name}"]`);
            if (!inputEl) return;

            const errorEl = inputEl.parentElement;
            const val = inputEl.value.trim();

            if (option.required && val === '') {
                valid = false;
                errorEl.classList.add('input-error');
                inputEl.focus();
                this.showError(`Option '${option.option_name}' is required.`);
                return;
            } else {
                errorEl.classList.remove('input-error');
            }

            if (val !== '') {
                const validationResult = this.validateOptionValue(option, val, errorEl, inputEl);
                if (!validationResult.valid) {
                    valid = false;
                    return;
                }
                data[option.option_name] = validationResult.value;
            }
        });

        if (!valid) return;

        this.submitCommand(data);
    }

    validateOptionValue(option, val, errorEl, inputEl) {
        if (option.option_type === 'boolean') {
            const lowered = val.toLowerCase();
            if (lowered !== 'true' && lowered !== 'false') {
                errorEl.classList.add('input-error');
                inputEl.focus();
                this.showError(`Option '${option.option_name}' must be 'true' or 'false'.`);
                return { valid: false };
            }
            return { valid: true, value: lowered === 'true' };
        } 
        
        else if (option.option_type === 'number') {
            if (isNaN(val)) {
                errorEl.classList.add('input-error');
                inputEl.focus();
                this.showError(`Option '${option.option_name}' must be a number.`);
                return { valid: false };
            }
            return { valid: true, value: Number(val) };
        } 
        
        else if (option.option_type === 'user') {
            if (!val.startsWith("@")) {
                errorEl.classList.add('input-error');
                inputEl.focus();
                this.showError(`Option '${option.option_name}' must be a user mention.`);
                return { valid: false };
            }

            const username = val.slice(1);
            const user = usersList.find(u => u.username === username);

            if (!user) {
                errorEl.classList.add('input-error');
                inputEl.focus();
                this.showError(`User @${username} is not in this server.`);
                return { valid: false };
            }

            return { valid: true, value: `${user.username}#${user.id}` };
        } 
        
        else {
            return { valid: true, value: val };
        }
    }

    submitCommand(optionsData) {
        const payload = {
            access_token: this.access_token || "",
            command: this.currentCommand,
            server_id: this.server_id || "",
            channel_id: this.channel_id || "",
            bot_id: this.currentBotId,
            options: optionsData
        };

        this.socket.emit("command", payload);
        this.resetComposer();

        const popup = document.querySelector(".available-commands");
        if (popup) popup.remove();

        this.currentCommand = null;
        this.currentBotId = null;
        this.currentOptions = null;
    }

    resetComposer() {
        const textarea = document.getElementById("message-input");
        const preview = document.getElementById("message-input-bg");
        const messageInputWrapper = document.querySelector('.message-input-wrapper');
        const inputContainer = document.querySelector('.input-container-2');

        if (!textarea || !preview) return;

        textarea.innerHTML = "\u200B";
        textarea.style.color = "transparent";
        preview.style.display = "block";

        if (messageInputWrapper) messageInputWrapper.style.height = "";
        if (inputContainer) inputContainer.style.minHeight = "";

        if (typeof typing !== "undefined" && typing) {
            typing = false;
            const channel_id = this.channel_id || "";
            const server_id = this.server_id || "";
            this.socket.emit("typing", { 
                access_token: this.access_token || "", 
                typing: false, 
                channel_id, 
                server_id 
            });
        }

        textarea.dispatchEvent(new InputEvent("input", { bubbles: true }));

        setTimeout(() => {
            textarea.focus();
            if (textarea.textContent === "\u200B") textarea.textContent = "";
        }, 1);
    }

    adjustWidth(el) {
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

    showError(message) {
        Toastify({
            text: message,
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
    }
}