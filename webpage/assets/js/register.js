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

    // === Client-side Validation ===
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

    // === Send request ===
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
