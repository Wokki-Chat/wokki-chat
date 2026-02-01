// modules/users/popups.js
// Module description: This module helps with showing user popups.
import { Sanitizer } from "../global/sanitization.js";
import emojis from "../../emojis.js";

export class UserPopupManager {
    constructor({ user_id, access_token }) {
        this.user_id = user_id;
        this.sanitizer = new Sanitizer(user_id);
        this.user_info = null;
        this.access_token = access_token;
    }

    async fetchUserInfo(user_id) {
        let headers = new Headers();
        headers.append('Authorization', `Bearer ${this.access_token}`);
        let response = await fetch(`https://chat.wokki20.nl/app/user_info?user_id=${user_id}`, { headers });
        let data = await response.json();
        if (data.error) {
            throw new Error(data.error);
        }
        this.user_info = data.user;
    }

    async openUserPopup(user) {
        let popup = document.createElement("div");
        popup.className = "user-info-profile-popup";
        popup.setAttribute("data-user-id", user.id);
        let lightText = false;
        if (user.profile_color_primary && user.profile_color_accent) {
            let color = user.profile_color_primary;
            let r = parseInt(color.slice(1,3),16);
            let g = parseInt(color.slice(3,5),16);
            let b = parseInt(color.slice(5,7),16);
            r = Math.max(0, r - r * 0.1);
            g = Math.max(0, g - g * 0.1);
            b = Math.max(0, b - b * 0.1);
            let darker = `#${((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1)}`;
            popup.style.background = darker;
            popup.style.border = `4px solid ${user.profile_color_accent}`;

            let brightness = (r*299 + g*587 + b*114) / 1000;
            lightText = brightness <= 150;
            popup.dataset.lightText = lightText.toString();
            popup.dataset.customStyle = 'true';
        }
        let userBio = await this.sanitizer.sanitizeMrk(user.bio);
        userBio = await emojis.replaceText(userBio);
        popup.innerHTML = `
            ${user.profile_banner ? `<img draggable="false" class="user-info-profile-popup-banner" src="${user.profile_banner}">` : ''}
            <div class="user-info-profile-popup-profile-picture-username-status">
                <div class="user-info-profile-popup-profile-status">
                    <img draggable="false" class="dm-info-profile-picture" src="${user.profile_picture}">
                    <div class="user-info-profile-popup-status-circle-outer">
                        <div class="user-info-profile-popup-status-circle-inner ${user.status}"></div>
                    </div>
                </div>
                <div class="user-info-profile-popup-status-username">
                    <div class="user-info-profile-popup-username-container"><p class="user-info-profile-popup-username">${user.display_name ? this.sanitizer.sanitize(user.display_name) : this.sanitizer.sanitize(user.username)}</p>${user.bot ? '<div class="bot-tag"><span class="material-symbols-rounded">check</span>BOT</div>' : ''}</div>
                    <p class="user-info-profile-popup-status">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</p>
                </div>
            </div>
            <div class="dm-info-container" ${user.profile_color_primary && user.profile_color_accent ? `style="background-color: rgba(255, 255, 255, 0.1); border: none;"` : ''}>
                <div class="dm-info-item">
                    <p class="dm-info-item-value dm-info-item-username-original">${this.sanitizer.sanitize(user.username)}</p>
                </div>
                <div class="dm-info-tags" ${!user.premium && (!user.tags || user.tags.length === 0 ) ? 'style="display: none;"' : ''}>
                    ${user.premium ? '<div class="dm-info-tag"><img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/tag_premium.svg"><p class="dm-info-tag-tooltip">Premium</p></div>' : ''}
                </div>	
                <div class="dm-info-item">
                    <p class="dm-info-item-key">Bio</p>
                    <p class="dm-info-item-value">${user.bio ? userBio : user.bot ? 'This bot has no bio yet' : 'This user has no bio yet'}</p>
                </div>
                <div class="dm-info-item">
                    <p class="dm-info-item-key">Joined on</p>
                    <p class="dm-info-item-value">${new Intl.DateTimeFormat('en-US', {month: 'short', day: 'numeric', year: 'numeric'}).format(new Date(user.created_at))}</p>
                </div>
                ${user.bot ? '' : `
                    <a class="button-primary-filled no-underline dm-info-profile-link" ${user.profile_color_primary && user.profile_color_accent ? `data-custom-style="true" style="color: rgb(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}); background-color: rgba(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}, 0.1); border: 1px solid rgba(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}, 0.3);"` : ''} href="/profile/@${encodeURIComponent(user.username)}">View full profile</a>
                `}
            </div>

            ${user.widgets?.Spotify?.item ? `
                <div class="dm-info-container" ${user.profile_color_primary && user.profile_color_accent ? `style="background-color: rgba(255, 255, 255, 0.1); border: none;"` : ''}>
                    <div class="dm-info-item">
                        <p class="dm-info-item-key">Playing Spotify</p>
                        <div class="spotify-info">
                            <div class="spotify-info-cover">
                                <img src="${user.widgets.Spotify.item.album.images[0]?.url}" alt="Album cover" />
                            </div>
                            <div class="spotify-info-text">
                                <a href="${user.widgets.Spotify.item.external_urls.spotify}" target="_blank" class="spotify-track-name" title="${user.widgets.Spotify.item.name}">
                                    ${user.widgets.Spotify.item.name.length > 23 ? user.widgets.Spotify.item.name.slice(0, 20) + '…' : user.widgets.Spotify.item.name}
                                </a>
                                <p class="spotify-artists">
                                    ${user.widgets.Spotify.item.artists.map(artist => {
                                        const name = artist.name.length > 18 ? artist.name.slice(0, 15) + '…' : artist.name;
                                        return `<a href="${artist.external_urls.spotify}" target="_blank" title="${artist.name}">${name}</a>`;
                                    }).join(', ')}
                                </p>
                                <div class="spotify-progress-container">
                                    <span class="spotify-time-left">${msToTime(user.widgets.Spotify.progress_ms)}</span>
                                    <div class="spotify-progress-bar-wrapper">
                                        <div class="spotify-progress-bar"></div>
                                    </div>
                                    <span class="spotify-time-right">${msToTime(user.widgets.Spotify.item.duration_ms)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;

        user.tags.forEach(tag => {
            const tagEl = document.createElement("div");
            tagEl.classList.add("dm-info-tag");
            tagEl.innerHTML = `
                <img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/${tag.tag_icon}.svg">
                <p class="dm-info-tag-tooltip">${tag.tag_name}</p>
            `;
            popup.querySelector(".dm-info-tags").appendChild(tagEl);
        });

        return popup;
    }

    async makeExtendedPopup(user) {
        let lightText = false;
        let popupStyle = '';
        let borderStyle = '';
        let customStyleData = false;

        if (user.profile_color_primary && user.profile_color_accent) {
            let color = user.profile_color_primary;
            let r = parseInt(color.slice(1,3),16);
            let g = parseInt(color.slice(3,5),16);
            let b = parseInt(color.slice(5,7),16);
            r = Math.max(0, r - r * 0.1);
            g = Math.max(0, g - g * 0.1);
            b = Math.max(0, b - b * 0.1);
            let darker = `#${((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1)}`;
            popupStyle = `${darker}`;
            borderStyle = `4px solid ${user.profile_color_accent}`;

            let brightness = (r*299 + g*587 + b*114) / 1000;
            lightText = brightness <= 150;
            customStyleData = true;
        }

        let userBio = await this.sanitizer.sanitizeMrk(user.bio);
        userBio = await emojis.replaceText(userBio);

        let tagsHtml = '';
        if (user.tags && user.tags.length) {
            user.tags.forEach(tag => {
                tagsHtml += `
                    <div class="dm-info-tag">
                        <img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/${tag.tag_icon}.svg">
                        <p class="dm-info-tag-tooltip">${tag.tag_name}</p>
                    </div>
                `;
            });
        }

        let html = `

        `;

        return {html, custom_style_data: customStyleData, popup_style: popupStyle, border_style: borderStyle, lightText};
    }

    async openExtendedPopup(user_id) {
        if (this.user_info === null) {
            await this.fetchUserInfo(user_id);
        }
        
        const user = this.user_info;

        const { html: popup_content, custom_style_data, popup_style, border_style, lightText } = await this.makeExtendedPopup(user);

        jspt.makePopup({
            content_type: "html",
            header: "Profile of <b>@" + this.sanitizer.sanitize(user.username) + "</b>",
            custom_id: "user-profile-popup",
            content: popup_content,
        });

        const popup = document.querySelector("#user-profile-popup").querySelector(".popup");

        if (custom_style_data && popup) {
            popup.setAttribute('data-light-text', lightText);
            popup.setAttribute('data-custom-style', 'true');
            if (popup_style) popup.style.setProperty('background', popup_style, 'important');
            if (border_style) popup.style.setProperty('border', border_style, 'important');
        }

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                const popup = document.getElementById("user-profile-popup");
                if (popup) jspt.closePopup("user-profile-popup");
            }
        });

        document.querySelector("#user-profile-popup").addEventListener("click", (e) => {
            if (!e.target.closest(".popup")) {
                jspt.closePopup("user-profile-popup");
            }
        });
    }
}