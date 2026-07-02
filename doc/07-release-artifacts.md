# Release Artifacts

Navigation: [Previous: Maintenance Guide](06-maintenance-guide.md) | [README](README.md)

BabelChrome release artifacts are produced from independent repositories but coordinated from the meta workspace.

## Browser Artifact

The native `.app` artifact is built from `browser/`. It should contain the native app, CEF runtime, resources, and ExtensionHost runtime. It should not contain installable module zips by default.

Browser releases can therefore move independently from module releases.

## Module Artifacts

Module artifacts are production zips generated from `modules/*` with:

```bash
./tools/dev2prod.sh
```

Each zip contains the runtime files for one module, including its own Composer production dependencies. The zip is installed from `babelchrome://modules`.

Before publishing module artifacts, run:

```bash
./tools/check-workspace.sh --full
```

Then prepare release metadata:

```bash
./tools/prepare-module-release.sh
```

The release preparation script builds module zips, verifies their presence, writes `zip/SHA256SUMS`, and writes `zip/modules-release-manifest.json`.

For a single module:

```bash
./tools/prepare-module-release.sh --module babelforge.demo-module
```

## Module Update Source

BabelChrome uses `modules-release-manifest.json` as the update catalog for the `Check Updates` action on `babelchrome://modules`. A release source may be:

- a remote URL serving `modules-release-manifest.json`;
- a remote base URL containing `modules-release-manifest.json` and the referenced zips;
- a local folder containing `modules-release-manifest.json` and the referenced zips.

When both a remote URL and a local folder are configured, BabelChrome tries the remote manifest first and falls back to the local folder if the remote source cannot be read.

## Library Releases

Libraries such as `library/viewer-kit/` are Composer packages. They are not installed directly in BabelChrome. Modules consume them through Composer and ship the resolved production dependency in their own zip.

When a library changes:

1. commit and push the library repository;
2. update dependent modules with Composer;
3. run module QA;
4. build new module zips;
5. commit the updated submodule pointers in the meta repository.

## GitHub Releases

The recommended public release model is:

- browser `.app` or `.dmg` artifacts on the browser repository or the meta repository release;
- module `.zip` artifacts attached to module releases or to a coordinated meta release;
- no generated `zip/` directory committed to Git.

This keeps the repository history small while still giving users direct downloadable packages.

Navigation: [Previous: Maintenance Guide](06-maintenance-guide.md) | [README](README.md)
