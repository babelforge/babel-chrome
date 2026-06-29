# Packaging Workflow

Navigation: [Previous: Module System](04-module-system.md) | [README](README.md) | [Next: Maintenance Guide](06-maintenance-guide.md)

Production modules are generated from development module sources. The meta workspace owns the packaging orchestration, but each module owns its own source, Composer lock, assets, tests, and dependencies.

## Build One Module

```bash
./tools/dev2prod.sh babelforge.demo-module
```

The argument may be either the module directory name or the manifest id.

## Build Every Module

```bash
./tools/dev2prod.sh
```

The browser repository also exposes a wrapper:

```bash
browser/tools/build-php-modules.sh
```

## Production Package Contents

The shipper copies the module into a temporary build directory, installs dependencies, compiles Symfony AssetMapper assets when available, installs Composer dependencies without dev packages, and creates a zip in `zip/`.

Runtime packages keep the files needed to execute the module:

- `manifest.json`;
- `composer.json` and `composer.lock`;
- `vendor/`;
- `public/`;
- runtime directories such as `src/`, `templates/`, `config/`, `bootstrap/`, `routes/`, `resources/`, or `storage/`.

Runtime packages exclude development-only paths such as:

- `tests/`;
- `var/`;
- `ai/`;
- `.git/`;
- `build/`;
- `coverage/`;
- `node_modules/`.

## Validation

Before shipping a module:

1. run its Composer QA script when available;
2. build the zip with `tools/dev2prod.sh`;
3. install the zip through `babelchrome://modules`;
4. verify the module route or viewer behavior in BabelChrome.

## Fresh Clone Check

The expected reproducibility check is:

```bash
git clone --recurse-submodules https://github.com/babelforge/babel-chrome.git
cd babel-chrome
composer install --working-dir=browser/src/ExtensionHost
composer install --working-dir=modules/markdown-viewer-module
composer install --working-dir=modules/demo-module
composer qa --working-dir=browser/src/ExtensionHost
composer qa --working-dir=modules/markdown-viewer-module
composer qa --working-dir=modules/demo-module
./tools/dev2prod.sh babelforge.demo-module
```

This verifies the host, one Symfony viewer module, one non-viewer module, and one production package.

Navigation: [Previous: Module System](04-module-system.md) | [README](README.md) | [Next: Maintenance Guide](06-maintenance-guide.md)
