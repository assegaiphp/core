# Frontend with Web Components

This page helps you decide where front-end code should live in an Assegai project.

If your question is "Where do I put my JavaScript without turning the app into a pile of browser scripts?", start here.

The core idea is simple:

- start with server-rendered HTML
- add HTMX when you want server-driven interaction
- add Web Components when a piece of UI needs browser-side behavior
- keep global one-off browser code in `public/js/main.js`
- keep first-party Assegai Web Components in generated `.wc.ts` files under `src/`

By the end of this guide, you should know where front-end code lives, how to generate new components, and how to run the supported build/watch flow.


## Server-rendered pages often use Twig

If you are new to Assegai's frontend story, it helps to separate two different jobs:

- Twig renders HTML on the server
- Web Components add browser behavior where you need it

So when you see a file like `AboutComponent.twig`, think "server-rendered HTML template," not "browser component code."

That means a page can use Twig for the HTML structure and still hydrate a Web Component later for interactive behavior.

## The default path for a new app

For a new project, the happy path is:

1. render HTML from a `View` or a component-backed page
2. generate a Web Component when a specific UI element needs client-side behavior
3. run `assegai serve --dev` while you work
4. let Assegai inject the bundle automatically once it exists

That keeps the server in charge of the page while giving you a clean place for interactive browser code.

## Where code belongs

### `src/Views/*.php`

Use classic views when:

- the page is mostly HTML plus server data
- you do not need a feature-specific page component yet
- you want the fastest possible path from controller to HTML

### `src/<Feature>/...Component.php` and `.twig`

Use component-backed pages when:

- the page belongs to a feature module
- the template, service, controller, and module should stay together
- the page still wants to be server-rendered first


The `.twig` file here is a normal Twig template. It is the HTML side of the page.

### `public/js/main.js`

Keep `main.js` for:

- tiny page-level DOM helpers
- third-party scripts that are not part of the Assegai Web Components pipeline
- truly global browser behavior that does not need to become a reusable custom element

Do not treat `main.js` as the main home for new first-party Assegai Web Components. Those belong in `.wc.ts` source files so the CLI can discover, bundle, and watch them.

### `src/**/*.wc.ts`

Use `.wc.ts` files for:

- new custom elements generated with `assegai g wc ...`
- browser-side runtime files paired with generated pages or components via `--wc`
- reusable UI elements that should be bundled into the Assegai Web Components runtime

## Create your first Web Component

Generate a standalone Web Component:

```bash
assegai g wc ui/alert
```

Or pair a page or component with a runtime file:

```bash
assegai g component user-card --wc
assegai g pg about --wc
```

That gives you a server-rendered feature plus a browser-side custom element file that participates in the first-party build flow.


## Twig basics in Assegai

The most common Twig syntax you need is:

- `{{ title }}` to print a value
- `{% if subtitle %}` for conditions
- `{% for item in items %}` for loops
- `{# comment #}` for template comments

Example:

```twig
<section class="hero">
  <h1>{{ title }}</h1>

  {% if subtitle %}
    <p>{{ subtitle }}</p>
  {% endif %}

  <ul>
    {% for feature in features %}
      <li>{{ feature }}</li>
    {% endfor %}
  </ul>
</section>
```

If you already know Twig from somewhere else, that same template language knowledge still applies here.

## What Assegai passes into a Twig template

Component-backed Twig templates receive:

- the component's public properties as normal variables
- a `ctx` helper object for methods and framework helpers

So if your component has:

```php
public string $title = 'Dashboard';

public function greeting(): string
{
  return 'Welcome back.';
}
```

you can use:

```twig
<h1>{{ title }}</h1>
<p>{{ ctx.greeting() }}</p>
```

That is the rule to remember:

- public properties become plain Twig variables
- public methods are called through `ctx`

Built-in `ctx` helpers include:

- `ctx.config(...)`
- `ctx.translate(...)`
- `ctx.timeAgo(...)`
- `ctx.env(...)`
- `ctx.getLang()`
- `ctx.wcProps(...)`
- `ctx.webComponentBundleUrl()`

## A generated component is expected to render through the shadow root

Generated Web Components use the Assegai runtime and attach a shadow root automatically. A typical render method looks like this:

```ts
protected render(): void {
  const name: string = this.getAttribute('name') || 'user-card';
  this.shadow.innerHTML = `
    <style></style>
    <p>${name} works!</p>`;
}
```

That means:

- render into `this.shadow`
- keep component styles inside the shadow tree when you want local encapsulation
- think of the generated runtime as the normal Assegai Web Components shape for new components

If a component is present in the HTML but its client-side behavior never appears, the most common issue is not the shadow DOM itself. It is usually that the bundle was never built, never watched, or never injected.

## Run the front-end workflow

The first-party commands are:

```bash
assegai wc:list
assegai wc:build
assegai wc:watch
```

`wc:list` answers a very useful question immediately: did the CLI actually discover the Web Components you think it should be bundling?

For development, the easiest loop is:

```bash
assegai serve --dev
```

That starts the PHP dev server and the Web Components watcher together.

If you prefer two separate terminals, this is still valid:

```bash
assegai serve
assegai wc:watch
```

For most new projects, `serve --dev` is the easiest starting point.

## How props move from PHP into a Web Component

From Twig:

```twig
<app-user-card data-props='{{ ctx.wcProps({
  name: user.name,
  role: user.role
}) }}'></app-user-card>
```

From a PHP view:

```php
<app-user-card
  data-props='<?= wc_props([
    "name" => $user["name"],
    "role" => $user["role"],
  ]) ?>'
></app-user-card>
```

`data-props` is not a special Assegai-only format. It is just JSON stored in an HTML attribute.

The helper exists because JSON inside HTML needs to be encoded and escaped safely. Quotes, apostrophes, and other characters can break the markup if you try to hand-build the string. `wc_props(...)` and `ctx.wcProps(...)` do that safely and give PHP views and Twig templates one consistent way to pass data.

The runtime reads that `data-props` payload and hydrates the custom element in the browser.


## Do not assume every Twig helper from other frameworks exists

Assegai uses Twig, but it does not automatically ship every helper you may have seen in Symfony or other stacks.

Do not assume these are available unless you add them yourself:

- a global `app` object
- `path()` or `url()` routing helpers
- `asset()` helpers
- `form_*()` helpers
- `csrf_token()`
- third-party Twig extensions you have not installed

So the safe mental model is:

- standard Twig language features work
- Assegai-specific helpers are available through `ctx`
- anything beyond that should be treated as opt-in

## Workspace bundle defaults live in `assegai.json`

The basic shape is:

```json
{
  "webComponents": {
    "enabled": true,
    "prefix": "app",
    "output": "public/js/assegai-components.min.js"
  }
}
```

Important keys:

- `enabled` controls whether automatic bundle injection is active
- `prefix` controls generated selectors such as `app-user-card`
- `output` controls where the browser bundle is written

If a bundle exists at the configured output path, Assegai injects it automatically into rendered HTML.

## Application runtime overrides

Deployment policy belongs in application configuration, not in the tracked workspace build defaults. The application `webComponents` section overrides runtime fields from `assegai.json`. For example, an application that supplies verified production scripts can disable both automatic bundle emission and development polling in `config/production.php`:

```php
<?php

return [
  'webComponents' => [
    'enabled' => false,
    'hotReload' => ['enabled' => false],
  ],
];
```

These switches are independent. `enabled: false` suppresses the automatic module tag, not polling. `hotReload.enabled: false` suppresses polling even when an active watcher marker contains a bundle URL. Neither switch removes scripts explicitly configured through document properties or templates.

The supported application override fields are:

- `enabled`: boolean, controls automatic bundle emission.
- `bundleUrl`: non-empty string, overrides the workspace bundle URL or output path. A missing local file emits no automatic tag; it does not fall back to the default bundle.
- `hotReload.enabled`: boolean, controls polling.
- `hotReload.path`: non-empty string, changes the public watcher marker path.
- `hotReload.pollInterval`: positive integer in milliseconds. The marker's interval still takes precedence, with a minimum effective interval of 250 ms.

Omitted fields inherit workspace defaults. Invalid application override fields or types raise `InvalidArgumentException`; use a boolean `false`, not the string `'false'`. Build-only settings such as `prefix` and `output` remain in `assegai.json`.

Both rendering engines and the `web_component_*` helpers share one project-bound configuration snapshot per App request. Workspace configuration and watcher state resolve lazily on first use, once per request. No override is written back to disk. With no application override, development bundling and hot reload continue to use the workspace defaults.

### Share selection and rendering configuration

After the application factory returns, request code can read the same application configuration used for document scripts and Web Component policy:

```php
use Assegai\Core\Config\AppConfig;
use Assegai\Core\WebComponents\WebComponentSupport;

$config = AppConfig::current();
$scripts = $config->get('app.bodyScriptUrls');
$runtime = WebComponentSupport::getConfiguration();

$automaticEmission = $runtime->isBundleEnabled();
$polling = $runtime->isHotReloadEnabled();
```

`AppConfig::current()` does not execute configuration fragments again or consult legacy `Config` globals. It returns a detached configuration view and throws `LogicException` outside an active App scope. Treat object-valued configuration as immutable; cloning the view does not deep-clone those objects. The Web Component configuration exposes the effective flags, resolved bundle URL, watcher state, and a readonly `workingDirectory`.

Configuration fragments still merge in the established order: `default.php`, `auth.php`, `production.php` in production, `local.php`, then `secure.php`. They retain recursive merge semantics. An application that owns a private release selection should read it once during configuration, preserve it as an immutable value, derive its script definitions from that value, and validate the final merged settings against it. Use `AppConfig::current()` in request readers so a second legacy configuration load cannot select different bytes.

For integrity-pinned assets, supply the verified `src`, `type`, `integrity`, and `crossorigin` through `app.bodyScriptUrls`. The existing document renderer emits these attributes. Keep automatic emission and polling disabled even when production selection is missing or invalid; the application must refuse execution instead of falling back to an unsigned bundle. Core does not verify release receipts or asset hashes.

Application configuration is loaded at App construction. Restart long-lived workers after changing deployment configuration or private selection. The next ordinary PHP request constructs a fresh App. Request cleanup discards the rendering snapshot, including after errors. Explicit helper root arguments inspect that project; they do not inherit a different project's application override. Outside App scope, helpers use live workspace lookup without a process-wide cache.

## Global favicon and scripts are configured once in `config/default.php`

You do not need to add a favicon or site-wide scripts per page anymore.

```php
<?php

return [
  'app' => [
    'title' => 'Blog API',
    'favicon' => ['/favicon.ico', 'image/x-icon'],
    'links' => ['/css/style.css'],
    'headScriptUrls' => ['/js/main.js'],
    'bodyScriptUrls' => ['/js/analytics.js'],
  ],
];
```

That configuration applies to both classic `View` rendering and component-backed rendered pages.

Inside an App request, document defaults come from its application snapshot, not ambient `Config::set('app', ...)` or `$_ENV['app']` changes. Use application fragments or explicit per-render properties. A direct `new View(...)` with omitted properties now uses the same configured defaults as the `view(...)` helper; explicitly supplied `ViewProperties` remain authoritative.

## FAQ

### Why are my new Web Components not rendering?

Check these in order:

1. Is the custom element tag actually present in the rendered HTML?
2. Does `assegai wc:list` show the component?
3. Did you run `assegai wc:watch`, `assegai wc:build`, or `assegai serve --dev`?
4. Does the rendered page include the Web Components bundle?
5. Are you rendering into `this.shadow` in the generated runtime style?

### What if my project is older and everything lives in `public/js/main.js`?

Keep the old browser code working first, then migrate gradually:

1. Leave existing global scripts in `public/js/main.js`.
2. Generate new custom elements with `assegai g wc ...` or pair feature generation with `--wc`.
3. Put those new custom-element definitions in `.wc.ts` files instead of appending them to `main.js`.
4. Run `assegai wc:list` and confirm the new components are discovered.
5. Run `assegai wc:watch` or `assegai serve --dev`.

This lets you move onto the first-party runtime without rewriting every older browser script at once.

## A good default strategy

Keep the server in charge of HTML.

Use `main.js` sparingly for truly global behavior. Use first-party `.wc.ts` files for new custom elements. Let `serve --dev` or `wc:watch` keep the bundle current while you work.

That keeps the application modular, predictable, and much easier to explain to the next person who joins the project.


## Where to go deeper

Use [Pages and Components](./pages-and-components.md) for the Assegai-specific rendering model and when to choose `View`, Twig-backed page components, HTMX, or Web Components.

Use the official Twig docs for the template language itself:

- [Twig template basics](https://twig.symfony.com/doc/3.x/templates.html)
- [Twig tags reference](https://twig.symfony.com/doc/3.x/tags/index.html)
- [Twig filters reference](https://twig.symfony.com/doc/3.x/filters/index.html)
- [Twig functions reference](https://twig.symfony.com/doc/3.x/functions/index.html)
