function generatePassword(length = 24) {
    const array = new Uint8Array(length / 2);
    crypto.getRandomValues(array);
    return [...array].map(b => b.toString(16).padStart(2, '0')).join('');
}

function showError(message) {
    const errorElement = document.getElementById('error-message');
    errorElement.innerText = message;
    errorElement.style.display = 'block';
}

function create_account() {
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const acceptTerms = document.getElementById('accept-terms').checked;

    if (!email) {
        showError('Email is required');
        return;
    }

    if (!acceptTerms) {
        showError('You must accept the Privacy Policy and Terms of Service');
        return;
    }

    if (password.length < 8) {
        showError('Password must be at least 8 characters long');
        return;
    }

    if (/^\s*$/.test(password)) {
        showError('Password cannot contain only spaces');
        return;
    }

    if (/[^\x20-\x7E]/.test(password)) {
        showError('Password contains invalid characters');
        return;
    }

    if (/[^\x20-\x7E]/.test(username)) {
        showError('Username contains invalid characters');
        return;
    }

    if (/^\s*$/.test(username)) {
        showError('Username cannot contain only spaces');
        return;
    }

    if (/[^a-zA-Z0-9\- ]/.test(username)) {
        showError('Username can only contain letters, numbers, and hyphens');
        return;
    }

    if (username.length < 3) {
        showError('Username must be at least 3 characters long');
        return;
    }

    if (/[\n\r]/.test(username)) {
        showError('Username cannot contain newlines');
        return;
    }

    const formData = new FormData();
    formData.append('username', username);
    formData.append('email', email);
    formData.append('password', password);

    fetch('https://chat.wokki20.nl/app/create_account', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {

        if (result.status === 'error') {
            showError(result.description);
        } else {
            window.location.href = '/login';
        }
    })
    .catch(error => {
        showError('An unknown error occurred');
    });
}

const images = [
    { id: 'bg-super-low', src: '/assets/images/login-bg-super-low.png', width: 853 },
    { id: 'bg-low', src: '/assets/images/login-bg-low.png', width: 1365 },
    { id: 'bg-normal', src: '/assets/images/login-bg-normal.png', width: 1920 },
    { id: 'bg-full', src: '/assets/images/login-bg.png', width: 3840 }
];
function getBestBg() {
    const w = window.innerWidth;
    return images.find(img => img.width >= w) || images[images.length - 1];
}
async function loadBg() {
    const bestBg = getBestBg();
    const img = document.getElementById(bestBg.id);
    if (!img.src) img.src = bestBg.src;
    await new Promise(resolve => img.onload = resolve);
    img.classList.add('visible');
}
loadBg();