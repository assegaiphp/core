# Serving with OpenSwoole

OpenSwoole gives Assegai a long-lived HTTP runtime instead of the normal request-per-process development server.

Use it when you want to explore:

- long-lived workers
- coroutine-friendly I/O
- the runtime direction Assegai is building toward

The default `php` runtime is still the easiest place to start. OpenSwoole is the path to try when you specifically want the alternate runtime.

## Install the extension first

The OpenSwoole serve path depends on the PHP extension being installed and enabled for the same PHP binary that runs `assegai`.

Typical Linux flow:

```bash
pecl install openswoole
```

Then enable the extension in your CLI and web PHP config if needed.

Check that the active PHP binary can see it:

```bash
php -m | grep openswoole
```

If that command returns nothing, `assegai serve --runtime=openswoole` will stop early and tell you the extension is missing.

## Start a project with OpenSwoole

From a project root:

```bash
assegai serve --runtime=openswoole
```

That uses your normal `bootstrap.php`, but the app runs through the OpenSwoole HTTP runtime instead of `php -S`.

You can still choose host and port:

```bash
assegai serve --runtime=openswoole --host 127.0.0.1 --port 9510
```

## Store the runtime in `assegai.json`

If a project should usually boot with OpenSwoole, keep that in config:

```json
{
  "development": {
    "server": {
      "runtime": "openswoole",
      "host": "127.0.0.1",
      "port": 9510,
      "openBrowser": false,
      "openswoole": {
        "workerNum": 1,
        "taskWorkerNum": 0,
        "maxRequest": 0,
        "enableCoroutine": true,
        "hookFlags": "all"
      }
    }
  }
}
```

Then a normal serve command is enough:

```bash
assegai serve
```

## What the settings mean

- `workerNum` controls how many HTTP workers the server starts
- `taskWorkerNum` reserves task workers for short internal offloading work
- `maxRequest` lets a worker restart after a fixed number of requests
- `enableCoroutine` turns coroutine support on or off
- `hookFlags` controls which coroutine hooks OpenSwoole enables

For a first pass, the defaults are fine.

## Current limits

Use this runtime with the expectation that it is still experimental.

The pieces that are in place now:

- app graph boot happens once per worker
- request and response state is refreshed per request
- escaped handler failures go back through the framework error pipeline
- lifecycle hooks run on worker boot and shutdown

The pieces that still need more work:

- broader end-to-end runtime coverage beyond the focused integration tests
- more runtime-specific tooling around worker management
- fuller async guidance across the ecosystem

Two practical limits matter today:

- `--https` is not supported on the OpenSwoole serve path yet
- the runtime is designed to be safe first, not marketed as fully production-ready yet

## When to stay on the default runtime

Stay on the normal `php` runtime if you are:

- learning Assegai for the first time
- building a conventional API or server-rendered app
- debugging ordinary controller, DTO, ORM, or rendering work

Switch to OpenSwoole when you want to test the long-lived runtime model directly.

## Read next

If you want the runtime details and current architecture boundary, continue with [OpenSwoole Runtime](./openswoole-runtime.md).
