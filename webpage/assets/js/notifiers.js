window.addEventListener("load", () => {

socket.on("friend_request_received", (request) => {
    userName = request.username;
    Toastify({
    text: "You got a new friend request from " + userName,
    duration: 3000,
    gravity: "bottom",
    position: "right",
    destination: "https://chat.wokki20.nl/friends",
    close: true,
    stopOnFocus: true,
    style: {
        background: "var(--clr-popup-a20)",
        borderRadius: "12px",
        boxShadow: "none"
    }
    }).showToast();
    
});


socket.on('friend_request_accepted', (request) => {
    username = request.username;
    Toastify({
    text: username + " accepted your friend request",
    destination: "https://chat.wokki20.nl/friends",
    duration: 3000,
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
    
});
});