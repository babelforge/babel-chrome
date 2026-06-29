#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_DIR="$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)"
MODULES_DIR="${PROJECT_DIR}/modules"
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
  zip/SHA256SUMS
  zip/modules-release-manifest.json
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

manifest_value() {
  local manifest="$1"
  local key="$2"

  php -r '$manifest = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); $key = $argv[2]; if (!isset($manifest[$key]) || !is_string($manifest[$key])) { fwrite(STDERR, "Invalid module manifest value: ".$key."\n"); exit(1); } echo $manifest[$key];' "${manifest}" "${key}"
}

resolve_module_dirs() {
  local module_manifest
  local module_id

  if [[ -z "${MODULE_FILTER}" ]]; then
    for module_manifest in "${MODULES_DIR}"/*/manifest.json; do
      [[ -f "${module_manifest}" ]] || continue
      dirname "${module_manifest}"
    done
    return
  fi

  if [[ -f "${MODULES_DIR}/${MODULE_FILTER}/manifest.json" ]]; then
    printf '%s\n' "${MODULES_DIR}/${MODULE_FILTER}"
    return
  fi

  for module_manifest in "${MODULES_DIR}"/*/manifest.json; do
    [[ -f "${module_manifest}" ]] || continue
    module_id="$(manifest_value "${module_manifest}" "id")"
    if [[ "${MODULE_FILTER}" == "${module_id}" ]]; then
      dirname "${module_manifest}"
      return
    fi
  done

  echo "No module matched '${MODULE_FILTER}'." >&2
  exit 1
}

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

write_checksums() {
  local checksum_path="${ZIP_DIR}/SHA256SUMS"

  mkdir -p "${ZIP_DIR}"
  : > "${checksum_path}"

  while IFS= read -r module_dir; do
    local module_manifest="${module_dir}/manifest.json"
    local module_id
    local module_version
    local zip_name

    module_id="$(manifest_value "${module_manifest}" "id")"
    module_version="$(manifest_value "${module_manifest}" "version")"
    zip_name="${module_id}-${module_version}.zip"

    if [[ ! -f "${ZIP_DIR}/${zip_name}" ]]; then
      echo "Missing module zip: ${ZIP_DIR}/${zip_name}" >&2
      exit 1
    fi

    (
      cd "${ZIP_DIR}"
      shasum -a 256 "${zip_name}"
    ) >> "${checksum_path}"
  done < <(resolve_module_dirs)
}

write_release_manifest() {
  local manifest_path="${ZIP_DIR}/modules-release-manifest.json"
  local module_dirs=()

  while IFS= read -r module_dir; do
    module_dirs+=("${module_dir}")
  done < <(resolve_module_dirs)

  php -r '
    $zipDir = $argv[1];
    $checksumFile = $zipDir."/SHA256SUMS";
    $checksums = [];
    foreach (file($checksumFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        [$checksum, $file] = preg_split("/\s+/", $line, 2);
        $checksums[$file] = $checksum;
    }

    $modules = [];
    for ($i = 2; $i < $argc; ++$i) {
        $moduleDir = $argv[$i];
        $manifest = json_decode((string) file_get_contents($moduleDir."/manifest.json"), true, 512, JSON_THROW_ON_ERROR);
        $id = $manifest["id"];
        $version = $manifest["version"];
        $zipName = $id."-".$version.".zip";
        $modules[] = [
            "id" => $id,
            "name" => $manifest["name"] ?? $id,
            "version" => $version,
            "zip" => $zipName,
            "sha256" => $checksums[$zipName] ?? null,
            "source" => substr($moduleDir, strlen(dirname($zipDir)) + 1),
        ];
    }

    $release = [
        "generatedAt" => date(DATE_ATOM),
        "modules" => $modules,
    ];

    file_put_contents($zipDir."/modules-release-manifest.json", json_encode($release, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
  ' "${ZIP_DIR}" "${module_dirs[@]}"
}

assert_clean_submodule_pointers
run_workspace_check
build_modules
write_checksums
write_release_manifest

echo "Prepared module release artifacts:"
echo "  ${ZIP_DIR}/SHA256SUMS"
echo "  ${ZIP_DIR}/modules-release-manifest.json"
