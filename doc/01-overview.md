# Overview

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)

BabelChrome is a macOS browser workspace built around a native CEF-based application and an installable PHP module system.

The browser provides the native shell, tab model, groups, address bar, local service host, module installation UI, stable `babelchrome://` routes, and runtime integration with macOS. Modules provide optional capabilities such as Markdown rendering, OpenAPI rendering, JSON rendering, local project launching, and framework integration examples.

The meta workspace keeps those responsibilities separated:

- the browser owns native application behavior and the extension host runtime;
- libraries provide reusable code consumed by modules;
- modules own their own Composer dependencies, manifests, frontend assets, tests, and runtime entrypoints;
- tooling packages modules into production zip artifacts.

This split keeps BabelChrome extensible without turning the browser repository into a vendor directory for every viewer or tool.

## Main Concepts

### Browser

The browser is the native app. It owns user-facing navigation, CEF integration, local protocols, module installation, and extension host lifecycle.

### Extension Host

The extension host is the PHP runtime embedded in the browser resources. It discovers installed modules, dispatches module routes, exposes internal APIs, and bridges module behavior back to the native shell.

### Modules

Modules are independent PHP projects shipped as zip packages. A module can be a Symfony app, a Laravel app, a plain PHP front controller, or another PHP layout, as long as it follows the BabelChrome manifest contract.

### Libraries

Libraries are shared packages used by modules. `babel-chrome-viewer-kit` is the first shared library and provides common viewer shell behavior such as the document header and `Open with` controls.

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)
