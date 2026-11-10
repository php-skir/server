<?php

declare(strict_types=1);

namespace Skir\Server\Studio;

/**
 * The page that hosts the official RPC Studio app (https://skir.build/docs/skirrpc#rpc-studio).
 *
 * The app is one script. It reads the endpoint's method list from `?list` and calls the methods itself.
 */
final class StudioRenderer
{
    public function render(string $appJsUrl): string
    {
        $escapedAppJsUrl = htmlspecialchars($appJsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>RPC Studio</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐙</text></svg>">
    <script src="{$escapedAppJsUrl}"></script>
  </head>
  <body style="margin: 0; padding: 0;">
    <skir-studio-app></skir-studio-app>
  </body>
</html>
HTML;
    }
}
