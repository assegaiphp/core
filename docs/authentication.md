# Authentication

Start here if your app needs login, sessions, JWTs, or provider-backed sign-in.

In practice, most apps need one of three approaches:

- a session for browser-based login
- a JWT for API clients
- an OAuth login flow such as "Sign in with GitHub"

Assegai keeps those concerns in a separate package:

```bash
composer require assegaiphp/auth
```

The auth package is intentionally small. It helps you establish auth state, but it does not create a user database, register routes for you, or decide what your login screens should look like.

## What the package gives you

Today, `assegaiphp/auth` ships with:

- `SessionAuthStrategy`
- `JwtAuthStrategy`
- `OAuth2AuthStrategy`
- `GitHubOAuthProvider`

## Session authentication

Use a session when your app serves browser pages and you want the user to stay logged in between requests.

```php
<?php

use Assegai\Auth\Strategies\SessionAuthStrategy;

$strategy = new SessionAuthStrategy([
  'user' => $user,
  'session_name' => 'blog_api',
]);
```

If authentication succeeds, the strategy stores a sanitized copy of the user in `$_SESSION`.

## JWT authentication

Use JWT authentication when the client is calling your API directly and you want to return a token.

```php
<?php

use Assegai\Auth\Strategies\JwtAuthStrategy;

$strategy = new JwtAuthStrategy([
  'user' => $user,
  'secret_key' => 'replace-with-a-long-random-secret-key',
  'issuer' => 'blog-api',
  'audience' => 'blog-api-clients',
  'token_lifetime' => '+1 hour',
]);
```

## OAuth login

OAuth is a redirect-based login flow. Your app sends the user to a provider, and the provider sends the user back after login.

```php
<?php

use Assegai\Auth\OAuth\OAuth2AuthStrategy;
use Assegai\Auth\OAuth\Providers\GitHubOAuthProvider;
use Assegai\Auth\OAuth\State\SessionOAuthStateStore;
use Assegai\Auth\Strategies\SessionAuthStrategy;

$oauth = new OAuth2AuthStrategy(
  provider: new GitHubOAuthProvider(),
  config: GitHubOAuthProvider::defaultConfig(
    clientId: 'your-github-client-id',
    clientSecret: 'your-github-client-secret',
    redirectUri: 'https://example.com/auth/github/callback',
  ),
  stateStore: new SessionOAuthStateStore(),
  sessionStrategy: new SessionAuthStrategy(['user' => (object) []]),
);
```

## What your app still owns

The auth package gives you strategies, not the whole application workflow.

You still decide:

- where users are loaded from
- what a login endpoint or page looks like
- whether you want sessions, JWTs, or both
- how to map an OAuth profile onto a local user record
- where to redirect the user after login

## Where to go next

For the deeper auth details, continue with [Authentication and OAuth In Depth](./authentication-and-oauth.md).
