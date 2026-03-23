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
