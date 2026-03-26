<?php

namespace Assegai\Core\Exceptions\Handlers\Support;

final class FrameworkErrorPageRenderer
{
  public static function render(
    int $statusCode,
    string $statusName,
    string $heading,
    string $message,
    ?string $details = null,
  ): string
  {
    $escapedStatusName = htmlspecialchars($statusName, ENT_QUOTES, 'UTF-8');
    $escapedHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $escapedDetails = $details !== null && $details !== ''
      ? htmlspecialchars($details, ENT_QUOTES, 'UTF-8')
      : '';

    $detailsMarkup = $escapedDetails !== ''
      ? '<div class="assegai-error-details"><span>Details</span><code>' . $escapedDetails . '</code></div>'
      : '';

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$statusCode} {$escapedStatusName}</title>
  <style>
    :root {
      color-scheme: dark;
      --bg: #0b0f1a;
      --panel: rgba(17, 24, 39, 0.92);
      --border: rgba(148, 163, 184, 0.16);
      --text: #f8fafc;
      --muted: #cbd5e1;
      --soft: #94a3b8;
      --cyan: #67e8f9;
      --purple: #a855f7;
      --blue: #60a5fa;
      --gradient: linear-gradient(135deg, #67e8f9 0%, #8b5cf6 48%, #60a5fa 100%);
      --shadow: 0 30px 90px rgba(2, 6, 23, 0.5);
      --radius: 24px;
      --font: Inter, system-ui, sans-serif;
      --mono: "JetBrains Mono", ui-monospace, monospace;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 2rem;
      background:
        radial-gradient(circle at 12% 0%, rgba(103, 232, 249, 0.14), transparent 26%),
        radial-gradient(circle at 88% 10%, rgba(168, 85, 247, 0.14), transparent 30%),
        linear-gradient(180deg, #0d1321 0%, #0b0f1a 24%, #090d16 100%);
      color: var(--text);
      font-family: var(--font);
    }

    .assegai-error-shell {
      width: min(100%, 980px);
      display: grid;
      gap: 1.5rem;
    }

    .assegai-error-card {
      position: relative;
      overflow: hidden;
      padding: clamp(2rem, 5vw, 3.5rem);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--panel);
      box-shadow: var(--shadow);
    }

    .assegai-error-card::before {
      content: "";
      position: absolute;
      inset: 0 auto auto 0;
      width: 100%;
      height: 1px;
      background: var(--gradient);
      opacity: 0.7;
    }

    .assegai-error-label {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      color: var(--cyan);
      font-family: var(--mono);
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .assegai-error-label::before {
      content: "";
      display: inline-block;
      width: 0.55rem;
      height: 0.55rem;
      border-radius: 999px;
      background: var(--gradient);
      box-shadow: 0 0 24px rgba(103, 232, 249, 0.4);
    }

    .assegai-error-code {
      margin: 1.1rem 0 0.4rem;
      font-size: clamp(4rem, 11vw, 8rem);
      line-height: 0.92;
      letter-spacing: -0.08em;
      font-weight: 800;
    }

    .assegai-error-heading {
      margin: 0;
      font-size: clamp(2rem, 5vw, 3.5rem);
      line-height: 0.96;
      letter-spacing: -0.05em;
      text-wrap: balance;
    }

    .assegai-error-message {
      margin: 1.15rem 0 0;
      max-width: 48rem;
      color: var(--muted);
      font-size: 1.08rem;
      line-height: 1.8;
    }

    .assegai-error-details {
      display: grid;
      gap: 0.45rem;
      margin-top: 1.5rem;
      padding-top: 1.4rem;
      border-top: 1px solid rgba(148, 163, 184, 0.08);
    }

    .assegai-error-details span {
      color: var(--soft);
      font-family: var(--mono);
      font-size: 0.75rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .assegai-error-details code {
      display: inline-block;
      width: fit-content;
      max-width: 100%;
      overflow-wrap: anywhere;
      padding: 0.55rem 0.7rem;
      border-radius: 0.75rem;
      background: rgba(15, 23, 41, 0.88);
      color: var(--cyan);
      font-family: var(--mono);
      font-size: 0.88rem;
    }

    .assegai-error-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      margin-top: 1.8rem;
    }

    .assegai-error-actions a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 3rem;
      padding: 0 1.15rem;
      border-radius: 12px;
      border: 1px solid transparent;
      color: var(--text);
      text-decoration: none;
      font-weight: 700;
      transition: transform 180ms ease, border-color 180ms ease, background 180ms ease;
    }

    .assegai-error-actions a:hover {
      transform: translateY(-1px);
    }

    .assegai-error-actions a:first-child {
      background: var(--gradient);
      color: #f8fafc;
    }

    .assegai-error-actions a:last-child {
      border-color: var(--border);
      background: rgba(15, 23, 41, 0.72);
    }

    .assegai-error-footer {
      color: var(--soft);
      font-family: var(--mono);
      font-size: 0.8rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
  </style>
</head>
<body>
  <div class="assegai-error-shell">
    <section class="assegai-error-card">
      <div class="assegai-error-label">AssegaiPHP</div>
      <div class="assegai-error-code">{$statusCode}</div>
      <h1 class="assegai-error-heading">{$escapedHeading}</h1>
      <p class="assegai-error-message">{$escapedMessage}</p>
      {$detailsMarkup}
      <div class="assegai-error-actions">
        <a href="/">Go Home</a>
        <a href="javascript:history.back()">Go Back</a>
      </div>
    </section>
    <div class="assegai-error-footer">Structured PHP without the guesswork.</div>
  </div>
</body>
</html>
HTML;
  }
}
