#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$root/appinfo/info.xml" | head -n1)"
name="booktailor-${version}"
out="$root/build/${name}.tar.gz"

cd "$root"

if [ "${1:-}" != "--skip-build" ]; then
	# js/ and css/ are generated and gitignored; clear stale hashed chunks so
	# the packaged tree only contains this build.
	rm -rf js css
	npm run build
fi

[ -f js/booktailor-main.mjs ] || { echo "missing built js entry" >&2; exit 1; }

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/booktailor"

for item in appinfo lib templates l10n js css img composer.json CHANGELOG.md LICENSE README.md openapi.json; do
	cp -a "$item" "$stage/booktailor/"
done

mkdir -p "$root/build"
rm -f "$out" "${out}.sha256"
tar --sort=name --mtime=@0 --owner=0 --group=0 --numeric-owner \
	-C "$stage" -czf "$out" booktailor
(
	cd "$root/build"
	sha256sum "${name}.tar.gz" > "${name}.tar.gz.sha256"
)

echo "Created $out"
echo "Checksum: $root/build/${name}.tar.gz.sha256"
