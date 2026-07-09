# Workspace Layout

Navigation: [Previous: Overview](01-overview.md) | [README](README.md) | [Next: Git And Submodules](03-git-and-submodules.md)

The target workspace is organized as a meta repository with nested independent repositories.

```text
babel-chrome/
  browser/
  library/
    viewer-kit/
  modules/
    demo-module/
    json-viewer-module/
    laravel-module/
    markdown-viewer-module/
    openapi-viewer-module/
    plain-php-module/
    project-launcher-module/
    process-runtime-demo-module/
    process-web-demo-module/
  tools/
  zip/
  doc/
```

## Root Workspace

The root workspace owns orchestration:

- root documentation;
- module packaging scripts;
- submodule pointers;
- generated production zip output location.

It should not directly own browser, library, or module implementation files after the submodule migration is complete.

## Browser

`browser/` contains the native BabelChrome application:

- CEF/AppKit integration;
- the ExtensionHost PHP runtime;
- native URL dispatching;
- module install/update/remove UI;
- local protocols such as `babelchrome://viewer/...` and `babelchrome://server/...`.

The detailed native source layout is documented in [Browser Source Layout](../browser/doc/07-browser-source-layout.md). The browser keeps responsibilities split between app lifecycle, CEF integration, window orchestration, UI controls, services, storage, and theme support.

## Library

`library/viewer-kit/` contains `babelforge/babel-chrome-viewer-kit`, a reusable Symfony-oriented library for viewer modules.

## Modules

Each directory under `modules/` is a standalone installable module source project. Each module owns:

- `manifest.json`;
- its runtime-specific source code;
- tests;
- documentation;
- release version;
- `composer.json`, `composer.lock`, and its own `vendor/` only when it is a PHP or Composer-backed module;
- source frontend assets and generated `public/assets/` when needed;
- executable runtime files and non-PHP production dependencies when using a process runtime.

Development vendors are intentionally module-local to avoid dependency conflicts.

## Zip Output

`zip/` contains generated production packages. It is ignored by Git and can be recreated from module sources with `tools/dev2prod.sh`.

Navigation: [Previous: Overview](01-overview.md) | [README](README.md) | [Next: Git And Submodules](03-git-and-submodules.md)
