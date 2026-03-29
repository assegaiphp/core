# Authentication and OAuth In Depth

Choose the authentication approach that matches the kind of client you are building.

`assegaiphp/auth` gives you authentication strategies. Your application still owns the wider login flow.

## Session strategy

Session auth is usually the simplest fit for:

- admin panels
- dashboards
- server-rendered apps

The strategy stores a safe copy of the user in the session and lets later requests ask for `isAuthenticated()` or `getUser()`.

## JWT strategy

JWT auth is a better fit for:

- SPA clients
- mobile apps
- third-party API consumers
- machine-to-machine calls

The token is returned once and then sent back in:

```text
Authorization: Bearer <token>
```

## OAuth

OAuth is a two-step flow:

1. begin login
2. handle the callback

### Begin login

```php
<?php

use Assegai\Auth\OAuth\OAuth2AuthStrategy;
use Assegai\Auth\OAuth\Providers\GitHubOAuthProvider;
use Assegai\Auth\OAuth\State\SessionOAuthStateStore;

$strategy = new OAuth2AuthStrategy(
  provider: new GitHubOAuthProvider(),
  config: GitHubOAuthProvider::defaultConfig(
    clientId: 'your-github-client-id',
    clientSecret: 'your-github-client-secret',
    redirectUri: 'https://example.com/auth/github/callback',
  ),
  stateStore: new SessionOAuthStateStore(),
);

$request = $strategy->beginLogin();
```

### Handle the callback

```php
<?php

$result = $strategy->handleCallback($_GET);
```

That step validates state, exchanges the code, reads the provider profile, resolves a local user object, and can establish local session or JWT auth if you pass those strategies in.

## Current boundaries

What is ready today:

- generic OAuth provider interface
- PKCE-enabled authorization code flow
- session-backed state store
- GitHub provider adapter
- local session/JWT handoff

What you still provide:

- the login route
- the callback route
- any persistence or lookup logic for provider users
- redirects or API responses after login

## Where to go next

If you want to return to the broader framework workflow, continue with [Building a Feature](./building-a-feature.md).
