// modules/users/popups.js
// Module description: This module helps with showing user popups.
import { Sanitizer } from "../global/sanitization.js";
import emojis from "../../emojis.js";

export class UserPopupManager {
    constructor({ user_id, access_token }) {
        this.user_id = user_id;
        this.sanitizer = new Sanitizer(user_id);
        this.access_token = access_token;
    }

    async getConnections(user) {
        if (!user.connections) {
            return '';
        }

        let connections = '';
        user.connections.forEach(connection => {
            connections += `
            <a class="connection-item" href="${connection.connection_user_url}" target="_blank">
                <img src="/assets/icons/connections/${connection.connection_type.toLowerCase()}.svg" alt="${connection.connection_type} Icon" title="${connection.connection_type}" class="connection-icon">
                <div class="connection-name">${connection.connection_name}<span class="material-symbols-rounded connection-open-icon">open_in_new</span></div>
            </a>`;
        });

        return connections;
    }


    async getGithubWidget(github) {
        if (!github?.data?.user?.contributionsCollection?.contributionCalendar?.weeks) {
            return '';
        }

        const weeks = github.data.user.contributionsCollection.contributionCalendar.weeks;
        const now = new Date();
        const fourMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 3, 1);

        const getOrdinal = (n) => {
            const s = ["th", "st", "nd", "rd"];
            const v = n % 100;
            return n + (s[(v - 20) % 10] || s[v] || s[0]);
        };

        const allDays = [];
        weeks.forEach(week => {
            week.contributionDays.forEach(day => {
                const d = new Date(day.date);
                if (d >= fourMonthsAgo && d <= now) {
                    allDays.push({
                        ...day,
                        dateObj: d,
                        weekday: d.getDay()
                    });
                }
            });
        });

        allDays.sort((a, b) => a.dateObj - b.dateObj);

        let currentWeekCol = 1;
        let lastWeekday = -1;
        
        allDays.forEach(day => {
            if (lastWeekday === 6 && day.weekday === 0) {
                currentWeekCol++;
            } else if (lastWeekday > day.weekday && lastWeekday !== 6) {
                currentWeekCol++;
            }
            
            day.weekCol = currentWeekCol;
            lastWeekday = day.weekday;
        });

        const totalWeeks = currentWeekCol;

        const weeksByCol = {};
        allDays.forEach(day => {
            if (!weeksByCol[day.weekCol]) {
                weeksByCol[day.weekCol] = [];
            }
            weeksByCol[day.weekCol].push(day);
        });

        const weekMonths = {};
        Object.keys(weeksByCol).forEach(weekCol => {
            const daysInWeek = weeksByCol[weekCol];
            const monthCounts = {};
            
            daysInWeek.forEach(day => {
                const month = day.dateObj.toLocaleString('default', { month: 'short' });
                monthCounts[month] = (monthCounts[month] || 0) + 1;
            });
            
            let maxMonth = null;
            let maxCount = 0;
            Object.keys(monthCounts).forEach(month => {
                if (monthCounts[month] > maxCount) {
                    maxCount = monthCounts[month];
                    maxMonth = month;
                }
            });
            
            if (maxCount >= 4) {
                weekMonths[weekCol] = maxMonth;
            }
        });

        const monthSpans = [];
        let currentMonth = null;
        let monthStartCol = null;
        
        for (let col = 1; col <= totalWeeks; col++) {
            const weekMonth = weekMonths[col];
            
            if (weekMonth !== currentMonth) {
                if (currentMonth !== null && monthStartCol !== null) {
                    monthSpans.push({
                        month: currentMonth,
                        start: monthStartCol,
                        end: col - 1
                    });
                }
                
                if (weekMonth) {
                    currentMonth = weekMonth;
                    monthStartCol = col;
                } else {
                    currentMonth = null;
                    monthStartCol = null;
                }
            }
        }
        
        if (currentMonth !== null && monthStartCol !== null) {
            monthSpans.push({
                month: currentMonth,
                start: monthStartCol,
                end: totalWeeks
            });
        }

        const monthsHTML = monthSpans.map(m => 
            `<div style="grid-column: ${m.start} / ${m.end + 1}; text-align: center;">${m.month}</div>`
        ).join('');

        const gridHTML = allDays.map(day => {
            const row = day.weekday + 1;
            const col = day.weekCol;
            const monthName = day.dateObj.toLocaleString('default', { month: 'long' });
            const dateOrdinal = getOrdinal(day.dateObj.getDate());
            const contributionText = day.contributionCount !== 1 ? 's' : '';
            
            return `<div class="github-commit-day" 
                title="${day.contributionCount} Contribution${contributionText} on ${monthName} ${dateOrdinal}" 
                style="background: ${day.color}; grid-column: ${col}; grid-row: ${row};"></div>`;
        }).join('');

        const containerStyle = user.profile_color_primary && user.profile_color_accent 
            ? 'style="background-color: rgba(255, 255, 255, 0.1); border: none;"' 
            : '';

        return `
            <style>
                .github-months { 
                    display: grid; 
                    grid-template-columns: repeat(${totalWeeks}, 14px);
                    margin-left: 20px; 
                    gap: 3px; 
                    font-size: 10px; 
                    margin-bottom: 5px;
                }
            </style>
            <div class="dm-info-container" ${containerStyle}>
                <div class="dm-info-item">
                    <p class="dm-info-item-key">GitHub Contributions</p>
                    <p class="dm-info-item-key-desc">Over the last 4 months</p>
                    <div class="github-info">
                        <div class="github-commit-container">
                            <div class="github-months">${monthsHTML}</div>
                            <div class="github-commit-grid-container">
                                <div class="github-commit-labels">
                                    ${['S', 'M', 'T', 'W', 'T', 'F', 'S'].map(d => `<div>${d}</div>`).join('')}
                                </div>
                                <div class="github-commit-grid">
                                    ${gridHTML}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
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
        if (userBio.length > 55) {
            userBio = userBio.slice(0, 55) + '<span class="cutoff">...</span>';
        }
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
                    <div class="dm-info-item-value">${user.bio ? userBio : user.bot ? 'This bot has no bio yet' : 'This user has no bio yet'}</div>
                </div>
                <div class="dm-info-item">
                    <p class="dm-info-item-key">Joined on</p>
                    <p class="dm-info-item-value">${new Intl.DateTimeFormat('en-US', {month: 'short', day: 'numeric', year: 'numeric'}).format(new Date(user.created_at))}</p>
                </div>
            </div>

            ${user.widgets?.Spotify?.item
                ? `
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
                `
                : user.widgets?.GitHub
                    ? await this.getGithubWidget(user.widgets.GitHub)
                    : ''
            }
        `;

        if (!user.bot) {
            const profileLink = document.createElement("a");
            profileLink.className = "button-primary-filled no-underline dm-info-profile-link";
            profileLink.href = `/profile/@${encodeURIComponent(user.username)}`;
            profileLink.textContent = "View full profile";

            if (user.profile_color_primary && user.profile_color_accent) {
                profileLink.dataset.customStyle = "true";
                profileLink.style.color = `rgb(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0})`;
                profileLink.style.backgroundColor = `rgba(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}, 0.1)`;
                profileLink.style.border = `1px solid rgba(${lightText ? 255 : 0}, ${lightText ? 255 : 0}, ${lightText ? 255 : 0}, 0.3)`;
            }

            profileLink.addEventListener("click", e => {
                const isNewTab = e.ctrlKey || e.metaKey || e.button === 1;
                if (isNewTab) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                this.openExtendedPopup(user.id);
            });
            
            popup.querySelector(".dm-info-container").appendChild(profileLink);
        }

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
            <div class="user-info-profile-static user-info-profile-popup-static" ${customStyleData ? `data-custom-style="${customStyleData}" data-light-text="${lightText}" style="border: none !important; background: transparent !important;"` : ''}>
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
                        <div class="dm-info-item-value">${user.bio ? userBio : user.bot ? 'This bot has no bio yet' : 'This user has no bio yet'}</div>
                    </div>
                    <div class="dm-info-item">
                        <p class="dm-info-item-key">Joined on</p>
                        <p class="dm-info-item-value">${new Intl.DateTimeFormat('en-US', {month: 'short', day: 'numeric', year: 'numeric'}).format(new Date(user.created_at))}</p>
                    </div>
                </div>
            </div>
            <div class="user-widgets">
                <p class="dm-info-item-key">Widgets</p>
                <div class="user-widgets-content">
                    <p class="dm-info-item-value">Loading widgets...</p>
                </div>

                <p class="dm-info-item-key">Connections</p>
                <div class="connections-list">
                    ${ (await this.getConnections(user)) || '<p class="dm-info-item-value">This user has no connections</p>' }
                </div>
            </div>
        `;

        return {html, custom_style_data: customStyleData, popup_style: popupStyle, border_style: borderStyle, lightText};
    }

    async openExtendedPopup(user_id) {
        const headers = new Headers();
        headers.append('Authorization', `Bearer ${this.access_token}`);

        const res = await fetch(`https://chat.wokki20.nl/app/user_info?user_id=${user_id}`, { headers });
        const reader = res.body.getReader();
        const decoder = new TextDecoder();

        let profileData = null;
        let popupCreated = false;

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value, { stream: true }).trim();
            if (!chunk) continue;

            try {
                const data = JSON.parse(chunk);
                if (data.user && !popupCreated) {
                    profileData = data.user;

                    const { html, custom_style_data, popup_style, border_style, lightText } = await this.makeExtendedPopup(profileData);

                    jspt.makePopup({
                        content_type: "html",
                        header: "Profile of <b>@" + this.sanitizer.sanitize(profileData.username) + "</b>",
                        custom_id: "user-profile-popup",
                        content: html,
                    });

                    const popup = document.querySelector("#user-profile-popup .popup");
                    if (custom_style_data && popup) {
                        popup.setAttribute('data-light-text', lightText);
                        popup.setAttribute('data-custom-style', 'true');
                        if (popup_style) popup.style.setProperty('background', popup_style, 'important');
                        if (border_style) popup.style.setProperty('border', border_style, 'important');
                    }

                    popupCreated = true;
                }
                if (data.widgets && popupCreated) {
                    const widgetsDiv = document.querySelector(".user-widgets-content");
                    if (!widgetsDiv) return;
                    widgetsDiv.innerHTML = '';
                    const githubWidget = data.widgets['GitHub'];
                    if (githubWidget) {
                        if (githubWidget.error) {
                            widgetsDiv.innerHTML = `<p class="dm-info-item-value">GitHub widget error: ${githubWidget.error}</p>`;
                        } else {
                            console.log(githubWidget);
                            widgetsDiv.innerHTML = await this.getGithubWidget(githubWidget);
                        }
                    } else {
                        widgetsDiv.innerHTML = '<p class="dm-info-item-value">This user has no widgets</p>';
                    }
                }
            } catch(e) {
            }
        }
        const currentPageUrl = window.location.href;
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                const popup = document.getElementById("user-profile-popup");
                if (popup) jspt.closePopup("user-profile-popup");
                window.history.replaceState({}, '', currentPageUrl);
            }
        });

        document.querySelector("#user-profile-popup").addEventListener("click", (e) => {
            if (!e.target.closest(".popup")) {
                jspt.closePopup("user-profile-popup");
                window.history.replaceState({}, '', currentPageUrl);
            }
        });

        document.querySelector(".popup-header-close").addEventListener("click", () => {
            window.history.replaceState({}, '', currentPageUrl);
        });

        window.history.replaceState({}, '', `https://chat.wokki20.nl/profile/@${this.sanitizer.sanitize(profileData.username)}`);
    }
}