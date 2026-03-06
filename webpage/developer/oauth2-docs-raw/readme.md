# OAuth 2.0 API documentation

Welcome to the documentation of the OAuth 2.0 API for [Wokki Chat](https://chat.wokki20.nl/)!

You can use this API to:

- **Authenticate users** - Allow users to log in to your application using their Wokki Chat account
- **Access user information** - Retrieve profile details including username, email, profile picture, and bio
- **View user connections** - Get lists of the user's friends and connections
- **View server memberships** - See which servers the authenticated user is a member of
- **Modify user profiles** - Update user information like display name, bio, and profile settings (with proper permissions)

## Limitations

This OAuth API is designed for user authentication and profile management. For more advanced functionality, you'll need to use regular bots:

- **Messaging** - Cannot read or send messages through this API
- **Server management** - Cannot create or modify servers
- **Server administration** - Cannot manage server settings, channels, or roles

If your application needs to interact with messages or manage servers, please refer to the Bot API documentation instead.

## Endpoints
We currently support the following endpoints:
  - [Authorization](docs/authorization.md)
  - [Token](docs/token.md)