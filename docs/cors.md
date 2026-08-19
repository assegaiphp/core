# Cross-Origin Resource Sharing (CORS)

Assegai can own CORS at the application boundary. This keeps preflight handling and
actual-response headers under the same policy across the PHP and OpenSwoole runtimes.

CORS is disabled until you call `enableCors()`.

## Enable the defaults

Call `enableCors()` before `run()` in `bootstrap.php`:

```php
function bootstrap(): void
{
  $app = AssegaiFactory::createFromProject(AppModule::class, __DIR__);
  $app->enableCors();
  $app->run();
}
```

The default policy allows any origin without credentials and allows these methods:

```text
GET, HEAD, PUT, PATCH, POST, DELETE
```

When `allowedHeaders` is not configured, Assegai reflects the browser's
`Access-Control-Request-Headers` value on a valid preflight and adds the corresponding
`Vary` header.

## Configure a credentialed console

A browser client that sends cookies or HTTP authentication needs an explicit origin.
The wildcard origin cannot be combined with credentials.

```php
$app->enableCors([
  'origin' => [
    'http://localhost:5173',
    'https://console.example.com',
  ],
  'credentials' => true,
  'methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'],
  'allowedHeaders' => [
    'Content-Type',
    'Authorization',
    'X-CSRF-Token',
    'X-API-Key',
  ],
  'exposedHeaders' => ['Location'],
  'maxAge' => 600,
]);
```

For an allowed request, the framework reflects the matching request origin, emits
`Access-Control-Allow-Credentials: true`, and adds `Vary: Origin`. A request from an
origin outside the allowlist receives no `Access-Control-Allow-Origin` header.

The browser request must opt into credentials too:

```js
await fetch('https://api.example.com/orders/42', {
  method: 'PATCH',
  credentials: 'include',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken,
  },
  body: JSON.stringify({ status: 'ready' }),
});
```

Cookie attributes and CSRF protection remain separate concerns. Cross-site cookies
normally need suitable `SameSite` and `Secure` attributes, and enabling CORS does not
make a credentialed state-changing endpoint safe from CSRF by itself.

## Options

| Option | Type | Default | Behavior |
| --- | --- | --- | --- |
| `origin` | `string`, `string[]`, `bool`, or callable | `'*'` | Sets the allowed origin policy. `false` disables CORS; `true` reflects any request origin. |
| `methods` | `string` or list | `GET, HEAD, PUT, PATCH, POST, DELETE` | Methods advertised by a preflight response. Enum values from `RequestMethod` are also accepted in a list. |
| `allowedHeaders` | `string`, list, or `null` | `null` | Headers advertised by preflight. `null` safely reflects requested header names. |
| `exposedHeaders` | `string` or list | `[]` | Non-safelisted response headers browser JavaScript may read. |
| `credentials` | `bool` | `false` | Emits `Access-Control-Allow-Credentials: true`. Requires an explicit origin or origin callback. |
| `maxAge` | non-negative `int` or `null` | `null` | Seconds a browser may cache a successful preflight. |
| `preflightContinue` | `bool` | `false` | Sends preflight through normal routing instead of completing it in the CORS layer. |
| `optionsSuccessStatus` | `int` | `204` | Successful status used for a framework-completed preflight. |

Unknown option names and unsafe header values are rejected during bootstrap. Assegai
also rejects `credentials: true` with `origin: '*'` or `origin: true`, because browsers
do not permit a wildcard allow-origin value on credentialed requests.

You can pass a `CorsOptions` object instead of an array when typed configuration is
more convenient:

```php
use Assegai\Core\Http\Cors\CorsOptions;

$app->enableCors(new CorsOptions(
  origin: ['https://console.example.com'],
  credentials: true,
  maxAge: 600,
));
```

## Compute origins dynamically

An `origin` callback receives the incoming origin and active request. Return `true` to
allow and reflect that origin, `false` to deny it, or return an explicit origin policy:

```php
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;

$app->enableCors([
  'origin' => static function (string $origin, RequestInterface $request): bool {
    return str_ends_with($origin, '.internal.example.com');
  },
  'credentials' => true,
]);
```

For request-level policies, pass a callback directly to `enableCors()`. It may return
an options array, `CorsOptions`, `true` for the defaults, or `false`/`null` to disable
CORS for that request:

```php
$app->enableCors(static function (RequestInterface $request): array|false {
  if (!str_starts_with($request->getPath(), '/control-plane/')) {
    return false;
  }

  return [
    'origin' => ['https://console.example.com'],
    'credentials' => true,
    'maxAge' => 600,
  ];
});
```

## Preflight behavior

Assegai recognizes a preflight only when all three conditions are present:

- the request method is `OPTIONS`
- the request includes `Origin`
- the request includes `Access-Control-Request-Method`

By default, a recognized preflight is answered before sessions, module graph
preparation, routing, controllers, and middleware. An ordinary application `OPTIONS`
request continues through normal routing.

Like NestJS's application-level CORS support, the advertised methods are policy-wide;
a successful preflight is not proof that a specific route exists. The important
difference from an entry-point shim is that every actual framework response, including
404, 405, and 500 responses, is decorated by the same CORS policy. The browser can
therefore expose the real HTTP result instead of masking it as a generic CORS failure.

Use `preflightContinue: true` only when your application deliberately owns preflight
routing and will produce the response itself.

## Migrate an existing scaffold

Remove unconditional CORS headers from both the project-root `index.php` and web-server
configuration. Also remove a top-level block like this:

```php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}
```

Then configure one policy with `$app->enableCors(...)` in `bootstrap.php` before
`$app->run()`.

Do not leave Apache or proxy rules that overwrite `Access-Control-Allow-Origin`. A
reverse proxy may handle CORS instead of Assegai, but there should be one authoritative
layer rather than two competing policies.
