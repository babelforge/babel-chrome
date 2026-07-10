#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_DIR="$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)"
ZIP_DIR="${PROJECT_DIR}/zip"
MODULE_FILTER=""
SKIP_BUILD="0"
SKIP_CHECK="0"
ALLOW_DIRTY="0"

usage() {
  cat <<'USAGE'
Usage:
  ./tools/prepare-module-release.sh [--module <module-directory-name|module-id>] [--skip-build] [--skip-check] [--allow-dirty]

Options:
  --module <value>  Prepare artifacts for one module directory name or manifest id.
  --skip-build      Reuse existing zip files instead of calling tools/dev2prod.sh.
  --skip-check      Do not run the workspace validation before building artifacts.
  --allow-dirty     Allow running with uncommitted workspace changes.

Outputs:
  zip/SHA256SUMS for every current module zip
  zip/modules-release-manifest.json for every current module zip
USAGE
}

while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --module)
      if [[ "$#" -lt 2 ]]; then
        usage >&2
        exit 1
      fi
      MODULE_FILTER="$2"
      shift
      ;;
    --skip-build)
      SKIP_BUILD="1"
      ;;
    --skip-check)
      SKIP_CHECK="1"
      ;;
    --allow-dirty)
      ALLOW_DIRTY="1"
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      usage >&2
      exit 1
      ;;
  esac
  shift
done

assert_clean_submodule_pointers() {
  local dirty_status

  if [[ "${ALLOW_DIRTY}" == "1" ]]; then
    return
  fi

  dirty_status="$(git -C "${PROJECT_DIR}" status --short --ignore-submodules=none)"
  if [[ -n "${dirty_status}" ]]; then
    echo "Workspace has uncommitted changes or dirty submodules:" >&2
    echo "${dirty_status}" >&2
    echo "Commit or stash changes before preparing release artifacts." >&2
    exit 1
  fi
}

build_modules() {
  if [[ "${SKIP_BUILD}" == "1" ]]; then
    return
  fi

  if [[ -n "${MODULE_FILTER}" ]]; then
    "${PROJECT_DIR}/tools/dev2prod.sh" "${MODULE_FILTER}"
    return
  fi

  "${PROJECT_DIR}/tools/dev2prod.sh"
}

run_workspace_check() {
  if [[ "${SKIP_CHECK}" == "1" ]]; then
    return
  fi

  if [[ -n "${MODULE_FILTER}" ]]; then
    "${PROJECT_DIR}/tools/check-workspace.sh" --quick --skip-package
    return
  fi

  "${PROJECT_DIR}/tools/check-workspace.sh" --full --skip-package
}

write_release_metadata() {
  php "${PROJECT_DIR}/tools/update-module-release-metadata.php"
}

assert_clean_submodule_pointers
run_workspace_check
build_modules
write_release_metadata

echo "Prepared module release artifacts:"
echo "  ${ZIP_DIR}/SHA256SUMS"
echo "  ${ZIP_DIR}/modules-release-manifest.json"
