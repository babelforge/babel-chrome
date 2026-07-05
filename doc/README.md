# BabelChrome Workspace Documentation

Navigation: [Next: Overview](01-overview.md)

This documentation describes the `babel-chrome` meta workspace. The workspace coordinates the native BabelChrome browser, shared libraries, installable modules, packaging scripts, and release documentation.

`babel-chrome` is not meant to own every source file directly. Its role is to provide a reproducible ecosystem layout where the browser, libraries, and modules can remain independently versioned while still being built and tested together.

## Pages

1. [Overview](01-overview.md)
2. [Workspace Layout](02-workspace-layout.md)
3. [Git And Submodules](03-git-and-submodules.md)
4. [Module System](04-module-system.md)
5. [Packaging Workflow](05-packaging-workflow.md)
6. [Maintenance Guide](06-maintenance-guide.md)
7. [Release Artifacts](07-release-artifacts.md)
8. [Module Integration Features](08-module-integration-features.md)

## Related Submodule Documentation

- [Browser Source Layout](../browser/doc/07-browser-source-layout.md) documents the native application source tree and the responsibility boundaries under `browser/src/`.

## Current Target Layout

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
  tools/
    dev2prod.sh
  zip/
  doc/
```

`zip/`, `vendor/`, `var/`, and `ai/` are local-only directories and must not be committed to the meta repository.

Navigation: [Next: Overview](01-overview.md)
