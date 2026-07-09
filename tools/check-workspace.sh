#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_DIR="$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)"
MODE="quick"
SKIP_INSTALL="0"
SKIP_PACKAGE="0"

usage() {
  cat <<'USAGE'
Usage:
  ./tools/check-workspace.sh [--quick|--full] [--skip-install] [--skip-package]

Modes:
  --quick  Validate ExtensionHost, Markdown viewer, Demo module, process runtime/process web smoke tests, and focused packaging.
  --full   Validate ExtensionHost, every module with a Composer QA script, every module smoke test, and all packaging.

Options:
  --skip-install   Do not run composer install before QA.
  --skip-package   Do not run tools/dev2prod.sh.
USAGE
}

while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --quick)
      MODE="quick"
      ;;
    --full)
      MODE="full"
      ;;
    --skip-install)
      SKIP_INSTALL="1"
      ;;
    --skip-package)
      SKIP_PACKAGE="1"
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

has_composer_script() {
  local project_dir="$1"
  local script_name="$2"

  php -r '$composer = json_decode((string) file_get_contents($argv[1]."/composer.json"), true); exit(isset($composer["scripts"][$argv[2]]) ? 0 : 1);' "${project_dir}" "${script_name}"
}

run_composer_install() {
  local project_dir="$1"

  if [[ "${SKIP_INSTALL}" == "1" ]]; then
    return
  fi

  echo "Installing Composer dependencies in ${project_dir#${PROJECT_DIR}/}"
  composer install --working-dir="${project_dir}"
}

run_composer_qa() {
  local project_dir="$1"

  if ! has_composer_script "${project_dir}" "qa"; then
    echo "Skipping QA in ${project_dir#${PROJECT_DIR}/}: no Composer qa script"
    return
  fi

  echo "Running QA in ${project_dir#${PROJECT_DIR}/}"
  composer qa --working-dir="${project_dir}"
}

run_smoke_test() {
  local script_path="$1"
  local project_dir

  project_dir="$(dirname "$(dirname "${script_path}")")"
  echo "Running smoke test in ${project_dir#${PROJECT_DIR}/}"
  "${script_path}"
}

assert_submodules_initialized() {
  local missing_submodules

  missing_submodules="$(git -C "${PROJECT_DIR}" submodule status --recursive | awk '/^-/{print $2}')"
  if [[ -n "${missing_submodules}" ]]; then
    echo "Uninitialized submodules:" >&2
    echo "${missing_submodules}" >&2
    echo "Run: git submodule update --init --recursive" >&2
    exit 1
  fi
}

quick_projects() {
  printf '%s\n' \
    "${PROJECT_DIR}/browser/src/ExtensionHost" \
    "${PROJECT_DIR}/modules/markdown-viewer-module" \
    "${PROJECT_DIR}/modules/demo-module"
}

full_projects() {
  printf '%s\n' "${PROJECT_DIR}/browser/src/ExtensionHost"
  find "${PROJECT_DIR}/modules" -mindepth 2 -maxdepth 2 -name composer.json -print \
    | sort \
    | while IFS= read -r composer_file; do
      dirname "${composer_file}"
    done
}

package_modules() {
  if [[ "${SKIP_PACKAGE}" == "1" ]]; then
    return
  fi

  if [[ "${MODE}" == "full" ]]; then
    echo "Building all module zips"
    "${PROJECT_DIR}/tools/dev2prod.sh"
    return
  fi

  echo "Building Demo module zip"
  "${PROJECT_DIR}/tools/dev2prod.sh" babelforge.demo-module
  echo "Building Process Runtime Demo module zip"
  "${PROJECT_DIR}/tools/dev2prod.sh" babelforge.process-runtime-demo
  echo "Building Process Web Demo module zip"
  "${PROJECT_DIR}/tools/dev2prod.sh" babelforge.process-web-demo
  echo "Building Node Process Web Demo module zip"
  "${PROJECT_DIR}/tools/dev2prod.sh" babelforge.node-process-web-demo
}

smoke_test_scripts() {
  if [[ "${MODE}" == "full" ]]; then
    find "${PROJECT_DIR}/modules" -mindepth 3 -maxdepth 3 -path '*/tests/*' -type f -name '*smoke*.sh' \
      | sort \
      | while IFS= read -r script_path; do
        if [[ -x "${script_path}" ]]; then
          printf '%s\n' "${script_path}"
        fi
      done
    return
  fi

  if [[ -x "${PROJECT_DIR}/modules/process-runtime-demo-module/tests/runtime-smoke.sh" ]]; then
    printf '%s\n' "${PROJECT_DIR}/modules/process-runtime-demo-module/tests/runtime-smoke.sh"
  fi
  if [[ -x "${PROJECT_DIR}/modules/process-web-demo-module/tests/process-web-smoke.sh" ]]; then
    printf '%s\n' "${PROJECT_DIR}/modules/process-web-demo-module/tests/process-web-smoke.sh"
  fi
  if [[ -x "${PROJECT_DIR}/modules/node-process-web-demo-module/tests/node-process-web-smoke.sh" ]]; then
    printf '%s\n' "${PROJECT_DIR}/modules/node-process-web-demo-module/tests/node-process-web-smoke.sh"
  fi
}

assert_submodules_initialized

if [[ "${MODE}" == "full" ]]; then
  project_iterator="full_projects"
else
  project_iterator="quick_projects"
fi

while IFS= read -r project_dir; do
  run_composer_install "${project_dir}"
  run_composer_qa "${project_dir}"
done < <("${project_iterator}")

while IFS= read -r script_path; do
  run_smoke_test "${script_path}"
done < <(smoke_test_scripts)

package_modules

echo "Workspace check completed in ${MODE} mode."
