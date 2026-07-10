# Module Integration Contract

Navigation: [Previous: Release Artifacts](07-release-artifacts.md) | [README](README.md)

This page is the canonical contract for the integration features that modules can rely on when they are installed in BabelChrome. The native browser should stay module-agnostic: modules declare capabilities in `manifest.json`, and BabelChrome exposes small generic services around routing, file handling, lifecycle, local application opening, and message delivery.

## Manifest Surface

Each module is discovered from its root `manifest.json`. The important public fields are:

- `id`, `name`, `version`, `description`, and `requirements` metadata such as `{ "php": ">=8.4" }` or `{ "node": ">=22" }`;
- `runtime.type`;
- `runtime.documentRoot` and `runtime.index` for `static-web` modules;
- `runtime.command`, `runtime.args`, `runtime.cwd`, `runtime.env`, `runtime.readyUrl`, `runtime.timeoutMs`, `runtime.startPolicy`, and `runtime.stop` for `process-web` modules;
- `runtime.command`, `runtime.args`, `runtime.cwd`, `runtime.env`, `runtime.timeoutMs`, `runtime.mode`, `runtime.commands`, and `runtime.stop` for `process-runtime` modules;
- `requiredSettings` for host-rendered runtime settings such as executable paths;
- `readiness` for optional read-only readiness checks;
- `setup` for optional user-confirmed setup commands;
- `routes` for stable `babelchrome://` URLs;
- `fileTypes` for generic viewer routing;
- `file-type-handler.fileTypes` for file type capability advertising;
- `supports` for capability-based message routing;
- `hooks` and `menuItems` for host-visible integration points;
- `badge` for address bar badge metadata;
- `settings.route` for a module-owned settings page;
- `defaultGroup` for preferred tab placement.

`defaultGroup` is only a placement hint. BabelChrome creates the group if needed when the module tab is opened or recreated, but the user can move the tab afterward.

## Runtime Types

The supported module runtimes are:

- `static-web`, which serves a static document root without any module-owned process;
- `process-web`, which starts a module-owned local HTTP server on a BabelChrome-assigned port and proxies module routes to it;
- `process-runtime`, which starts or invokes a module-owned process without implying any HTTP server.

PHP is not a browser-level runtime in the public contract. A Symfony, Laravel, or plain PHP module should declare `process-web`, declare the PHP executable through `requiredSettings`, and start its own PHP front controller with the resolved executable. Older `php-web`, `php-class`, `web`, or implicit-class manifests are not part of the fresh module platform and must be rebuilt.

A static web module declares a document root and an index file:

```json
{
  "runtime": {
    "type": "static-web",
    "documentRoot": "public",
    "index": "index.html"
  }
}
```

`static-web` modules do not need a process command, Composer vendor directory, or `requirements.php`. BabelChrome serves the declared index file through the same stable module routes as other modules. Public assets are still served from the module `public/` directory through tokenized `/module/<id>/assets/...` URLs.

Static text documents can use request-scoped placeholders:

```html
<link rel="stylesheet" href="{{ BABELCHROME_MODULE_ASSET_BASE_URL }}/app.css{{ BABELCHROME_MODULE_ASSET_TOKEN_QUERY }}">
```

Supported placeholders include `BABELCHROME_MODULE_ID`, `BABELCHROME_MODULE_NAME`, `BABELCHROME_MODULE_VERSION`, `BABELCHROME_MODULE_ROUTE`, `BABELCHROME_MODULE_ASSET_BASE_URL`, `BABELCHROME_MODULE_ASSET_TOKEN_QUERY`, `BABELCHROME_LOCAL_SERVICE_BASE_URL`, `BABELCHROME_LOCAL_SERVICE_TOKEN`, and `BABELCHROME_SOURCE_URL`.

A process web module declares how BabelChrome starts its local HTTP server:

```json
{
  "runtime": {
    "type": "process-web",
    "startPolicy": "lazy",
    "command": "node",
    "args": ["server/index.js", "--port={{ port }}"],
    "cwd": ".",
    "env": {
      "NODE_ENV": "production"
    },
    "readyUrl": "http://127.0.0.1:{{ port }}/health",
    "timeoutMs": 10000,
    "stop": {
      "signal": "TERM",
      "timeoutMs": 3000
    }
  }
}
```

`process-web` modules do not need a browser-owned PHP adapter, Composer vendor directory, or `requirements.php`. The runtime supports these placeholders in `command`, `args`, `env`, and `readyUrl`: `{{ port }}`, `{{ moduleId }}`, `{{ moduleDir }}`, and `{{ settings.<key> }}` for values resolved from `requiredSettings`.

BabelChrome assigns the port at runtime. The process port is an implementation detail and must not appear in stable user-facing URLs. Callers should continue to use the declared `babelchrome://` route, and the native host rebuilds the runtime URL after app restart.

`runtime.startPolicy` is optional. The default value is `lazy`, which starts the module process on first route access. A `prewarm` value asks BabelChrome to start the module process before it is needed when possible. During session restore, the module needed by the restored active tab is prewarmed first, before the first browser view is created. Other enabled `prewarm` modules are then started in the background with a serial queue so startup is not flooded by multiple local servers.

When BabelChrome proxies a route to the process, it forwards BabelChrome context with HTTP headers:

```text
X-BabelChrome-Module-Id
X-BabelChrome-Module-Route
X-BabelChrome-Source-Url
X-BabelChrome-Local-Service-Base-Url
X-BabelChrome-Local-Service-Token
X-BabelChrome-Module-Asset-Base-Url
X-BabelChrome-File-Types
```

The request query is proxied except for the ExtensionHost `token`. On module disable, remove, update, or `app.will-quit`, BabelChrome stops running process-web instances.

The modules page shows compact status badges for enabled state, readiness, and runtime state. The module details page exposes process runtime diagnostics from the host. For `process-web`, diagnostics include state, running flag, start policy, prewarm status when available, assigned port, process base URL, readiness URL, command, working directory, and captured logs. `Start runtime`, `Restart runtime`, and `Stop runtime` actions are shown when they match the current process state. Restart stops any current process, starts a new one, waits for readiness, and returns the refreshed runtime status. The internal endpoints are:

```text
/internal/modules/runtime-restart?moduleId=<module-id>
/internal/modules/runtime-stop?moduleId=<module-id>
```

Runtime status can be read without starting a stopped process:

```text
/internal/modules/runtime-status?moduleId=<module-id>
```

For `process-runtime`, diagnostics expose the runtime mode, state, command, working directory, and captured logs for long-running instances. On-demand process-runtime modules usually report `idle`, because no persistent process is expected between requests.

A process runtime module declares a non-web command contract:

```json
{
  "runtime": {
    "type": "process-runtime",
    "mode": "on-demand",
    "command": "node",
    "args": ["worker/index.js", "{{ route }}"],
    "cwd": ".",
    "env": {
      "NODE_ENV": "production"
    },
    "timeoutMs": 10000,
    "commands": {
      "lifecycle": {
        "command": "node",
        "args": ["worker/lifecycle.js", "{{ hook }}"]
      }
    },
    "stop": {
      "signal": "TERM",
      "timeoutMs": 3000
    }
  }
}
```

Supported modes are:

- `on-demand`, which starts a fresh process for each declared module route or hook route;
- `long-running`, which starts and keeps a process alive until the module is disabled, removed, updated, or BabelChrome quits.

`process-runtime` modules do not need a browser-owned PHP adapter, Composer vendor directory, or `requirements.php`. The runtime supports these placeholders in `command`, `args`, and `env`: `{{ moduleId }}`, `{{ moduleDir }}`, `{{ route }}`, `{{ hook }}`, `{{ sourceUrl }}`, and `{{ settings.<key> }}` for values resolved from `requiredSettings`.

For on-demand execution, BabelChrome writes a JSON payload to stdin:

```json
{
  "module": {
    "id": "vendor.module",
    "name": "Vendor Module",
    "version": "1.0.0",
    "path": "/path/to/installed/module"
  },
  "route": "index",
  "hook": "app.did-start",
  "sourceUrl": "https://example.com/source",
  "localServiceBaseUrl": "http://127.0.0.1:61000",
  "query": {
    "name": "value"
  },
  "fileTypes": "md,json"
}
```

The process may return plain stdout, which BabelChrome serves as `text/plain`, or JSON stdout:

```json
{
  "statusCode": 200,
  "contentType": "application/json; charset=utf-8",
  "body": {
    "ok": true
  }
}
```

If the command exits non-zero or times out, BabelChrome surfaces stdout and stderr in the module error page. A process-runtime module does not expose a browser route unless it explicitly declares one in `routes`. Lifecycle hooks target the runtime through the declared route used by the host, currently `lifecycle` for `app.did-start` and `app.will-quit`.

## Required Runtime Settings

Modules may declare host-rendered settings that must be valid before their process runtime can start. This is intended for dependencies that the module cannot render a settings page for by itself, such as the PHP executable needed to start a PHP-backed viewer:

```json
{
  "requiredSettings": {
    "phpPath": {
      "type": "executable",
      "label": "PHP executable",
      "binary": "php",
      "minVersion": "8.4",
      "autoDetectPaths": [
        "/opt/homebrew/opt/php@8.4/bin/php",
        "/usr/local/opt/php@8.4/bin/php",
        "/usr/local/bin/php"
      ],
      "versionArgs": ["-v"]
    }
  },
  "runtime": {
    "type": "process-web",
    "command": "{{ settings.phpPath }}",
    "args": ["-S", "127.0.0.1:{{ port }}", "-t", "public", "public/index.php"]
  }
}
```

For `type: "executable"`, BabelChrome validates that the path exists, is executable, and optionally satisfies `minVersion` using `versionArgs`. Missing values are auto-detected from `autoDetectPaths` when possible. Manual values are saved per module and setting key from the host-rendered page `babelchrome://settings/<module-id>?runtimeSettings=1`.

When required settings are invalid, BabelChrome does not start or prewarm the runtime. Opening the module navigates to its host-rendered settings page instead. Resolved values are available to runtime templates as `{{ settings.<key> }}` and to the child process environment as `BABELCHROME_SETTING_<KEY>`.

## Readiness And Setup

Modules may declare an optional readiness check:

```json
{
  "readiness": {
    "type": "command",
    "command": "./bin/babelchrome-ready",
    "timeoutMs": 5000
  }
}
```

Readiness commands are intended to be read-only, idempotent, and fast. They are not a replacement for `requiredSettings`: use `requiredSettings` for dependencies that are needed before the module process can start, and use readiness for optional health checks after the host can already execute the required command. Supported readiness commands run from the module root and expect a JSON object such as:

```json
{
  "ready": false,
  "status": "missing-dependencies",
  "messages": ["Node.js >= 22 is required"],
  "canSetup": true
}
```

Modules that declare readiness expose a `Check readiness` action on their details page. The action returns the same status shape as the module snapshot:

```text
/internal/modules/readiness-status?moduleId=<module-id>
```

Modules may also declare an optional setup command:

```json
{
  "setup": {
    "type": "command",
    "command": "./bin/babelchrome-setup",
    "timeoutMs": 600000,
    "requiresConfirmation": true
  }
}
```

Setup commands are executable module actions, but BabelChrome must not run them automatically during install, startup, or update; setup requires an explicit user action.
Setup commands are exposed from the module details page when the module declares `setup` and its readiness state is not already ready. BabelChrome asks the user for confirmation, runs the command from the module root, captures stdout and stderr, then re-runs readiness so the module details page reflects the new state.

The setup response follows the same JSON-first convention as readiness. A command may print a JSON object such as:

```json
{
  "ok": true,
  "status": "completed",
  "messages": ["Dependencies installed"]
}
```

If stdout is not JSON, BabelChrome still shows stdout, stderr, timeout state, and exit code. A failing setup command must not break the browser or disable the module automatically.

## Stable Module Routes

Modules expose stable URLs through `routes`:

```json
{
  "routes": [
    {
      "scheme": "babelchrome",
      "host": "project-launcher",
      "handler": "index"
    }
  ]
}
```

Opening `babelchrome://project-launcher` asks BabelChrome to dispatch the route to the installed module. The tokenized local HTTP URL is an implementation detail of the native host and should not be stored by callers.

The Project Launcher also exposes stable server URLs such as:

```text
babelchrome://server/visualizer/architecture?project=project-1
```

Those URLs stay valid even when the managed local server receives a different port after restart.

## Generic Viewer URLs

Viewer modules can support local files and remote URLs without exposing module-specific routes to other apps:

```text
babelchrome://viewer/file/<encoded-local-path>
babelchrome://viewer/url/<encoded-source-url>
```

BabelChrome resolves the source extension against enabled modules. If no enabled module handles the source, BabelChrome displays a `No viewer installed for this file type` page.

The generic viewer URL should be preferred over direct module URLs in external integrations because it keeps the source stable and lets BabelChrome choose the active viewer module.

## File Type Advertising

Enabled modules that declare `file-type-handler.fileTypes` contribute to the request header sent by BabelChrome to HTTP and HTTPS pages:

```text
X-BabelChrome-File-Types: md,markdown,mmd,mermaid,yaml,yml,json
```

This header is intentionally narrow. It advertises handled file extensions only; it does not expose module names, versions, or installed paths.

`fileTypes` and `file-type-handler.fileTypes` can differ. `fileTypes` controls viewer source matching, while `file-type-handler.fileTypes` controls what BabelChrome advertises to HTTP and HTTPS pages.

The header is refreshed when modules are installed, removed, enabled, or disabled. A web app can use it with the User-Agent marker to decide whether to emit `babelchrome://viewer/...` links for files that BabelChrome can render.

## Address Bar Badges

Viewer and tool modules can declare a compact address badge:

```json
{
  "badge": {
    "text": "MD",
    "textColor": "#ffffff",
    "backgroundColor": "#0969da"
  }
}
```

BabelChrome displays the badge inside the address field for URLs handled by that module. If the same manifest exposes `settings.route`, the badge context menu can offer `View Settings`.

Badges are metadata only. The module still owns the viewer or tool UI.

## Module Settings

Module settings should live on module-owned routes, for example:

```json
{
  "settings": {
    "route": "babelchrome://settings/babelforge.markdown-viewer"
  }
}
```

When this route exists, `babelchrome://modules` shows a `Settings` action for the module. Modules without a settings route should not display a settings action.

## Open With Integration

Viewer modules can use BabelChrome's native module-agnostic open-with endpoints for local files:

```text
GET  /internal/open-with/list/<extension>
POST /internal/open-with/set/<extension>
POST /internal/open-with/open
```

`/internal/open-with/list/<extension>` returns the shared preferred application, when it is still available, and the macOS applications discovered for that extension.

`/internal/open-with/set/<extension>` stores a shared preferred application after validating the application identifier.

`/internal/open-with/open` opens a local file with the requested application identifier, or falls back to the macOS default application when the identifier is empty or unavailable.

The shared preference is keyed by file extension, not by module. A module may use its own storage instead, but the shared endpoints provide the common BabelChrome behavior used by the current viewers.

## Message Relay

Modules can send opaque messages to compatible open tabs through:

```text
POST /internal/message-relay
```

The message envelope is capability-based:

```json
{
  "supports": "file-viewer",
  "message": {
    "event": "viewer-changed",
    "extension": "md",
    "applicationId": "com.microsoft.VSCode"
  }
}
```

BabelChrome validates the envelope and broadcasts it to pages whose module declares the requested `supports` capability. The host does not interpret the inner `message` object. This keeps the browser generic while allowing viewer modules to synchronize controls such as `Open with`.

## Lifecycle Hooks

Enabled modules can declare lifecycle hooks in `hooks`. BabelChrome currently dispatches:

- `app.did-start` after the native window and restored tabs have been rebuilt;
- `app.will-quit` before the ExtensionHost process is stopped.

The Project Launcher uses these hooks to snapshot launched servers on quit, stop them cleanly, and restart the previously running servers on the next launch.

## Local Drop Hook

Modules that declare `drop.local-paths` can receive local filesystem paths dropped into drop-aware module pages. BabelChrome intercepts native CEF file drops and forwards local paths only to a tab that opted into the capability.

This is used by Project Launcher so users can drop a folder or a `babelchrome.json` file into the module UI. Normal web pages keep normal browser drag-and-drop behavior.

## Module Menu Items

Modules can declare menu contributions in `menuItems`:

```json
{
  "menuItems": [
    {
      "id": "markdown.open-source-file",
      "label": "Open Source File",
      "hook": "context-menu.build",
      "route": "babelchrome://markdown/action/open-source-file",
      "contexts": ["markdown.local-file"]
    }
  ]
}
```

The internal module menu endpoint returns these declarations for host-side context menu wiring and diagnostics. Module menu contributions are declarative; the module still owns the target route.

## Internal Page Context Menus

BabelChrome internal pages use an explicit opt-in convention for button-like links:

```html
<a class="smallButton" data-can-open-menu="true" href="babelchrome://modules">Back to modules</a>
```

Button-like controls do not expose the link context menu by default. Controls that are safe navigation targets, such as `Details`, `Settings`, `Back`, `Modules`, or `Check Updates`, opt in with `data-can-open-menu="true"`.

Destructive or state-changing actions such as `Remove`, `Disable`, `Enable`, `Install`, `Restart`, and `Set update source` should not opt in.

This is currently a BabelChrome internal-page convention, not a universal browser API injected into every module page. A module can reuse the same HTML convention in its own UI, but it must provide its own frontend behavior unless it uses a shared UI helper that implements it.

## Current Hook Names

The current module ecosystem uses these hook names:

- `address.badge.resolve`;
- `app.did-start`;
- `app.will-quit`;
- `context-menu.build`;
- `drop.local-paths`;
- `file.open.resolve`;
- `settings.section.register`;
- `tab.title.resolve`;
- `url.resolve`.

Some hooks are already wired by the host. Others exist as declared integration points for modules and diagnostics while native behavior continues to evolve. Prefer stable route and viewer contracts when integrating external apps.

Navigation: [Previous: Release Artifacts](07-release-artifacts.md) | [README](README.md)
