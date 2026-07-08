# Overview

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)

BabelChrome is a macOS browser workspace built around a native CEF-based application and an installable module system. The current implemented module runtimes are `php-web`, `php-class`, and `static-web`, and the module contract is intended to stay runtime-aware rather than PHP-only.

The browser provides the native shell, tab model, groups, address bar, local service host, module installation UI, stable `babelchrome://` routes, and runtime integration with macOS. Modules provide optional capabilities such as Markdown rendering, OpenAPI rendering, JSON rendering, local project launching, and framework integration examples.

The meta workspace keeps those responsibilities separated:

- the browser owns native application behavior and the extension host runtime;
- libraries provide reusable code consumed by modules;
- modules own their own dependencies, manifests, frontend assets, tests, and runtime entrypoints;
- tooling packages modules into production zip artifacts.

This split keeps BabelChrome extensible without turning the browser repository into a vendor directory for every viewer or tool.

## Main Concepts

### Browser

The browser is the native app. It owns user-facing navigation, CEF integration, local protocols, module installation, and extension host lifecycle.

### Extension Host

The ExtensionHost is currently implemented in PHP and embedded in the browser resources. It discovers installed modules, dispatches module routes through supported runtime handlers, exposes internal APIs, and bridges module behavior back to the native shell.

### Modules

Modules are independent extension projects shipped as zip packages. Today, the implemented runtime handlers support PHP modules such as Symfony apps, Laravel apps, plain PHP front controllers, PHP class entrypoints, and static web modules that need no PHP entrypoint. The manifest contract is runtime-aware so future handlers can support additional non-PHP module layouts without changing the native browser contract.

### Libraries

Libraries are shared packages used by modules. `babel-chrome-viewer-kit` is the first shared library and provides common viewer shell behavior such as the document header and `Open with` controls.

Navigation: [README](README.md) | [Next: Workspace Layout](02-workspace-layout.md)
