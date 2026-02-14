// modules/global/dev.js
// Module description: This module helps with things in the dev environment.

export default class Dev {
    constructor() {}

    init() {
        if (location.hostname !== "localhost") return;
	    document.title = "Dev Env - " + document.title;
    }
}