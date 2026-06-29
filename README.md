# BabelChrome

BabelChrome is the meta workspace for the BabelChrome ecosystem.

It coordinates:

- the native macOS CEF browser application;
- shared libraries used by BabelChrome modules;
- installable PHP modules;
- module packaging tools;
- root documentation for workspace and release workflows.

## Documentation

Start with [doc/README.md](doc/README.md).

## Clone

Clone the complete workspace with submodules:

```bash
git clone --recurse-submodules https://github.com/babelforge/babel-chrome.git
```

For an existing clone:

```bash
git submodule update --init --recursive
```

## Workspace

```text
browser/
library/viewer-kit/
modules/
tools/
doc/
```

The browser, libraries, and modules are independent Git repositories attached to this workspace as submodules.

## Build Module Packages

Module source directories are development workspaces. They are not installed directly into BabelChrome.

Build installable production zips from the meta workspace root:

```bash
./tools/dev2prod.sh
```

Build one module by directory name or manifest id:

```bash
./tools/dev2prod.sh markdown-viewer-module
./tools/dev2prod.sh babelforge.markdown-viewer
```

Generated archives are written to `zip/`, which is ignored by Git.

## Install Modules

Install generated zips from the running browser:

1. Open `babelchrome://modules`.
2. Click `Install Module`.
3. Select a zip from `zip/`.
4. Keep the module enabled.

Viewer behavior such as Markdown, OpenAPI, and JSON rendering exists only after the matching module is installed and enabled.
