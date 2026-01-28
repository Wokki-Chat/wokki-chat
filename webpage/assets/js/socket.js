console.log(
	"%cSTOP\n\n%cDo NOT paste anything into this console.\nIf someone told you to paste something here,\nit can steal your credentials.",
	"color: #b22222; font-size: 60px; font-weight: 800;",
	"font-size: 18px; font-weight: 500;"
);

const socket = io("https://chat.wokki20.nl", {
    path: "/socket.io",
    transports: ["websocket"],
    query: {
        access_token: document.getElementById("access-token").getAttribute("value")
    },
});

let serverName = "Unknown Server";

socket.on("connected to server", (data) => {
    serverName = data.server_name;
});
