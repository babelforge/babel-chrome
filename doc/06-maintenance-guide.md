# Maintenance Guide

Navigation: [Previous: Packaging Workflow](05-packaging-workflow.md) | [README](README.md) | [Next: Release Artifacts](07-release-artifacts.md)

Maintenance work should preserve repository ownership boundaries.

## Browser Changes

Commit native app, ExtensionHost, and browser-specific documentation changes in `browser/`.

Run at least:

```bash
composer qa
```

from `browser/src/ExtensionHost` when changing the PHP host.

## Library Changes

Commit shared viewer library changes in `library/viewer-kit/`.

After changing the library, update dependent modules with Composer so their locks reference the intended library commit.

## Module Changes

Commit each module change in its own module repository. Do not move shared runtime behavior into a module if it belongs in the browser host or a shared library.

Run module QA before packaging:

```bash
composer qa
```

## Meta Repository Changes

Commit root documentation, root scripts, and submodule pointer updates in the root meta repository.

Do not commit:

- `ai/`;
- `zip/`;
- `vendor/`;
- `var/`;
- generated caches.

## Documentation Registry

When public documentation changes, update the BabelForge shared documentation registry for this project in:

```text
/Users/user/Documents/__Dev/_projets/__README/- associated-mini-projects -/PhpNoobs/all-projects/README.md
/Users/user/Documents/__Dev/_projets/__README/- associated-mini-projects -/PhpNoobs/all-projects/projects.json
```

Navigation: [Previous: Packaging Workflow](05-packaging-workflow.md) | [README](README.md) | [Next: Release Artifacts](07-release-artifacts.md)
