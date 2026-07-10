# Overview

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)

BabelChrome is a macOS browser workspace built around a native CEF-based application and an installable module system. The public module contract is language-agnostic and is centered on `static-web`, `process-web`, and `process-runtime` runtimes.

The browser provides the native shell, tab model, groups, address bar, native module registry and installer, native process runtime actions, native process-web route proxying, native viewer source helpers, native on-demand process-runtime route execution, local service host APIs that are still being migrated, stable `babelchrome://` routes, and runtime integration with macOS. Modules provide optional capabilities such as Markdown rendering, OpenAPI rendering, JSON rendering, local project launching, and framework integration examples.

The meta workspace keeps those responsibilities separated:

- the browser owns native application behavior and the extension host runtime;
- libraries provide reusable code consumed by modules;
- modules own their own dependencies, manifests, frontend assets, tests, and runtime entrypoints;
- tooling packages modules into production zip artifacts.

This split keeps BabelChrome extensible without turning the browser repository into a vendor directory for every viewer or tool.

## Main Concepts

### Browser

The browser is the native app. It owns user-facing navigation, CEF integration, local protocols, module discovery, module installation, module enable/disable/remove state, native process-web lifecycle and proxying, native viewer dispatch/source helpers, native on-demand process-runtime route execution, and extension host lifecycle for the remaining transitional APIs.

### Extension Host

The ExtensionHost is currently implemented in PHP and embedded in the browser resources. It exposes internal APIs and runtime paths that have not yet moved fully native. It is no longer the owner of installed-module discovery, install/remove/enable/disable state, viewer dispatch/source helpers, the main `process-web` route proxy, or on-demand `process-runtime` route execution.

### Modules

Modules are independent extension projects shipped as zip packages. A module may use PHP, Node.js, Ruby, Python, or another runtime, but that runtime belongs to the module and must be declared through the manifest. Required executable paths are declared through host-rendered `requiredSettings`, and optional health checks can use readiness. PHP-based web modules therefore run as `process-web` modules instead of relying on a browser-level PHP adapter.

### Libraries

Libraries are shared packages used by modules. `babel-chrome-viewer-kit` is the first shared library and provides common viewer shell behavior such as the document header and `Open with` controls.

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)
