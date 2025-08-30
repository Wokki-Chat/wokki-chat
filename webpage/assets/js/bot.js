const profilePicturePreview = document.querySelector(".profile-picture-preview");

let profilePictureChanged = false;
let newProfilePictureFile = null;

let wrapper = profilePicturePreview.parentElement;

const fileInput = document.createElement("input");
fileInput.type = "file";
fileInput.accept = "image/png,image/jpeg,image/jpg,image/webp,image/gif";
fileInput.style.display = "none";
document.body.appendChild(fileInput);

profilePicturePreview.addEventListener("click", () => {
fileInput.click();
});

fileInput.addEventListener("change", () => {
const file = fileInput.files[0];
if (!file) {
    profilePicturePreview.src = profile_picture;
    return;
}

const allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp", "image/gif"];

if (!allowedTypes.includes(file.type)) {
    Toastify({
    text: "File type not allowed. Please upload a valid image file.",
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

const reader = new FileReader();
reader.onload = e => {
    profilePicturePreview.src = e.target.result;
};
reader.readAsDataURL(file);

newProfilePictureFile = file;
profilePictureChanged = true;
});
    

const showTokenBtn = document.getElementById("show-btn");
const copyTokenBtn = document.getElementById("copy-btn");
const copyInviteLinkBtn = document.getElementById("copy-invite-btn");

let tokenShowing = false;
showTokenBtn.addEventListener("click", () => {
    if (tokenShowing) {
        document.getElementById("bot-token").type = "password";
        showTokenBtn.innerText = "Show Token";
        tokenShowing = false;
        return;
    } else if (!tokenShowing) {
        document.getElementById("bot-token").type = "text";
        showTokenBtn.innerText = "Hide Token";
        tokenShowing = true;
        return;
    }
});


copyTokenBtn.addEventListener("click", () => {
    const token = document.getElementById("bot-token").value;
    navigator.clipboard.writeText(token).then(() => {
        Toastify({
        text: "Token copied to clipboard!",
        className: "copy-code-success",
        duration: 3000,
        close: true,
        gravity: "bottom",
        position: "right",
        style: {
            background: "var(--clr-popup-a10)",
            boxShadow: "none",
            borderRadius: "12px"
        }
        }).showToast();
    });
});

copyInviteLinkBtn.addEventListener("click", () => {
    const inviteLink = document.getElementById("bot-invite").value;
    navigator.clipboard.writeText(inviteLink).then(() => {
        Toastify({
        text: "Invite link copied to clipboard!",
        className: "copy-code-success",
        duration: 3000,
        close: true,
        gravity: "bottom",
        position: "right",
        style: {
            background: "var(--clr-popup-a10)",
            boxShadow: "none",
            borderRadius: "12px"
        }
        }).showToast();
    });
})

const saveChangesBtn = document.getElementById("save-bot-button");

saveChangesBtn.addEventListener("click", () => {
    const botName = document.getElementById("name").value;
    const botBio = document.getElementById("bio").value;

    if (botName === "") {
        Toastify({
            text: "Please enter a bot name.",
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

    if (botName === originalBotName && !profilePictureChanged && botBio === originalBotBio) {
        return;
    }

    const formData = new FormData();
    if (profilePictureChanged) {
        formData.append("profile_picture", newProfilePictureFile);
    }
    if (botName !== originalBotName) {
        formData.append("bot_name", botName);
    }
    if (botBio !== originalBotBio) {
        formData.append("bot_bio", botBio);
    }
    formData.append("bot_id", bot_id);

    fetch("/app/update_bot", {
        method: "POST",
        headers: {
            "Authorization": `Bearer ${access_token}`
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            Toastify({
                text: "Bot updated successfully!",
                className: "copy-code-success",
                duration: 3000,
                close: true,
                gravity: "bottom",
                position: "right",
                style: {
                    background: "var(--clr-popup-a10)",
                    boxShadow: "none",
                    borderRadius: "12px"
                }
            }).showToast();
            window.location.reload();
        }
    })
    .catch(err => {
        Toastify({
            text: "Something went wrong!",
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
