# Git And Submodules

Navigation: [Previous: Workspace Layout](02-workspace-layout.md) | [README](README.md) | [Next: Module System](04-module-system.md)

The root `babel-chrome` repository is intended to be a meta repository. The browser, libraries, and modules should be independent repositories attached as submodules.

## Planned Repositories

```text
babelforge/babel-chrome
babelforge/babel-chrome-browser
babelforge/babel-chrome-viewer-kit
babelforge/babelchrome-demo-module
babelforge/babelchrome-json-viewer-module
babelforge/babelchrome-laravel-module
babelforge/babelchrome-markdown-viewer-module
babelforge/babelchrome-openapi-viewer-module
babelforge/babelchrome-plain-php-module
babelforge/babelchrome-project-launcher-module
```

## Clone Workflow

Once the meta repository is initialized, a full checkout should use:

```bash
git clone --recurse-submodules <repo-url>
```

Existing clones should update with:

```bash
git submodule update --init --recursive
```

## Daily Workflow

Changes are committed in the repository that owns the files:

- native app changes go into `browser/`;
- viewer kit changes go into `library/viewer-kit/`;
- module changes go into their module directory;
- root docs and packaging orchestration go into the root meta repository.

After changing a submodule, the root repository must also be updated if the meta workspace should point to the new submodule commit.

## Risk

Submodules make ownership explicit but require discipline. A pushed module change is not automatically reflected in the root meta repository until the submodule pointer is committed there.

Navigation: [Previous: Workspace Layout](02-workspace-layout.md) | [README](README.md) | [Next: Module System](04-module-system.md)
