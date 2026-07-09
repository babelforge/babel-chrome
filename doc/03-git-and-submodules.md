# Git And Submodules

Navigation: [Previous: Workspace Layout](02-workspace-layout.md) | [README](README.md) | [Next: Module Operations](04-module-operations.md)

The root `babel-chrome` repository is intended to be a meta repository. The browser, libraries, and modules should be independent repositories attached as submodules.

## Repositories

```text
babelforge/babel-chrome
babelforge/babel-chrome-browser
babelforge/babel-chrome-viewer-kit
babelforge/babelchrome-demo-module
babelforge/babelchrome-json-viewer-module
babelforge/babelchrome-laravel-module
babelforge/babelchrome-markdown-viewer-module
babelforge/babelchrome-node-process-web-demo-module
babelforge/babelchrome-openapi-viewer-module
babelforge/babelchrome-plain-php-module
babelforge/babelchrome-project-launcher-module
babelforge/babelchrome-process-runtime-demo-module
babelforge/babelchrome-process-web-demo-module
```

## Clone Workflow

A full checkout should use:

```bash
git clone --recurse-submodules https://github.com/babelforge/babel-chrome.git
```

Existing clones should update with:

```bash
git submodule update --init --recursive
```

To refresh every submodule after remote updates:

```bash
git submodule update --remote --recursive
```

## Submodule URLs

The public `.gitmodules` file uses HTTPS GitHub URLs on purpose. This keeps submodule links directly clickable from the GitHub web interface and lets external users clone the workspace without knowing any local SSH host alias.

Developers who prefer SSH can still override submodule remotes locally after cloning:

```bash
git -C browser remote set-url origin git@github.com-BabelForge:babelforge/babel-chrome-browser.git
```

That local override should not be committed to the meta repository.

## Daily Workflow

Changes are committed in the repository that owns the files:

- native app changes go into `browser/`;
- viewer kit changes go into `library/viewer-kit/`;
- module changes go into their module directory;
- root docs and packaging orchestration go into the root meta repository.

After changing a submodule, the root repository must also be updated if the meta workspace should point to the new submodule commit.

Example:

```bash
cd modules/markdown-viewer-module
git add .
git commit -m "Improve Markdown viewer"
git push
cd ../..
git add modules/markdown-viewer-module
git commit -m "Update Markdown viewer pointer"
git push
```

## Risk

Submodules make ownership explicit but require discipline. A pushed module change is not automatically reflected in the root meta repository until the submodule pointer is committed there.

Navigation: [Previous: Workspace Layout](02-workspace-layout.md) | [README](README.md) | [Next: Module Operations](04-module-operations.md)
