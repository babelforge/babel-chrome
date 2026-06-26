#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_DIR="$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)"
BABEL_CHROME_BROWSER_DIR="${BABEL_CHROME_BROWSER_DIR:-${BABEL_CHROME_WORKSPACE:-$(CDPATH= cd -- "${PROJECT_DIR}/browser" && pwd)}}"
MODULES_DIR="${PROJECT_DIR}/modules"
ZIP_DIR="${PROJECT_DIR}/zip"
BUILD_ROOT="${PROJECT_DIR}/var/dev2prod/$$"
MODULE_FILTER="${1:-}"

usage() {
  cat <<'USAGE'
Usage:
  ./tools/dev2prod.sh
  ./tools/dev2prod.sh <module-directory-name|module-id>

Examples:
  ./tools/dev2prod.sh
  ./tools/dev2prod.sh plain-php-module
  ./tools/dev2prod.sh babelforge.plain-php-module
USAGE
}

if [[ "${MODULE_FILTER}" == "-h" || "${MODULE_FILTER}" == "--help" ]]; then
  usage
  exit 0
fi

if [[ "$#" -gt 1 ]]; then
  usage >&2
  exit 1
fi

mkdir -p "${ZIP_DIR}"
rm -rf "${BUILD_ROOT}"
mkdir -p "${BUILD_ROOT}"
trap 'rm -rf "${BUILD_ROOT}"' EXIT

manifest_value() {
  local manifest="$1"
  local key="$2"

  php -r '$manifest = json_decode((string) file_get_contents($argv[1]), true); $key = $argv[2]; if (!is_array($manifest) || !isset($manifest[$key]) || !is_string($manifest[$key])) { fwrite(STDERR, "Invalid module manifest value: ".$key."\n"); exit(1); } echo $manifest[$key];' "${manifest}" "${key}"
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

ship_module() {
  local module_dir="$1"
  local module_manifest="${module_dir}/manifest.json"
  local module_id
  local module_version
  local build_module_dir
  local target_path

  module_id="$(manifest_value "${module_manifest}" "id")"
  module_version="$(manifest_value "${module_manifest}" "version")"

  echo "Preparing ${module_id} ${module_version}"

  build_module_dir="${BUILD_ROOT}/${module_id}"
  cp -R "${module_dir}" "${build_module_dir}"

  if [[ -f "${build_module_dir}/composer.json" ]]; then
    (
      cd "${build_module_dir}"
      composer install
    )
  fi

  if [[ -f "${build_module_dir}/bin/console" ]]; then
    (
      cd "${build_module_dir}"
      rm -rf public/assets var/cache
      php bin/console asset-map:compile --env=prod --no-debug
    )
  fi

  if [[ -f "${build_module_dir}/composer.json" ]]; then
    (
      cd "${build_module_dir}"
      composer install --no-dev --classmap-authoritative
    )
  fi

  target_path="${ZIP_DIR}/${module_id}-${module_version}.zip"
  php "${BABEL_CHROME_BROWSER_DIR}/tools/ship-php-module.php" "${build_module_dir}" "${target_path}" >/dev/null
  echo "Built ${target_path}"
}

while IFS= read -r module_dir; do
  ship_module "${module_dir}"
done < <(resolve_module_dirs)
