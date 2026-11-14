# AssegaiPHP Core runtime paths

Draft release notes. A release version has not been assigned.

Applications can select filesystem locations at bootstrap, keeping generated
application files separate from external configuration, secrets and persistent
writable state.

## What changed

- Adds an immutable `ApplicationPaths` value accepted by `AssegaiFactory::create()`,
  `createFromProject()` and `createWithRuntime()` through the optional `paths`
  argument. The value is injectable and available through `App::getPaths()`.
- Selects the application root, configuration directory, secrets directory,
  writable state directory, Composer metadata directory and environment file
  independently. Explicit directories must already exist and use absolute paths.
- Reads `local.php` and `secure.php` from the selected secrets directory. Other
  configuration fragments use the configuration directory. Setting
  `environmentFile` to `null` disables dotenv loading; a selected missing file
  fails during bootstrap without falling back to an application `.env`.
- Resolves PHP views, Twig templates, adjacent resources and public assets from
  the selected application. Static asset handling uses the active request URI,
  including requests supplied by alternate HTTP runtimes.
- Uses an explicitly selected state directory for default logs, uploads and
  PHP file sessions. Restores the previous session save path after a request;
  custom session handlers remain application-owned.
- Adds state, log, cache, session and upload directory helpers to `Paths`.
- Disables configuration persistence and implicit workspace-configuration writes
  when the selected contract has `readOnly: true`, which is its default.

## Compatibility and deployment

PHP 8.4 or newer remains required. Dependency versions do not change. Existing
factory calls retain their defaults and configuration persistence behavior.

Opt into the contract by passing `paths: new ApplicationPaths(...)` at bootstrap.
Provision external directories before startup and keep secrets and writable
state outside the public document root and replaceable application directory.
The `readOnly` option guards Core configuration writes; filesystem permissions
must enforce the deployment's access policy.

The application root contains generated `src/`, adjacent resources, `public/`
and `assegai.json`. The Composer directory selects `composer.json` metadata;
the bootstrap remains responsible for loading an autoloader that maps application
classes to the selected generation.

## Lifecycle limits

The cache-directory helper selects a path; it does not create or warm a cache.
`App::boot()` prepares application and middleware graphs in memory and invokes
application initialization hooks. It does not produce a persistent discovery
cache or a deployment-readiness signal.

Keep long-lived workers bound to one immutable generation. Restart them after
changing generated code, configuration or secrets. This change does not provide
worker draining, generation switching, rollback orchestration or automatic
configuration reload. Environment values retain their process-wide scope.
