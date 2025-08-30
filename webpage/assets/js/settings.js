window.addEventListener("load", () => {



  if (active_tab === "account") {
    const editProfileButton = document.getElementById("edit-profile-button");
    const profilePicturePreview = document.querySelector(".profile-picture-preview");

    let profilePictureChanged = false;
    let newProfilePictureFile = null;
    let isEditing = false;

    editProfileButton.addEventListener("click", () => {
      if (!isEditing) {
        isEditing = true;
        editProfileButton.textContent = "Save";

        if (profilePicturePreview) {
          let wrapper = profilePicturePreview.parentElement;
          wrapper.classList.add("editing");

          const fileInput = document.createElement("input");
          fileInput.type = "file";
          fileInput.accept = premium ? "image/png,image/jpeg,image/jpg,image/webp,image/gif" : "image/png,image/jpeg,image/jpg,image/webp";
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

            const allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp"];
            if (premium) allowedTypes.push("image/gif");

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
        }

      } else {
        if (profilePictureChanged && newProfilePictureFile) {
          const formData = new FormData();
          formData.append("profile_picture", newProfilePictureFile);
          fetch("/app/update_profile", {
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
                text: "Profile updated successfully!",
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
              profilePictureChanged = false;
              newProfilePictureData = null;
            } else {
              throw new Error(data.message || "Update failed");
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
        }

        isEditing = false;
        editProfileButton.textContent = "Edit Profile";
        profilePicturePreview.parentElement.classList.remove("editing");
      }
    });
  }

  
});

function setTheme(theme) {
  document.cookie = `theme=${theme}; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/; SameSite=None; Secure;`;
  window.location.reload();
}
