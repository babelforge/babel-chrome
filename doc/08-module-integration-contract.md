# Module Integration Contract

Navigation: [Previous: Release Artifacts](07-release-artifacts.md) | [README](README.md)

This page is the canonical contract for the integration features that modules can rely on when they are installed in BabelChrome. The native browser should stay module-agnostic: modules declare capabilities in `manifest.json`, and BabelChrome exposes small generic services around routing, file handling, lifecycle, local application opening, and message delivery.

## Manifest Surface

Each module is discovered from its root `manifest.json`. The important public fields are:

- `id`, `name`, `version`, `description`, and `requirements` metadata such as `{ "php": ">=8.4" }`;
- `runtime.type` and `runtime.entrypoint`;
- `runtime.processIsolation` when a module needs a dedicated PHP process;
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

The module contract is runtime-aware. The currently implemented runtimes are:

- `php-web`, which executes a PHP front controller such as `public/index.php`;
- `php-class`, which executes a PHP class implementing `BabelChromeModuleInterface`;
- `static-web`, which serves a static document root without PHP module code.

Legacy manifests remain accepted:

- `web` is normalized to `php-web`;
- `class` or a missing runtime is normalized to `php-class`.

Future runtime families such as `process-web` and `process-runtime` are planned but not implemented yet. Documentation should not describe them as available until the ExtensionHost supports them.

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

`static-web` modules do not need a PHP entrypoint, Composer vendor directory, or `requirements.php`. BabelChrome serves the declared index file through the same stable module routes as other modules. Public assets are still served from the module `public/` directory through tokenized `/module/<id>/assets/...` URLs.

Static text documents can use request-scoped placeholders:

```html
<link rel="stylesheet" href="{{ BABELCHROME_MODULE_ASSET_BASE_URL }}/app.css{{ BABELCHROME_MODULE_ASSET_TOKEN_QUERY }}">
```

Supported placeholders include `BABELCHROME_MODULE_ID`, `BABELCHROME_MODULE_NAME`, `BABELCHROME_MODULE_VERSION`, `BABELCHROME_MODULE_ROUTE`, `BABELCHROME_MODULE_ASSET_BASE_URL`, `BABELCHROME_MODULE_ASSET_TOKEN_QUERY`, `BABELCHROME_LOCAL_SERVICE_BASE_URL`, `BABELCHROME_LOCAL_SERVICE_TOKEN`, and `BABELCHROME_SOURCE_URL`.

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

Readiness commands are intended to be read-only, idempotent, and fast. The ExtensionHost runs supported readiness commands from the module root and expects a JSON object such as:

```json
{
  "ready": false,
  "status": "missing-dependencies",
  "messages": ["Node.js >= 22 is required"],
  "canSetup": true
}
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

Opening `babelchrome://project-launcher` asks BabelChrome to dispatch the route to the installed module. The tokenized local HTTP URL is an implementation detail of the ExtensionHost and should not be stored by callers.

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

Viewer modules can use BabelChrome's module-agnostic open-with endpoints for local files:

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

Button-like controls do not expose the link context menu by default. Controls that are safe navigation targets, such as `Details`, `Settings`, `Back`, `PHP Modules`, or `Check Updates`, opt in with `data-can-open-menu="true"`.

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
