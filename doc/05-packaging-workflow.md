# Packaging Workflow

Navigation: [Previous: Module Operations](04-module-operations.md) | [README](README.md) | [Next: Maintenance Guide](06-maintenance-guide.md)

Production modules are generated from development module sources. The meta workspace owns the packaging orchestration, but each module owns its own source, assets, tests, dependencies, and release version. Modules that use Composer own their Composer lock.

## Build One Module

```bash
./tools/dev2prod.sh babelforge.demo-module
```

The argument may be either the module directory name or the manifest id.

## Build Every Module

```bash
./tools/dev2prod.sh
```

The browser repository also exposes a historical wrapper:

```bash
browser/tools/build-php-modules.sh
```

## Production Package Contents

The shipper copies the module into a temporary build directory, prepares runtime dependencies when the module type needs them, compiles Symfony AssetMapper assets when available, installs Composer dependencies without dev packages when Composer is present, and creates a zip in `zip/`.

Runtime packages keep the files needed to execute the module for its declared runtime:

- `manifest.json`;
- `composer.json`, `composer.lock`, and `vendor/` when the runtime needs Composer;
- `public/`;
- PHP framework directories such as `src/`, `templates/`, `config/`, `bootstrap`, `routes`, `resources`, `storage`, or `database`;
- static web documents for `static-web` runtimes;
- executable scripts, compiled assets, and module-owned production dependencies for `process-web` and `process-runtime` runtimes.

Runtime packages exclude development-only paths such as:

- `tests/`;
- `var/`, except when the declared process runtime uses it as a module-owned runtime directory;
- `ai/`;
- `.git/`;
- `build/`;
- `coverage/`;
- `node_modules/`, except when the declared process runtime needs it as a production runtime dependency.

The shipper should not assume that every module is a PHP project. Composer-based modules ship Composer production dependencies, static web runtimes ship static public files, and process runtimes ship the executable files and production dependencies required by their command contract.

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
./tools/check-workspace.sh
```

This verifies the host, one Symfony viewer module, one non-viewer module, and one production package.

For release preparation, run the full workspace check:

```bash
./tools/check-workspace.sh --full
```

This runs QA for every module exposing a Composer `qa` script and builds every module zip.

Navigation: [Previous: Module Operations](04-module-operations.md) | [README](README.md) | [Next: Maintenance Guide](06-maintenance-guide.md)
