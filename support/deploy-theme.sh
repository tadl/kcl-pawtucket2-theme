#!/usr/bin/env bash

set -euo pipefail

usage() {
	printf 'Usage: %s [--dry-run] user@host\n' "$0"
}

if [[ "${1:-}" == '--help' && "$#" -eq 1 ]]; then
	usage
	exit 0
fi
dry_run=0
if [[ "${1:-}" == '--dry-run' ]]; then
	dry_run=1
	shift
fi
if [[ "$#" -ne 1 || ! "$1" =~ ^([A-Za-z0-9_.-]+@)?[A-Za-z0-9][A-Za-z0-9_.-]*$ ]]; then
	usage >&2
	exit 1
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source_dir="$(cd -- "${script_dir}/.." && pwd)/"
destination="$1:/var/www/kcl/themes/kcl/"
if [[ ! -f "${source_dir}conf/app.conf" || ! -d "${source_dir}views" ]]; then
	printf 'Theme source directory is incomplete: %s\n' "${source_dir}" >&2
	exit 1
fi

rsync_options=(
	--archive
	--verbose
	--itemize-changes
	--exclude='.git/'
	--exclude='.gitignore'
	--exclude='README.md'
	--exclude='.DS_Store'
)
if [[ "${dry_run}" -eq 1 ]]; then
	rsync_options+=(--dry-run)
fi
printf 'Deploying %s to %s\n' "${source_dir}" "${destination}"
rsync "${rsync_options[@]}" "${source_dir}" "${destination}"

# Keep application caches, sessions, temporary files and services unchanged.
if [[ "${dry_run}" -eq 1 ]]; then
	printf '%s\n' 'Dry run complete (no theme files changed)'
else
	printf '%s\n' 'Deployment complete (application caches preserved; no service reload)'
fi
