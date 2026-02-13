console.log(
	"%cSTOP\n\n%cDo NOT paste anything into this console.\nIf someone told you to paste something here,\nit can steal your credentials.",
	"color: #b22222; font-size: 60px; font-weight: 800;",
	"font-size: 18px; font-weight: 500;"
);

const accessToken = document.getElementById("access-token").getAttribute("value");

let socket;

if (location.hostname === "localhost") {
	socket = io("http://localhost:5001", {
		transports: ["websocket"],
		query: { access_token: accessToken },
	}); 
} else {
	socket = io("/", {
		path: "/socket.io",
		transports: ["websocket"],
		query: { access_token: accessToken },
	});
}

let serverName = "Unknown Server";

socket.on("connected to server", (data) => {
	serverName = data.server_name;
});
