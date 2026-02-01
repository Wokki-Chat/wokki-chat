// modules/global/sanitization.js
// Module description: This module helps with sanitizing messages.

export class Sanitizer {
    constructor(user_id, channels, server_id) {
        this.user_id = user_id;
        this.channels = channels;
        this.server_id = server_id;
        this.users_list = [];
        setInterval(() => this.formatDynamicTime(), 1000);
        if (typeof window.swup !== "undefined") window.swup.hooks.on('page:view', () => this.formatDynamicTime());
    }

    init(users_list) {
        this.users_list = users_list;
    }

    sanitize(text) {
        const div = document.createElement("div");
        div.innerText = text;
        return div.innerHTML;
    }

    escapeHtml(str) {
        return str.replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[ch]);
    }

    isInsideATag(str, index) {
        const openTagIndex = str.lastIndexOf('<a ', index);
        if (openTagIndex === -1) return false;
        const openTagEnd = str.indexOf('>', openTagIndex);
        if (openTagEnd === -1) return false;
        const closeTagIndex = str.indexOf('</a>', openTagEnd);
        if (closeTagIndex === -1) return false;
        return index >= openTagIndex && index < closeTagIndex + 4;
    }

    isInShortcode(str, index) {
        const before = str.lastIndexOf(':', index);
        const after = str.indexOf(':', index);
        return before !== -1 && after !== -1 && before < index && after > index;
    }

    async replaceWithCheck(str = null, regex, replacer) {
        const matches = [...str.matchAll(regex)];
        for (const match of matches.reverse()) {
            const offset = match.index;
            if (this.isInShortcode(str, offset)) continue;
            str = str.slice(0, offset) + await replacer(...match, offset) + str.slice(offset + match[0].length);
        }
        return str;
    }

    async getTimeEl(timeNum, type) {
        const timestamp = parseInt(timeNum, 10) * 1000;
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
    }

    async sanitizeMsg(text) {
        const parts = text.split(/(```(\w+)?\n[\s\S]*?```)/g);

        let processed = await Promise.all(parts.map(async part => {
            if (!part) return '';
            if (part.startsWith('```')) {
                const match = part.match(/```(\w+)?\n([\s\S]*?)```/);
                if (!match) return '';
                const lang = match[1] || '';
                const code = match[2];
                return `<pre><code class="lang-${lang}" style="white-space: pre-wrap;">${this.escapeHtml(code)}</code></pre>`;
            } else {
                let escaped = this.escapeHtml(part);

                escaped = await this.replaceWithCheck(escaped, /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, async (match, linkText, url) => {
                    return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${linkText}</a>`;
                });

                escaped = await this.replaceWithCheck(escaped, /(?<!["'>])(https?:\/\/[^\s<]+)/g, async (url) => {
                    if (!url.startsWith('https://open.spotify.com/track/') && !url.startsWith('https://chat.wokki20.nl/invite/')) {
                        return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="link">${url}</a>`;
                    }
                    return url;
                });

                escaped = await this.replaceWithCheck(escaped, /^#{1,6} .*/gm, async (line) => {
                    const level = line.match(/^#+/)[0].length;
                    const content = line.slice(level + 1).trim();
                    return `<h${level} style="margin: 0;">${content}</h${level}>`;
                });

                escaped = await this.replaceWithCheck(escaped, /(^|\n)((- .+\n?)+)/g, async (match, before, list) => {
                    const items = list.trim().split('\n').map(i => i.replace(/^- /, '').trim());
                    const lis = items.map(i => `<li>${i}</li>`).join('');
                    return `${before}<ul>${lis}</ul>`;
                });

                escaped = await this.replaceWithCheck(escaped, /(?<!\\)~~(.+?)~~/g, async (match, content) => `<del>${content}</del>`);
                escaped = await this.replaceWithCheck(escaped, /(?<!\\)`([^`\n]+)`/g, async (match, code) => `<code>${code}</code>`);
                escaped = await this.replaceWithCheck(escaped, /(?<!\\)\*\*(?!\*\*)([^*]+?)\*\*/g, async (match, content, offset) => {
                    const before = escaped[offset - 1] || ' ';
                    const after = escaped[offset + match.length] || ' ';
                    if (/\w/.test(before) && /\w/.test(after)) return match;
                    return `<strong>${content}</strong>`;
                });

                escaped = await this.replaceWithCheck(escaped, /(?<!\\)(\*|_)(.+?)\1/g, async (match, wrap, content, offset) => {
                    const before = escaped[offset - 1] || ' ';
                    const after = escaped[offset + match.length] || ' ';
                    if (/\w/.test(before) && /\w/.test(after)) return match;
                    if (content.includes(wrap)) return match;
                    return `<em>${content}</em>`;
                });

                escaped = await this.replaceWithCheck(escaped, /(^|\n)((?:&gt; ?.*(?:\n|$))+)/g, async (match, before, quoteBlock) => {
                    const lines = quoteBlock.trim().split('\n').map(line => line.replace(/^&gt; ?/, '')).join('<br>');
                    return `${before}<div class="quote">${lines}</div>`;
                });

                escaped = await this.replaceWithCheck(escaped, /#([^\s#<]+)/g, async (match, channelName) => {
                    if (!Array.isArray(this.channels)) return match;
                    const channel = this.channels.find(c => c.name.toLowerCase() === channelName.toLowerCase());
                    if (channel) {
                        const url = `https://chat.wokki20.nl/server/${server_id}/channel/${channel.channel_id}`;
                        return `<a href="${url}" rel="noopener noreferrer" class="channel-link">#${channelName}</a>`;
                    }
                    return match;
                });

                escaped = await this.replaceWithCheck(escaped, /&lt;@([^&]+)&gt;/g, async (match, username) => {
                    const cleanUsername = username.trim();
                    if (cleanUsername.toLowerCase() === "everyone") {
                        return `<a href="#" rel="noopener noreferrer" class="user-link everyone self" data-user-id="everyone">@everyone</a>`;
                    }
                    const user = this.users_list.find(u => u.username.toLowerCase() === cleanUsername.toLowerCase());
                    if (user) {
                        const url = `https://chat.wokki20.nl/profile/@${encodeURIComponent(cleanUsername)}`;
                        return `<a href="${url}" rel="noopener noreferrer" class="user-link ${user.id == user_id ? "self" : ""}" data-user-id="${user.id}">@${cleanUsername}</a>`;
                    }
                    return match;
                });

                escaped = await this.replaceWithCheck(escaped, /&lt;t:(\d+):(\w+)&gt;/g, async (match, timeNumber, type) => {
                    return await this.getTimeEl(timeNumber, type);
                });

                escaped = escaped.replace(/\n/g, '<br>');

                escaped = await this.replaceWithCheck(escaped, /(?<!["'>])(https?:\/\/chat\.wokki20\.nl\/invite\/[^\s)]+)/g, async (url) => {
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

                escaped = await this.replaceWithCheck(escaped, /(?<!["'>])(https?:\/\/open\.spotify\.com\/track\/[0-9A-Za-z]+)(\?[^\s]*)?/g, async (url) => {
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

                return escaped;
            }
        }));

        const result = processed.join('');

        setTimeout(() => { hljs.highlightAll(); }, 0);

        return result.trim();
    }

    async sanitizeMrk(text) {
        text = this.escapeHtml(text);

        text = await this.replaceWithCheck(text, /(^|\n)((- .+\n?)+)/g, (match, before, list) => {
            const items = list.trim().split('\n').map(i => i.replace(/^- /, '').trim());
            const lis = items.map(i => `<li>${i}</li>`).join('');
            return `${before}<ul>${lis}</ul>`;
        });

        text = await this.replaceWithCheck(text, /(?<!\\)~~(.+?)~~/g, (match, content) => `<del>${content}</del>`);
        text = await this.replaceWithCheck(text, /(?<!\\)`([^`\n]+)`/g, (match, code) => `<code>${code}</code>`);

        text = await this.replaceWithCheck(text, /(?<!\\)\*\*(?!\*\*)([^*]+?)\*\*/g, (match, content, offset) => {
            const before = text[offset - 1] || ' ';
            const after = text[offset + match.length] || ' ';
            if (/\w/.test(before) && /\w/.test(after)) return match;
            return `<strong>${content}</strong>`;
        });

        text = await this.replaceWithCheck(text, /(?<!\\)(\*|_)([^*_]+?)\1/g, (match, wrap, content, offset) => {
            const before = text[offset - 1] || ' ';
            const after = text[offset + match.length] || ' ';
            if (/\w/.test(before) && /\w/.test(after)) return match;
            if (content.includes(wrap)) return match;
            return `<em>${content}</em>`;
        });

        text = await this.replaceWithCheck(text, /(^|\n)((?:&gt; ?.*(?:\n|$))+)/g, (match, before, quoteBlock) => {
            const lines = quoteBlock.trim().split('\n').map(line => line.replace(/^&gt; ?/, '')).join('<br>');
            return `${before}<div class="quote">${lines}</div>`;
        });

        text = await this.replaceWithCheck(text, /&lt;t:(\d+):(\w+)&gt;/g, (match, timeNumber, type) => {
            return this.getTimeEl(timeNumber, type);
        });

        text = text.replace(/\n/g, '<br>');
        text = text.replace(/\\([*_\-~`\\[\](){}])/g, '$1');

        return text;
    }

    formatDynamicTime() {
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
}

export class TextareaFormatter extends Sanitizer {
    constructor(user_id, channels, server_id, textarea) {
        super(user_id, channels, server_id);
        this.textarea = textarea;
    }

    cleanMsg() {
        const clone = this.textarea.cloneNode(true);

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

    format(text) {
        const escaped = this.escapeHtml(text);

        return escaped
            .replace(/(\\)?\*\*(.+?)\*\*/g, (match, esc, content, offset) => {
                if (esc) return `<span class="md-escape">\\</span>**${content}**`;
                if (this.isInShortcode(escaped, offset)) return match;
                return `<span class="md-bold"><span class="md-syntax">**</span><b>${content}</b><span class="md-syntax">**</span></span>`;
            })

            .replace(/(\\)?([*_])([^*_]+?)\2/g, (match, esc, wrap, content, offset) => {
                if (esc) return `<span class="md-escape">\\</span>${wrap}${content}${wrap}`;
                if (this.isInShortcode(escaped, offset)) return match;
                const before = escaped[offset - 1] || ' ';
                const after = escaped[offset + match.length] || ' ';
                if (/\w/.test(before) && /\w/.test(after)) return match;
                if (content.includes(wrap)) return match;
                return `<span class="md-italic"><span class="md-syntax">${wrap}</span><i>${content}</i><span class="md-syntax">${wrap}</span></span>`;
            })

            .replace(/(\\)?~~(.+?)~~/g, (match, esc, content, offset) => {
                if (esc) return `<span class="md-escape">\\</span>~~${content}~~`;
                if (this.isInShortcode(escaped, offset)) return match;
                return `<span class="md-strike"><span class="md-syntax">~~</span><del>${content}</del><span class="md-syntax">~~</span></span>`;
            });
    }
}