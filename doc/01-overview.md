# Overview

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)

BabelChrome is a macOS browser workspace built around a native CEF-based application and an installable module system. The public module contract is language-agnostic and is centered on `static-web`, `process-web`, and `process-runtime` runtimes.

The browser provides the native shell, tab model, groups, address bar, native module registry and installer, local service host, stable `babelchrome://` routes, and runtime integration with macOS. Modules provide optional capabilities such as Markdown rendering, OpenAPI rendering, JSON rendering, local project launching, and framework integration examples.

The meta workspace keeps those responsibilities separated:

- the browser owns native application behavior and the extension host runtime;
- libraries provide reusable code consumed by modules;
- modules own their own dependencies, manifests, frontend assets, tests, and runtime entrypoints;
- tooling packages modules into production zip artifacts.

This split keeps BabelChrome extensible without turning the browser repository into a vendor directory for every viewer or tool.

## Main Concepts

### Browser

The browser is the native app. It owns user-facing navigation, CEF integration, local protocols, module discovery, module installation, module enable/disable/remove state, and extension host lifecycle.

### Extension Host

The ExtensionHost is currently implemented in PHP and embedded in the browser resources. It dispatches module routes through supported runtime handlers, exposes internal APIs, and bridges module behavior back to the native shell. It is no longer the owner of installed-module discovery or install/remove/enable/disable state.

### Modules

Modules are independent extension projects shipped as zip packages. A module may use PHP, Node.js, Ruby, Python, or another runtime, but that runtime belongs to the module and must be declared through the manifest and readiness checks. PHP-based web modules therefore run as `process-web` modules instead of relying on a browser-level PHP adapter.

### Libraries

Libraries are shared packages used by modules. `babel-chrome-viewer-kit` is the first shared library and provides common viewer shell behavior such as the document header and `Open with` controls.

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)
