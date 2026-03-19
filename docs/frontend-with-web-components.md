# Frontend with Web Components

This guide is for application developers who are deciding where front-end code should live in an Assegai project.

If your question is "Where do I put my JavaScript without turning the app into a pile of browser scripts?", start here.

The core idea is simple:

- start with server-rendered HTML
- add HTMX when you want server-driven interaction
- add Web Components when a piece of UI needs browser-side behavior
- keep global one-off browser code in `public/js/main.js`
- keep first-party Assegai Web Components in generated `.wc.ts` files under `src/`

By the end of this guide, you should know where front-end code lives, how to generate new components, and how to run the supported build/watch flow.

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
<app-user-card data-props='{{ ctx.webComponentProps({
  name: user.name,
  role: user.role
}) }}'></app-user-card>
```

From a PHP view:

```php
<app-user-card
  data-props='<?= web_component_props([
    "name" => $user["name"],
    "role" => $user["role"],
  ]) ?>'
></app-user-card>
```

`data-props` is not a special Assegai-only format. It is just JSON stored in an HTML attribute.

The helper exists because JSON inside HTML needs to be encoded and escaped safely. Quotes, apostrophes, and other characters can break the markup if you try to hand-build the string. `web_component_props(...)` and `ctx.webComponentProps(...)` do that safely and give PHP views and Twig templates one consistent way to pass data.

The runtime reads that `data-props` payload and hydrates the custom element in the browser.

## Bundle configuration lives in `assegai.json`

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
