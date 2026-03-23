# Getting Started

This guide assumes you are new to Assegai.

If you can install a Composer package and run a PHP command, you can follow along. The goal is to get you to a running project first, then explain the pieces as they appear.

The recommended way to start an Assegai project is with the CLI, not by wiring files together manually.

## Start with the CLI

If the CLI is installed, the entry point is simply:

```bash
assegai
```

That gives you the commands you will use most often:

- `assegai new`
- `assegai serve`
- `assegai generate`
- `assegai schematic:init`
- `assegai schematic:list`
- `assegai test`
- `assegai database:*`
- `assegai migration:*`

If you later want `assegai generate` to understand your own company-specific feature scaffolds, start with [Custom CLI Schematics](./custom-cli-schematics.md). If you want the full manifest and token reference after that, continue with [Custom CLI Schematics In Depth](./custom-cli-schematics-in-depth.md).

## Create a new project

Create a new app:

```bash
assegai new blog-api
```

The scaffold flow currently prompts for:

- project description
- version
- package name
- PHP namespace
- whether to initialize git
- whether to configure a database

If you opt into database setup during scaffolding, the CLI also:

- writes database settings into `config/default.php`
- generates a default users resource when one does not already exist
- updates `src/AppModule.php` to import that resource module
- attempts to install `assegaiphp/orm`

## What a fresh project looks like

A real scaffolded app looks roughly like this:

```text
blog-api/
├── apache.conf.example
├── assegai.json
├── bootstrap.php
├── composer.json
├── config/
│   └── default.php
├── index.php
├── public/
│   ├── css/style.css
│   ├── images/logo.png
│   ├── js/main.js
│   ├── favicon.ico
│   └── robots.txt
├── src/
│   ├── AppController.php
│   ├── AppModule.php
│   ├── AppService.php
│   └── Views/index.php
└── README.md
```

Two details are worth calling out immediately:

- the HTTP router entry point is the project-root `index.php`
- the starter home page is rendered from `src/Views/index.php`

One front-end detail matters early too:

- `public/js/main.js` is the default global browser script, not the long-term home for new first-party Assegai Web Components

## How the scaffold boots

The root `index.php` sets CORS headers, normalizes the request path, and forwards every request into `bootstrap.php`:

```php
<?php

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Origin,X-Requested-With,Content-Type,Accept,X-Access-Token,Authorization,x-api-key");
header("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,PUT,PATCH,POST,DELETE");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

if (!isset($_GET['path']) || $_GET['path'] === '') {
  $_GET['path'] = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
}

require_once __DIR__ . '/bootstrap.php';
```

`bootstrap.php` is intentionally tiny:

```php
<?php

use Assegai\Core\AssegaiFactory;
use Assegaiphp\BlogApi\AppModule;

require __DIR__ . '/vendor/autoload.php';

function bootstrap(): void
{
  $app = AssegaiFactory::create(AppModule::class);
  $app->run();
}

bootstrap();
```

That tells you a lot about Assegai's philosophy:

- one root module
- a framework-owned application runtime
- conventions around how requests enter the app
- user code living in modules, controllers, providers, views, components, DTOs, and entities

## Serve the app

The CLI serves the project through the root `index.php` router:

```bash
assegai serve
```

Once the server is up, you can also open:

- `/docs` for Swagger UI
- `/openapi.json` for the generated OpenAPI document

For that route to be useful, the project needs a current OpenAPI document. The predictable development flow is either:

- run `assegai api:export openapi`
- or enable export-on-serve in `assegai.json`

By default, a scaffolded project stores dev server settings in `assegai.json`:

```json
{
  "development": {
    "server": {
      "host": "localhost",
      "port": 5000,
      "openBrowser": false
    }
  }
}
```

So the default development URL is usually:

```text
http://localhost:5000
```

You can override that when needed:

```bash
assegai serve --host 0.0.0.0 --port 8080
```

## Development errors are readable

In non-production environments, Assegai wires in Whoops-based error handling.

That means the development experience is friendlier by default:

- `GET` requests render a human-friendly HTML error page
- CLI errors fall back to plain text
- non-`GET` HTTP errors fall back to JSON-style error output

This is especially useful while you are building pages and endpoints at the same time, because failures are easier to inspect without adding your own debug scaffolding first.

## Understand the starter home page

The scaffolded home page is not just a placeholder. It shows the standard controller-to-service-to-view flow:

```php
<?php

namespace Assegaiphp\BlogApi;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Rendering\View;

#[Controller(path: '')]
class AppController
{
  public function __construct(protected AppService $appService)
  {
  }

  #[Get]
  public function home(): View
  {
    return $this->appService->home();
  }
}
```

```php
<?php

namespace Assegaiphp\BlogApi;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Rendering\View;

#[Injectable]
class AppService
{
  public function __construct(protected ProjectConfig $config)
  {
  }

  public function home(): View
  {
    $name = $this->config->get('name') ?? 'Your app';

    return view('index', [
      'title' => 'Muli Bwanji',
      'subtitle' => "Congratulations! $name is running.",
      'welcomeLink' => Config::get('contact')['links']['assegai_website'],
      'getStartedLink' => Config::get('contact')['links']['guide_link'],
    ]);
  }
}
```

That is already enough to teach a useful pattern:

- controllers stay thin
- providers own behavior
- rendered output can be a `View`

## Generate your first API feature

From the project root, generate a REST-style resource:

```bash
assegai g r posts
```

When run from the project root, the generator:

- creates `PostsController`, `PostsService`, and `PostsModule`
- creates DTO and entity stubs
- updates `src/AppModule.php`

You end up with a feature folder like this:

```text
src/Posts/
├── DTOs/
│   ├── CreatePostDTO.php
│   └── UpdatePostDTO.php
├── Entities/
│   └── PostEntity.php
├── PostsController.php
├── PostsModule.php
└── PostsService.php
```

And `AppModule` is extended to import the new module:

```php
#[Module(
  providers: [AppService::class],
  controllers: [AppController::class],
  imports: [UsersModule::class, PostsModule::class],
)]
class AppModule
{
}
```

At that point you have a working route prefix at:

```text
http://localhost:5000/posts
```

The generated controller gives you the familiar REST surface:

- `GET /posts`
- `GET /posts/:id`
- `POST /posts`
- `PUT /posts/:id`
- `DELETE /posts/:id`

## Generate your first page

Assegai is not only for JSON APIs. From the same app, generate a page:

```bash
assegai g pg about
```

That creates:

```text
src/About/
├── AboutComponent.css
├── AboutComponent.php
├── AboutComponent.twig
├── AboutController.php
├── AboutModule.php
└── AboutService.php
```

And again, the CLI updates `AppModule` for you.

The route is now available at:

```text
http://localhost:5000/about
```

This is a big part of the Assegai value proposition: the same project can host JSON endpoints and server-rendered pages without switching frameworks or inventing a parallel structure.

If you are building interactive front-end features, the next guide to read is [Frontend with Web Components](./frontend-with-web-components.md). It explains how to keep `main.js`, generated `.wc.ts` files, and the Web Components runtime in the right places.

## Why this workflow matters

The CLI is not just a convenience layer. It encodes the framework's conventions:

- every feature gets a module boundary
- controllers and providers are created together
- page generation uses declarations and components
- resources naturally line up with the ORM story
- the root module stays the place where features are composed

That means even a fast-moving prototype tends to stay organized.

## Next steps

Continue with:

- [Frontend with Web Components](./frontend-with-web-components.md)
- [Architecture and Lifecycle](./architecture-and-lifecycle.md)
- [Controllers and Routing](./controllers-and-routing.md)
- [Pages and Components](./pages-and-components.md)
