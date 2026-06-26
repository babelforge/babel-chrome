# Module System

Navigation: [Previous: Git And Submodules](03-git-and-submodules.md) | [README](README.md) | [Next: Packaging Workflow](05-packaging-workflow.md)

BabelChrome modules are independent PHP projects installed into BabelChrome as zip packages. The host discovers a module from its root `manifest.json`.

## Runtime Contract

A framework-agnostic web module declares a front controller:

```json
{
  "runtime": {
    "type": "web",
    "entrypoint": "public/index.php"
  },
  "entrypoint": "public/index.php"
}
```

The entrypoint may boot Symfony, Laravel, plain PHP, or another layout. BabelChrome does not require a framework-specific adapter.

Modules that need strict Composer isolation can request a dedicated PHP process:

```json
{
  "runtime": {
    "type": "web",
    "entrypoint": "public/index.php",
    "processIsolation": true
  }
}
```

## Routes

Modules can expose stable BabelChrome routes through their manifest:

```json
{
  "scheme": "babelchrome",
  "host": "project-launcher",
  "handler": "index"
}
```

The host passes route context and source metadata through `BABELCHROME_*` environment values.

## Viewers

Viewer modules declare handled file extensions through `file-type-handler`. Enabled viewer modules contribute to:

- `X-BabelChrome-File-Types`;
- address bar badges;
- `babelchrome://viewer/file/<encoded-path>`;
- `babelchrome://viewer/url/<encoded-url>`.

Viewer modules that share the common file-viewer UI should declare `supports: ["file-viewer"]` and may use `babelforge/babel-chrome-viewer-kit`.

## Available Modules

Current module sources:

- `babelforge.demo-module`;
- `babelforge.json-viewer`;
- `babelforge.laravel-module`;
- `babelforge.markdown-viewer`;
- `babelforge.openapi-viewer`;
- `babelforge.plain-php-module`;
- `babelforge.project-launcher`.

Navigation: [Previous: Git And Submodules](03-git-and-submodules.md) | [README](README.md) | [Next: Packaging Workflow](05-packaging-workflow.md)
