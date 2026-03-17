<?php

namespace Assegai\Core\ApiDocs;

class SwaggerUiRenderer
{
  public function render(string $specUrl = '/openapi.json', string $title = 'Assegai API Docs'): string
  {
    $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
    $escapedSpecUrl = htmlspecialchars($specUrl, ENT_QUOTES | ENT_HTML5);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$escapedTitle}</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css">
    <style>
      :root {
        color-scheme: dark;
        --docs-bg: #0b0f1a;
        --docs-panel: rgba(15, 23, 41, 0.92);
        --docs-panel-strong: rgba(17, 24, 39, 0.96);
        --docs-border: rgba(148, 163, 184, 0.16);
        --docs-text: #f8fafc;
        --docs-text-soft: #f8fafc;
        --docs-accent: #22d3ee;
        --docs-accent-strong: #60a5fa;
        --docs-success: #22c55e;
        --docs-warning: #f59e0b;
        --docs-danger: #ef4444;
      }

      html {
        background:
          radial-gradient(circle at top left, rgba(34, 211, 238, 0.08), transparent 28%),
          radial-gradient(circle at top right, rgba(168, 85, 247, 0.08), transparent 32%),
          var(--docs-bg);
      }

      body {
        margin: 0;
        min-height: 100vh;
        background: transparent;
        color: var(--docs-text);
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      }

      #swagger-ui {
        max-width: min(1480px, calc(100vw - 2rem));
        margin: 0 auto;
      }

      .swagger-ui {
        color: var(--docs-text);
      }

      .swagger-ui .wrapper {
        max-width: none;
        padding: 2rem 0 3rem;
      }

      .swagger-ui .topbar {
        display: none;
      }

      .swagger-ui .info {
        margin: 0 0 1.75rem;
        padding: 1.6rem 1.7rem;
        border: 1px solid var(--docs-border);
        border-radius: 20px;
        background:
          radial-gradient(circle at top right, rgba(34, 211, 238, 0.08), transparent 28%),
          linear-gradient(180deg, rgba(15, 23, 41, 0.96), rgba(10, 15, 27, 0.96));
        box-shadow: 0 26px 70px rgba(2, 6, 23, 0.24);
      }

      .swagger-ui .info .title,
      .swagger-ui .info h1,
      .swagger-ui .info h2,
      .swagger-ui .info h3,
      .swagger-ui .info h4,
      .swagger-ui .info h5,
      .swagger-ui .info p,
      .swagger-ui .info li,
      .swagger-ui .info table,
      .swagger-ui .markdown p,
      .swagger-ui .markdown li,
      .swagger-ui .renderedMarkdown p,
      .swagger-ui .renderedMarkdown li,
      .swagger-ui .renderedMarkdown code,
      .swagger-ui .renderedMarkdown pre,
      .swagger-ui .opblock-tag,
      .swagger-ui .opblock-summary-description,
      .swagger-ui .responses-inner h4,
      .swagger-ui .responses-inner h5,
      .swagger-ui .response-col_status,
      .swagger-ui .response-col_description,
      .swagger-ui .parameter__name,
      .swagger-ui .parameter__type,
      .swagger-ui .parameter__deprecated,
      .swagger-ui .model-title,
      .swagger-ui .prop-name,
      .swagger-ui .prop-type,
      .swagger-ui .model,
      .swagger-ui .model-box,
      .swagger-ui .tab li button.tablinks,
      .swagger-ui .servers > label,
      .swagger-ui label,
      .swagger-ui .link,
      .swagger-ui section.models h4,
      .swagger-ui section.models h5,
      .swagger-ui .errors-wrapper hgroup h4,
      .swagger-ui .errors-wrapper .errors h4,
      .swagger-ui .errors-wrapper .errors small,
      .swagger-ui .download-url-wrapper label,
      .swagger-ui .download-url-wrapper .select-label span {
        color: var(--docs-text);
      }

      .swagger-ui .info .title small,
      .swagger-ui .info .base-url,
      .swagger-ui .markdown code,
      .swagger-ui .responses-inner .markdown p,
      .swagger-ui .parameter__extension,
      .swagger-ui .parameter__in,
      .swagger-ui .parameter__name.required span,
      .swagger-ui .response-col_links,
      .swagger-ui .opblock-external-docs-wrapper p,
      .swagger-ui .opblock-description-wrapper p,
      .swagger-ui .opblock-section-header h4,
      .swagger-ui .scheme-container .schemes > label,
      .swagger-ui .scheme-container .schemes .servers-title,
      .swagger-ui .btn,
      .swagger-ui .copy-to-clipboard,
      .swagger-ui .servers-title,
      .swagger-ui .servers > label select,
      .swagger-ui .download-url-wrapper .select-label,
      .swagger-ui .auth-container .scope-def,
      .swagger-ui .auth-container .scope-def p,
      .swagger-ui .auth-container h4,
      .swagger-ui .auth-container h5 {
        color: var(--docs-text-soft);
      }

      .swagger-ui .scheme-container {
        margin: 0 0 1.5rem;
        padding: 1rem 1.15rem;
        background: var(--docs-panel);
        border: 1px solid var(--docs-border);
        border-radius: 16px;
        box-shadow: none;
      }

      .swagger-ui .scheme-container .schemes,
      .swagger-ui .scheme-container .download-url-wrapper {
        gap: 0.75rem;
      }

      .swagger-ui .scheme-container select,
      .swagger-ui .scheme-container input,
      .swagger-ui input[type=text],
      .swagger-ui input[type=password],
      .swagger-ui input[type=search],
      .swagger-ui input[type=email],
      .swagger-ui input[type=file],
      .swagger-ui textarea,
      .swagger-ui select {
        background: rgba(11, 15, 26, 0.92);
        border: 1px solid var(--docs-border);
        color: var(--docs-text);
        border-radius: 12px;
        box-shadow: none;
      }

      .swagger-ui input::placeholder,
      .swagger-ui textarea::placeholder {
        color: #64748b;
      }

      .swagger-ui .btn,
      .swagger-ui .download-url-button,
      .swagger-ui .authorize {
        border-radius: 12px;
        border: 1px solid rgba(34, 211, 238, 0.24);
        background: rgba(34, 211, 238, 0.08);
        color: var(--docs-text);
        transition: background 180ms ease, border-color 180ms ease, transform 180ms ease;
      }

      .swagger-ui .btn:hover,
      .swagger-ui .download-url-button:hover,
      .swagger-ui .authorize:hover {
        background: rgba(34, 211, 238, 0.14);
        border-color: rgba(34, 211, 238, 0.32);
        transform: translateY(-1px);
      }

      .swagger-ui .opblock-tag {
        border-bottom: 1px solid var(--docs-border);
        font-size: 1.05rem;
      }

      .swagger-ui .opblock,
      .swagger-ui .model-container,
      .swagger-ui section.models,
      .swagger-ui .responses-inner,
      .swagger-ui .errors-wrapper,
      .swagger-ui .auth-wrapper {
        background: var(--docs-panel);
        border: 1px solid var(--docs-border);
        border-radius: 18px;
        box-shadow: none;
      }

      .swagger-ui .opblock .opblock-summary,
      .swagger-ui .opblock .opblock-section-header,
      .swagger-ui .responses-table thead tr td,
      .swagger-ui .responses-table thead tr th,
      .swagger-ui table thead tr td,
      .swagger-ui table thead tr th,
      .swagger-ui .model-container .model-box-control,
      .swagger-ui section.models .model-container {
        background: rgba(11, 15, 26, 0.76);
        border-color: var(--docs-border);
      }

      .swagger-ui .opblock.opblock-get {
        border-color: rgba(34, 197, 94, 0.4);
      }

      .swagger-ui .opblock.opblock-post {
        border-color: rgba(96, 165, 250, 0.4);
      }

      .swagger-ui .opblock.opblock-patch,
      .swagger-ui .opblock.opblock-put {
        border-color: rgba(245, 158, 11, 0.4);
      }

      .swagger-ui .opblock.opblock-delete {
        border-color: rgba(239, 68, 68, 0.4);
      }

      .swagger-ui .opblock-summary-method {
        border-radius: 10px;
        font-weight: 700;
      }

      .swagger-ui .opblock-summary-path,
      .swagger-ui .opblock-summary-path__deprecated {
        color: var(--docs-text);
        font-family: "JetBrains Mono", "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 0.85rem;
      }

      .swagger-ui .opblock-summary-control:focus,
      .swagger-ui .btn:focus,
      .swagger-ui input:focus,
      .swagger-ui textarea:focus,
      .swagger-ui select:focus {
        outline: 2px solid rgba(34, 211, 238, 0.28);
        outline-offset: 2px;
        box-shadow: none;
      }

      .swagger-ui .highlight-code,
      .swagger-ui .microlight,
      .swagger-ui pre,
      .swagger-ui code {
        background: rgba(11, 15, 26, 0.94);
        color: #dbeafe;
      }

      .swagger-ui .copy-to-clipboard {
        background: rgba(11, 15, 26, 0.94);
      }

      .swagger-ui .responses-table tbody tr td,
      .swagger-ui table tbody tr td,
      .swagger-ui .parameter__name,
      .swagger-ui .parameter__type,
      .swagger-ui .response-col_status,
      .swagger-ui .response-col_description__inner p,
      .swagger-ui .response-col_description__inner div,
      .swagger-ui .model-example,
      .swagger-ui .model-box,
      .swagger-ui .prop {
        color: var(--docs-text);
      }

      .swagger-ui .prop-format,
      .swagger-ui .parameter__enum,
      .swagger-ui .response-col_description__inner small,
      .swagger-ui .model-hint,
      .swagger-ui .response-undocumented {
        color: var(--docs-text-soft);
      }

      .swagger-ui a,
      .swagger-ui .link,
      .swagger-ui .info a,
      .swagger-ui .renderedMarkdown a {
        color: var(--docs-accent);
      }

      .swagger-ui .response-col_status {
        font-weight: 700;
      }

      .swagger-ui .errors-wrapper {
        color: var(--docs-text);
      }

      .swagger-ui .errors-wrapper .errors {
        background: rgba(239, 68, 68, 0.08);
      }

      @media (max-width: 768px) {
        #swagger-ui {
          max-width: calc(100vw - 1rem);
        }

        .swagger-ui .wrapper {
          padding: 1rem 0 2rem;
        }

        .swagger-ui .info {
          padding: 1.2rem 1.1rem;
        }
      }
    </style>
  </head>
  <body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-standalone-preset.js"></script>
    <script>
      window.ui = SwaggerUIBundle({
        url: '{$escapedSpecUrl}',
        dom_id: '#swagger-ui',
        deepLinking: true,
        displayRequestDuration: true,
        docExpansion: 'list',
        filter: true,
        syntaxHighlight: {
          activate: true,
          theme: 'obsidian',
        },
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset,
        ],
        layout: 'BaseLayout',
      });
    </script>
  </body>
</html>
HTML;
  }
}
